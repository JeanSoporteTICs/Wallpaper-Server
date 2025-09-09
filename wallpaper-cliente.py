import urllib.error
import os
import sys
import json
import uuid
import time
import threading
import subprocess
import socket
import urllib.request
import ctypes
import winreg
import logging
import base64
import requests
import psutil
import tempfile
import shutil
import getpass
import tkinter as tk
from tkinter import ttk, messagebox
from ctypes import wintypes as wt
from zoneinfo import ZoneInfo
from http.server import ThreadingHTTPServer, BaseHTTPRequestHandler
from pathlib import Path
from datetime import datetime
from logging.handlers import RotatingFileHandler

# =============================
# CONFIGURACIÓN BÁSICA
# =============================
DEFAULT_PORT = 8584
APP_NAME = "wallpaper-cliente"
LOCAL_APPDATA = os.getenv("LOCALAPPDATA", os.path.expanduser("~"))
APP_FOLDER = os.path.join(LOCAL_APPDATA, APP_NAME)
CHILE_TZ = ZoneInfo("America/Santiago")
# Rutas centralizadas
CONFIG_FILE = os.path.join(APP_FOLDER, "config.json")
BACKUP_CONFIG_FILE = os.path.join(APP_FOLDER, "backup-config.json")
DATA_CLIENTE_FILE = os.path.join(APP_FOLDER, "data-cliente.json")
WALLPAPER_FILE = os.path.join(APP_FOLDER, "wallpaper.jpg")
LOG_FILE = os.path.join(APP_FOLDER, "client.log")

MAX_LOG_SIZE = 5 * 1024 * 1024  # 5 MB
BACKUP_COUNT = 3
HTTP_TIMEOUT = 8

FIREWALL_RULE_NAME = "Wallpaper-Webhook"

# Windows API para wallpaper
SPI_SETDESKWALLPAPER = 20
SPIF_UPDATEINIFILE = 0x01
SPIF_SENDCHANGE = 0x02
CRYPTPROTECT_UI_FORBIDDEN = 0x01

# Variable global para el servidor webhook
webhook_server = None

# Lock global único
json_lock = threading.Lock()

# =============================
# DPAPI para cifrar secret
# =============================


class DATA_BLOB(ctypes.Structure):
    _fields_ = [("cbData", wt.DWORD), ("pbData",
                                       ctypes.POINTER(ctypes.c_char))]


def _blob_from_bytes(b: bytes) -> "DATA_BLOB":
    blob = DATA_BLOB()
    blob.cbData = len(b)
    blob.pbData = ctypes.cast(ctypes.create_string_buffer(
        b), ctypes.POINTER(ctypes.c_char))
    return blob


def dpapi_encrypt(plaintext: str) -> str:
    data_in = _blob_from_bytes(plaintext.encode('utf-8'))
    data_out = DATA_BLOB()
    if not ctypes.windll.crypt32.CryptProtectData(
        ctypes.byref(data_in), None, None, None, None,
        CRYPTPROTECT_UI_FORBIDDEN, ctypes.byref(data_out)
    ):
        raise OSError("CryptProtectData failed")
    try:
        buf = ctypes.string_at(data_out.pbData, data_out.cbData)
        return base64.b64encode(buf).decode('ascii')
    finally:
        ctypes.windll.kernel32.LocalFree(data_out.pbData)


def dpapi_decrypt(cipher_b64: str) -> str:
    raw = base64.b64decode(cipher_b64)
    data_in = _blob_from_bytes(raw)
    data_out = DATA_BLOB()
    if not ctypes.windll.crypt32.CryptUnprotectData(
        ctypes.byref(data_in), None, None, None, None,
        CRYPTPROTECT_UI_FORBIDDEN, ctypes.byref(data_out)
    ):
        raise OSError("CryptUnprotectData failed")
    try:
        buf = ctypes.string_at(data_out.pbData, data_out.cbData)
        return buf.decode('utf-8')
    finally:
        ctypes.windll.kernel32.LocalFree(data_out.pbData)


def get_secret_from_cfg(cfg: dict | None) -> str | None:
    if not cfg:
        return None
    if cfg.get("secret_key_dpapi"):
        try:
            return dpapi_decrypt(cfg["secret_key_dpapi"])
        except Exception as e:
            log.error(f"Error descifrando secret_key_dpapi: {e}")
            return None
    if cfg.get("secret_key"):  # retrocompatibilidad
        return cfg["secret_key"]
    return None

# =============================
# LOGGING
# =============================


def setup_logging():
    os.makedirs(APP_FOLDER, exist_ok=True)

    logger = logging.getLogger("wallpaper_client")
    logger.setLevel(logging.DEBUG)

    formatter = logging.Formatter("%(asctime)s - %(levelname)s - %(message)s")

    file_handler = RotatingFileHandler(
        LOG_FILE, maxBytes=MAX_LOG_SIZE, backupCount=BACKUP_COUNT, encoding="utf-8"
    )
    file_handler.setFormatter(formatter)
    logger.addHandler(file_handler)

    console_handler = logging.StreamHandler()
    console_handler.setFormatter(formatter)
    logger.addHandler(console_handler)

    return logger


log = setup_logging()
log.info("Logger configurado correctamente")

# =============================
# UTILIDADES
# =============================


def ensure_app_folder():
    try:
        os.makedirs(APP_FOLDER, exist_ok=True)
        log.info(f"Carpeta de aplicación verificada: {APP_FOLDER}")
    except Exception as e:
        log.error(f"Error creando carpeta de aplicación: {e}")
        raise


def guardar_json(path, data):
    try:
        with json_lock:
            with open(path, "w", encoding="utf-8") as f:
                json.dump(data, f, indent=2, ensure_ascii=False)
        log.debug(f"JSON guardado en {path}")
    except Exception as e:
        log.error(f"Error guardando JSON en {path}: {e}")
        raise


def cargar_json(path):
    if not os.path.exists(path):
        log.debug(f"JSON no encontrado: {path}")
        return None
    try:
        with json_lock:
            with open(path, "r", encoding="utf-8") as f:
                return json.load(f)
    except json.JSONDecodeError:
        log.error(f"Error decodificando JSON en {path}")
        return None
    except Exception as e:
        log.error(f"Error cargando JSON desde {path}: {e}")
        return None


def obtener_nombre():
    try:
        return os.getlogin()
    except Exception:
        try:
            return getpass.getuser()
        except Exception as e:
            log.error(f"Error obteniendo usuario: {e}")
            return "unknown"


def obtener_ip_local():
    try:
        with socket.socket(socket.AF_INET, socket.SOCK_DGRAM) as s:
            try:
                s.connect(("8.8.8.8", 80))
                return s.getsockname()[0]
            except Exception as e:
                log.warning(
                    f"No se pudo obtener IP local, usando 127.0.0.1: {e}")
                return "127.0.0.1"
    except Exception as e:
        log.error(f"Error creando socket para IP local: {e}")
        return "127.0.0.1"


def obtener_mac():
    try:
        ip_local = obtener_ip_local()
        for iface, addrs in psutil.net_if_addrs().items():
            ipv4_ok = any(
                addr.family == socket.AF_INET and addr.address == ip_local for addr in addrs)
            if not ipv4_ok:
                continue
            for addr in addrs:
                # psutil.AF_LINK puede no existir en versiones nuevas; usar getattr
                if getattr(psutil, 'AF_LINK', None) and addr.family == psutil.AF_LINK:
                    return (addr.address or '').upper()
        return "00:00:00:00:00:00"
    except Exception as e:
        log.error(f"Error obteniendo MAC: {e}")
        return "00:00:00:00:00:00"

# =============================
# ID ESTABLE DEL CLIENTE
# =============================


def get_machine_guid() -> str | None:
    try:
        with winreg.OpenKey(winreg.HKEY_LOCAL_MACHINE, r"SOFTWARE\Microsoft\Cryptography") as k:
            v, _ = winreg.QueryValueEx(k, "MachineGuid")
            v = (v or "").strip()
            if v and v.upper() not in ["UNKNOWN", "NULL", "N/A"]:
                return v
    except Exception:
        pass
    return None


def get_current_user_sid() -> str | None:
    try:
        sid = subprocess.check_output(
            ["powershell", "-NoProfile", "-Command",
             "([System.Security.Principal.WindowsIdentity]::GetCurrent()).User.Value"],
            text=True, stderr=subprocess.STDOUT, timeout=5
        ).strip()
        if sid:
            return sid
    except Exception as e:
        log.debug(f"PowerShell SID falló: {e}")
    # Fallback (opcional): intenta con 'whoami /user' si quieres
    try:
        out = subprocess.check_output(
            ["whoami", "/user"], text=True, timeout=5)
        # Busca el primer token que parezca un SID S-1-...
        for tok in out.split():
            if tok.startswith("S-1-"):
                return tok.strip()
    except Exception as e:
        log.debug(f"whoami /user falló: {e}")
    return None


def crear_id_cliente(username: str | None = None, mac: str | None = None) -> str:
    sid = get_current_user_sid()
    mg = get_machine_guid()  # ya la tienes en tu código

    if sid and mg:
        return f"user_{sid.lower()}__win_{mg[:8].lower()}"
    if sid:
        return f"user_{sid.lower()}"
    if mg:
        return f"win_{mg.lower()}"

    # Último recurso: username + uuid (para no bloquear si algo falla)
    uname = (username or obtener_nombre() or "unknown").strip().lower()
    return f"user_{uname}__uuid_{uuid.uuid4().hex[:8]}"


# =============================
# FIREWALL
# =============================


def crear_regla_firewall(puerto=DEFAULT_PORT, nombre_regla=FIREWALL_RULE_NAME):
    try:
        cmd_check = f'netsh advfirewall firewall show rule name="{nombre_regla}"'
        res = subprocess.run(
            cmd_check, capture_output=True, text=True, shell=True)
        stdout = (res.stdout or "") + (res.stderr or "")
        if any(x in stdout for x in ["No rules", "No hay reglas", "There is no"]):
            cmd_add = (
                f'netsh advfirewall firewall add rule name="{nombre_regla}" '
                f"dir=in action=allow protocol=TCP localport={puerto}"
            )
            subprocess.run(cmd_add, shell=True)
            log.info(f"Regla de firewall creada: {nombre_regla}:{puerto}")
        else:
            log.info(f"Regla de firewall ya existe: {nombre_regla}")
    except Exception as e:
        log.error(f"Error creando regla de firewall: {e}")

# =============================
# WALLPAPER
# =============================


def set_wallpaper(image_path, style="fit"):
    """Aplica un wallpaper con el estilo indicado.
    style: 'fill','fit','stretch','tile','center','span'
    """
    STYLE_MAP = {
        "fill": ("10", "0"),
        "fit": ("6", "0"),
        "stretch": ("2", "0"),
        "tile": ("0", "1"),
        "center": ("0", "0"),
        "span": ("22", "0"),
    }
    if style not in STYLE_MAP:
        log.warning(f"Estilo no válido: {style}. Usando 'fill'.")
        style = "fill"

    wallpaper_path = os.path.abspath(image_path)

    # Registro
    try:
        with winreg.OpenKey(
            winreg.HKEY_CURRENT_USER, r"Control Panel\\Desktop", 0, winreg.KEY_SET_VALUE
        ) as key:
            winreg.SetValueEx(key, "WallpaperStyle", 0,
                              winreg.REG_SZ, STYLE_MAP[style][0])
            winreg.SetValueEx(key, "TileWallpaper", 0,
                              winreg.REG_SZ, STYLE_MAP[style][1])
    except Exception as e:
        log.error(f"Error escribiendo estilo en registro: {e}")

    # Aplicar
    try:
        r = ctypes.windll.user32.SystemParametersInfoW(
            SPI_SETDESKWALLPAPER, 0, wallpaper_path, SPIF_UPDATEINIFILE | SPIF_SENDCHANGE
        )
        if not r:
            log.error("SystemParametersInfoW falló al establecer wallpaper")
        else:
            log.info(f"Wallpaper aplicado: {wallpaper_path}")
    except Exception as e:
        log.error(f"Error aplicando wallpaper: {e}")


def lock_wallpaper_option():
    try:
        key_path = r"Software\Microsoft\Windows\CurrentVersion\Policies\ActiveDesktop"
        try:
            key = winreg.OpenKey(winreg.HKEY_CURRENT_USER,
                                 key_path, 0, winreg.KEY_SET_VALUE)
        except FileNotFoundError:
            key = winreg.CreateKey(winreg.HKEY_CURRENT_USER, key_path)
        winreg.SetValueEx(key, "NoChangingWallpaper", 0, winreg.REG_DWORD, 1)
        winreg.CloseKey(key)
        ctypes.windll.user32.SystemParametersInfoW(20, 0, None, 3)
        log.info("Wallpaper bloqueado (NoChangingWallpaper=1)")
    except Exception as e:
        log.error(f"Error bloqueando wallpaper: {e}")


def unlock_wallpaper_option():
    try:
        key_path = r"Software\Microsoft\Windows\CurrentVersion\Policies\ActiveDesktop"
        try:
            with winreg.OpenKey(winreg.HKEY_CURRENT_USER, key_path, 0, winreg.KEY_SET_VALUE) as key:
                try:
                    winreg.DeleteValue(key, "NoChangingWallpaper")
                except FileNotFoundError:
                    pass
        except FileNotFoundError:
            pass
        log.info("Wallpaper desbloqueado")
    except Exception as e:
        log.error(f"Error desbloqueando wallpaper: {e}")

# ----------------------------
# SISTEMA DE ACCIONES PENDIENTES
# ----------------------------


def agregar_accion_pendiente(accion: dict):
    try:
        data = cargar_json(DATA_CLIENTE_FILE) or {}
        if "pending" not in data:
            data["pending"] = []
        if "timestamp" not in accion:
            accion["timestamp"] = chile_now_ampm()
        if len(data["pending"]) >= 100:
            data["pending"] = data["pending"][-50:]
        data["pending"].append(accion)
        guardar_json(DATA_CLIENTE_FILE, data)
        log.info(
            f"Acción añadida a pendientes: {accion.get('action', 'unknown')}")
        return True
    except Exception as e:
        log.error(f"Error añadiendo acción pendiente: {e}")
        return False


def procesar_accion_pendiente(accion: dict):
    try:
        action_type = accion.get("action")
        if action_type == "set_wallpaper":
            style = accion.get("style", "fill")
            image_name = accion.get("image_name")
            if not image_name:
                log.error("Acción set_wallpaper sin image_name")
                return False
            cfg = cargar_json(CONFIG_FILE) or {}
            base = build_base_url(cfg)
            url = f"{base}/fondos/{image_name}"
            log.info(f"Procesando wallpaper pendiente: {image_name}")
            if descargar_wallpaper(url, WALLPAPER_FILE):
                set_wallpaper(WALLPAPER_FILE, style=style)
                return True
            else:
                log.error("No se pudo descargar el wallpaper")
                return False
        elif action_type == "lock":
            lock_wallpaper_option()
            return True
        elif action_type == "unlock":
            unlock_wallpaper_option()
            return True
        else:
            log.warning(f"Acción pendiente desconocida: {action_type}")
            return False
    except Exception as e:
        log.error(f"Error procesando acción pendiente {accion}: {e}")
        return False


def limpiar_pendientes_servidor(cliente_id):
    cfg = cargar_json(CONFIG_FILE) or {}
    url = f"{build_base_url(cfg)}/api/limpiar_pendientes.php"
    payload = {
        "id": cliente_id,
        "secret_key": get_secret_from_cfg(cfg)
    }
    try:
        response = requests.post(url, json=payload, timeout=HTTP_TIMEOUT)
        if response.status_code == 200:
            log.info(
                f"Servidor confirmó limpieza de pendientes para cliente {cliente_id}")
        else:
            log.warning(
                f"No se pudo limpiar pendientes del servidor: {response.text}")
    except Exception as e:
        log.error(
            f"Error notificando al servidor para limpiar pendientes: {e}")


def procesar_todas_pendientes():
    try:
        data = cargar_json(DATA_CLIENTE_FILE) or {}
        pendientes = data.get("pending", [])
        if not pendientes:
            return 0
        log.info(f"Procesando {len(pendientes)} acciones pendientes")
        exitosas = 0
        cliente_id = data.get("id")
        for accion in list(pendientes):
            if procesar_accion_pendiente(accion):
                exitosas += 1
                pendientes.remove(accion)
        data["pending"] = pendientes
        guardar_json(DATA_CLIENTE_FILE, data)
        if cliente_id and exitosas > 0:
            limpiar_pendientes_servidor(cliente_id)
        log.info(f"Procesadas {exitosas} acciones pendientes")
        return exitosas
    except Exception as e:
        log.error(f"Error procesando acciones pendientes: {e}")
        return 0


def descargar_wallpaper(url: str, destino: str):
    tmp_fd, tmp_path = tempfile.mkstemp(prefix="wp_", suffix=".img")
    os.close(tmp_fd)
    try:
        urllib.request.urlretrieve(url, tmp_path)
        shutil.move(tmp_path, destino)
        return True
    except Exception as e:
        log.error(f"Descarga de wallpaper falló: {e}")
        try:
            os.remove(tmp_path)
        except Exception:
            pass
        return False

# =============================
# HTTP CLIENT (envío al servidor)
# =============================


def build_base_url(cfg: dict) -> str:
    ip = cfg.get('server_ip', '127.0.0.1')
    port = str(cfg.get('server_port', '80'))
    folder = (cfg.get('server_folder') or '').strip('/')
    base = f"http://{ip}:{port}"
    return f"{base}/{folder}" if folder else base


def fetch_and_store_secret(cfg: dict, provision_pass: str) -> bool:
    try:
        data_cliente = cargar_json(
            DATA_CLIENTE_FILE) or load_or_create_data_cliente()
        client_id = data_cliente.get("id") or "unknown"
        url = build_base_url(cfg) + "/api/provision_secret.php"
        payload = {"provision_password": provision_pass,
                   "client_id": client_id}
        r = requests.post(url, json=payload, timeout=HTTP_TIMEOUT)
        if r.status_code != 200:
            log.error(f"Provision HTTP {r.status_code}: {r.text}")
            return False
        resp = r.json()
        if not (resp.get("ok") or resp.get("success")) or not resp.get("secret_key"):
            log.error(f"Provision respuesta inválida: {resp}")
            return False
        secret = resp["secret_key"]
        cfg_local = cargar_json(CONFIG_FILE) or {}
        cfg_local.pop("secret_key", None)
        cfg_local["secret_key_dpapi"] = dpapi_encrypt(secret)
        guardar_json(CONFIG_FILE, cfg_local)
        log.info("Secret key recibida y guardada cifrada.")
        return True
    except Exception as e:
        log.error(f"Error al provisionar secret: {e}")
        return False


def enviar_al_servidor(cfg: dict, data: dict, endpoint: str = "/api/recibir.php"):
    url = build_base_url(cfg) + endpoint
    headers = {
        "Content-Type": "application/json",
        "User-Agent": f"{APP_NAME}/1.0 ({socket.gethostname()})"
    }
    payload = json.dumps(data).encode("utf-8")
    req = urllib.request.Request(
        url, data=payload, headers=headers, method="POST")
    log.debug(f"POST -> {url}")
    try:
        with urllib.request.urlopen(req, timeout=HTTP_TIMEOUT) as resp:
            resp_text = resp.read().decode("utf-8")
            return True, resp_text
    except Exception as e:
        return False, str(e)


def test_server_connection(cfg: dict) -> bool:
    secret = get_secret_from_cfg(cfg)
    ok, resp = enviar_al_servidor(cfg, {"ping": True, "secret_key": secret})
    if not ok:
        log.warning(f"Ping falló: {resp}")
    return ok

# =============================
# WEBHOOK (servidor local)
# =============================


class WebhookHandler(BaseHTTPRequestHandler):
    server_version = "WallpaperCliente/1.1"

    def log_message(self, format, *args):
        log.info("%s - - [%s] %s" % (self.address_string(),
                 self.log_date_time_string(), format % args))

    def _json_response(self, status: int, data: dict):
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.end_headers()
        self.wfile.write(json.dumps(data).encode("utf-8"))

    def do_POST(self):
        content_length = int(self.headers.get("Content-Length", 0))
        if content_length == 0:
            self._json_response(400, {"ok": False, "error": "Empty request"})
            return
        try:
            body = self.rfile.read(content_length)
            payload = json.loads(body)
            log.debug(f"Webhook payload: {payload}")
        except Exception as e:
            log.error(f"JSON inválido en webhook: {e}")
            self._json_response(400, {"ok": False, "error": "Invalid JSON"})
            return
        cfg = cargar_json(CONFIG_FILE) or {}
        secret = get_secret_from_cfg(cfg)
        if payload.get("secret_key") != secret:
            log.warning("Webhook con secret_key inválida")
            self._json_response(
                403, {"ok": False, "error": "Invalid secret_key"})
            return
        action = payload.get("action")
        result = {"ok": False, "action": action}
        try:
            ejecutado_directamente = False
            if action == "set_wallpaper":
                style = payload.get("style", "fill")
                image_name = payload.get("image_name")
                if not image_name:
                    result["error"] = "No image name provided"
                else:
                    cfg2 = cargar_json(CONFIG_FILE) or {}
                    base = build_base_url(cfg2)
                    url = f"{base}/fondos/{image_name}"
                    log.info(f"Descargando wallpaper: {url}")
                    if descargar_wallpaper(url, WALLPAPER_FILE):
                        set_wallpaper(WALLPAPER_FILE, style=style)
                        result["ok"] = True
                        ejecutado_directamente = True
                    else:
                        result["error"] = "No se pudo descargar el wallpaper"
            elif action == "lock":
                lock_wallpaper_option()
                result["ok"] = True
                ejecutado_directamente = True
            elif action == "unlock":
                unlock_wallpaper_option()
                result["ok"] = True
                ejecutado_directamente = True
            elif action == "ping":
                result["ok"] = True
                ejecutado_directamente = True
            else:
                result["error"] = f"Acción desconocida: {action}"
            if not ejecutado_directamente and result.get("error"):
                accion_pendiente = payload.copy()
                if agregar_accion_pendiente(accion_pendiente):
                    result["pending"] = True
                    result["message"] = "Acción añadida a pendientes"
                    result["ok"] = True
        except Exception as e:
            log.error(f"Error procesando acción '{action}': {e}")
            result["error"] = str(e)
        update_data_cliente(action_performed=action)
        self._json_response(200, result)


def start_webhook_server(port=DEFAULT_PORT):
    global webhook_server
    try:
        # Si quieres no exponer en red, cambia a ("127.0.0.1", port)
        webhook_server = ThreadingHTTPServer(("0.0.0.0", port), WebhookHandler)
        thread = threading.Thread(
            target=webhook_server.serve_forever, daemon=True)
        thread.start()
        log.info(f"Webhook server iniciado en puerto {port}")
        return webhook_server
    except Exception as e:
        log.error(f"No se pudo iniciar webhook server: {e}")
        raise

# =============================
# DATOS DEL CLIENTE / CONFIG
# =============================


def get_serial_number():
    try:
        result = subprocess.check_output(
            ["wmic", "bios", "get", "serialnumber"],
            shell=True, text=True, stderr=subprocess.STDOUT, timeout=5,
        )
        lines = [line.strip()
                 for line in result.strip().splitlines() if line.strip()]
        if len(lines) >= 2 and lines[1] and lines[1].upper() not in ["NULL", "TO BE FILLED BY O.E.M.", "N/A"]:
            return lines[1]
    except Exception as e:
        log.debug(f"WMIC falló: {e}")
    try:
        ps_cmd = "Get-CimInstance Win32_BIOS | Select-Object -ExpandProperty SerialNumber"
        result = subprocess.check_output(
            ["powershell", "-Command", ps_cmd],
            shell=True, text=True, stderr=subprocess.STDOUT, timeout=5,
        ).strip()
        if result and result.upper() not in ["NULL", "TO BE FILLED BY O.E.M.", "N/A"]:
            return result
    except Exception as e:
        log.debug(f"PowerShell falló: {e}")
    log.warning("No se pudo obtener número de serie BIOS. Usando 'DESCONOCIDO'")
    return "DESCONOCIDO"


def chile_now_ampm():
    ahora = datetime.now(CHILE_TZ)
    offset = ahora.strftime("%z")
    offset_formatted = offset[:-2]
    return ahora.strftime(f"%Y-%m-%d %I:%M:%S %p {offset_formatted}")


def load_or_create_data_cliente():
    ensure_app_folder()
    data = cargar_json(DATA_CLIENTE_FILE)
    username = obtener_nombre()
    serie = get_serial_number()
    mac = obtener_mac()
    if data and data.get("id"):
        data.update({
            "username": username,
            "ip": obtener_ip_local(),
            "mac": mac,
            "serie": data.get("serie") or serie,
            "last_seen": chile_now_ampm(),
        })
    else:
        client_id = crear_id_cliente(username, mac)
        data = {
            "id": client_id,
            "username": username,
            "serie": serie,
            "ip": obtener_ip_local(),
            "mac": mac,
            "locked": False,
            "last_seen": chile_now_ampm(),
            "pending": [],
        }
    guardar_json(DATA_CLIENTE_FILE, data)
    return data


def update_data_cliente(action_performed=None):
    data = cargar_json(DATA_CLIENTE_FILE) or {}
    data["ip"] = obtener_ip_local()
    data["last_seen"] = chile_now_ampm()
    if action_performed == "lock":
        data["locked"] = True
    elif action_performed == "unlock":
        data["locked"] = False
    guardar_json(DATA_CLIENTE_FILE, data)
    cfg = cargar_json(CONFIG_FILE)
    if cfg:
        client_copy = dict(data)
        client_copy.pop("pending", None)
        secret = get_secret_from_cfg(cfg)
        payload = {"client": client_copy, "secret_key": secret}
        ok, resp = enviar_al_servidor(cfg, payload)
        if not ok:
            log.error(f"No se pudo enviar actualización al servidor: {resp}")


def sync_config_with_server():
    cfg = cargar_json(CONFIG_FILE)
    if not cfg:
        log.warning("No hay configuración para sincronizar")
        return
    data = cargar_json(DATA_CLIENTE_FILE) or {}
    secret = get_secret_from_cfg(cfg)
    payload = {"query_config": True,
               "id": data.get("id"), "secret_key": secret}
    ok, resp = enviar_al_servidor(cfg, payload)
    if not ok:
        log.error(f"Error consultando config remota: {resp}")
        return
    try:
        r = json.loads(resp)
        if r.get("config") and r["config"] != cfg:
            log.info("Configuración remota diferente. Actualizando...")
            guardar_json(BACKUP_CONFIG_FILE, cfg)
            guardar_json(CONFIG_FILE, r["config"])
    except Exception as e:
        log.error(f"Error procesando respuesta de configuración: {e}")

# =============================
# BUCLE PRINCIPAL
# =============================


def obtener_pendientes_servidor(cliente_id):
    cfg = cargar_json(CONFIG_FILE) or {}
    base_url = build_base_url(cfg)
    url = f"{base_url}/api/obtener_pendientes.php"
    payload = {"id": cliente_id, "secret_key": get_secret_from_cfg(cfg)}
    for intento in range(3):
        try:
            response = requests.post(url, json=payload, timeout=HTTP_TIMEOUT)
            if response.status_code == 200:
                data = response.json()
                if data.get("success"):
                    pendientes = data.get("pending", [])
                    log.info(f"Pendientes del servidor: {len(pendientes)}")
                    return pendientes
            log.warning(
                f"Resp inesperada ({response.status_code}): {response.text}")
        except Exception as e:
            log.warning(f"Intento {intento+1}/3 obtener pendientes: {e}")
        time.sleep(1.5 * (intento + 1))
    return []


def main_loop_background():
    log.info("Iniciando bucle en segundo plano")
    ultima_conexion_exitosa = False
    intentos_consecutivos = 0
    while True:
        try:
            update_data_cliente()
            cfg = cargar_json(CONFIG_FILE)
            if cfg and test_server_connection(cfg):
                if not ultima_conexion_exitosa:
                    log.info("✓ Conexión al servidor restablecida")
                cliente_data = cargar_json(DATA_CLIENTE_FILE) or {}
                cliente_id = cliente_data.get("id")
                if cliente_id:
                    pendientes_servidor = obtener_pendientes_servidor(
                        cliente_id)
                    for accion in pendientes_servidor:
                        agregar_accion_pendiente(accion)
                acciones_procesadas = procesar_todas_pendientes()
                if acciones_procesadas > 0:
                    log.info(
                        f"✓ {acciones_procesadas} acciones pendientes procesadas")
                try:
                    sync_config_with_server()
                except Exception as e:
                    log.warning(f"Error sincronizando configuración: {e}")
                ultima_conexion_exitosa = True
                intentos_consecutivos = 0
                time.sleep(30)
            else:
                if ultima_conexion_exitosa or intentos_consecutivos == 0:
                    log.warning(
                        "✗ Sin conexión al servidor. Las acciones se guardarán como pendientes.")
                ultima_conexion_exitosa = False
                intentos_consecutivos += 1
                espera = min(300, 30 * intentos_consecutivos)
                time.sleep(espera)
        except Exception as e:
            log.error(f"Error en bucle background: {e}")
            time.sleep(60)

# =============================
# GUI PRIMERA EJECUCIÓN
# =============================


def first_run_gui():
    log.info("Mostrando GUI de configuración inicial")
    root = tk.Tk()
    root.title("Configurar Cliente Wallpaper")
    root.geometry("460x360")
    root.resizable(False, False)
    fuente_normal = ("Segoe UI", 10)
    fuente_titulo = ("Segoe UI", 12, "bold")
    frm = ttk.Frame(root, padding=15)
    frm.pack(fill="both", expand=True)
    ttk.Label(frm, text="Configuración inicial", font=fuente_titulo).grid(
        column=0, row=0, columnspan=3, pady=(0, 10), sticky="w")
    ttk.Label(frm, text="IP del servidor:", font=fuente_normal).grid(
        column=0, row=1, sticky="w", pady=5)
    e_ip = ttk.Entry(frm, width=28, font=fuente_normal)
    e_ip.grid(column=1, row=1, columnspan=2, pady=5, sticky="w")
    ttk.Label(frm, text="Puerto:", font=fuente_normal).grid(
        column=0, row=2, sticky="w", pady=5)
    e_port = ttk.Entry(frm, width=28, font=fuente_normal)
    e_port.grid(column=1, row=2, columnspan=2, pady=5, sticky="w")
    e_port.insert(0, "80")
    ttk.Label(frm, text="Carpeta del servidor:", font=fuente_normal).grid(
        column=0, row=3, sticky="w", pady=5)
    e_folder = ttk.Entry(frm, width=28, font=fuente_normal)
    e_folder.grid(column=1, row=3, columnspan=2, pady=5, sticky="w")
    e_folder.insert(0, "wallpaper-server")
    ttk.Label(frm, text="Contraseña de aprovisionamiento:",
              font=fuente_normal).grid(column=0, row=4, sticky="w", pady=5)
    e_prov = ttk.Entry(frm, width=28, font=fuente_normal, show="•")
    e_prov.grid(column=1, row=4, pady=5, sticky="w")

    def toggle_secret():
        e_prov.config(show="" if chk_show_var.get() else "•")
    chk_show_var = tk.BooleanVar(value=False)
    ttk.Checkbutton(frm, text="Mostrar", variable=chk_show_var,
                    command=toggle_secret).grid(column=2, row=4, sticky="w", padx=6)

    status_label = ttk.Label(
        frm, text="", foreground="#0055aa", font=fuente_normal)
    status_label.grid(column=0, row=6, columnspan=3, pady=(8, 4), sticky="w")


def first_run_gui():
    log.info("Mostrando GUI de configuración inicial")
    root = tk.Tk()
    root.title("Configurar Cliente Wallpaper")
    root.geometry("460x360")
    root.resizable(False, False)

    fuente_normal = ("Segoe UI", 10)
    fuente_titulo = ("Segoe UI", 12, "bold")

    frm = ttk.Frame(root, padding=15)
    frm.pack(fill="both", expand=True)

    ttk.Label(frm, text="Configuración inicial", font=fuente_titulo)\
        .grid(column=0, row=0, columnspan=3, pady=(0, 10), sticky="w")

    ttk.Label(frm, text="IP del servidor:", font=fuente_normal)\
        .grid(column=0, row=1, sticky="w", pady=5)
    e_ip = ttk.Entry(frm, width=28, font=fuente_normal)
    e_ip.grid(column=1, row=1, columnspan=2, pady=5, sticky="w")

    ttk.Label(frm, text="Puerto:", font=fuente_normal)\
        .grid(column=0, row=2, sticky="w", pady=5)
    e_port = ttk.Entry(frm, width=28, font=fuente_normal)
    e_port.grid(column=1, row=2, columnspan=2, pady=5, sticky="w")
    e_port.insert(0, "80")

    ttk.Label(frm, text="Carpeta del servidor:", font=fuente_normal)\
        .grid(column=0, row=3, sticky="w", pady=5)
    e_folder = ttk.Entry(frm, width=28, font=fuente_normal)
    e_folder.grid(column=1, row=3, columnspan=2, pady=5, sticky="w")
    e_folder.insert(0, "wallpaper-server")

    ttk.Label(frm, text="Contraseña de aprovisionamiento:", font=fuente_normal)\
        .grid(column=0, row=4, sticky="w", pady=5)
    e_prov = ttk.Entry(frm, width=28, font=fuente_normal, show="•")
    e_prov.grid(column=1, row=4, pady=5, sticky="w")

    def toggle_secret():
        e_prov.config(show="" if chk_show_var.get() else "•")

    chk_show_var = tk.BooleanVar(value=False)
    ttk.Checkbutton(frm, text="Mostrar", variable=chk_show_var, command=toggle_secret)\
        .grid(column=2, row=4, sticky="w", padx=6)

    status_label = ttk.Label(
        frm, text="", foreground="#0055aa", font=fuente_normal)
    status_label.grid(column=0, row=6, columnspan=3, pady=(8, 4), sticky="w")

    # --- Funciones internas que usan los widgets de arriba ---

    def probar_conexion():
        ip = e_ip.get().strip()
        port = e_port.get().strip()
        folder = e_folder.get().strip().strip("/")
        if not ip or not port or not folder:
            messagebox.showwarning(
                "Faltan datos", "Completa IP, puerto y carpeta.")
            return

        url = f"http://{ip}:{port}/{folder}/"
        log.info(f"Probando conexión con servidor: {url}")

        # Evitar seguir redirecciones para detectar HTTPS
        class NoRedirect(urllib.request.HTTPRedirectHandler):
            def redirect_request(self, req, fp, code, msg, hdrs, newurl):
                raise urllib.error.HTTPError(req.full_url, code, msg, hdrs, fp)

        opener = urllib.request.build_opener(NoRedirect)

        # 1) HEAD
        try:
            req = urllib.request.Request(url, method="HEAD")
            try:
                with opener.open(req, timeout=5) as resp:
                    status = getattr(resp, "status", resp.getcode())
            except urllib.error.HTTPError as e:
                if e.code in (301, 302, 307, 308):
                    loc = e.headers.get("Location", "")
                    if loc.startswith("https://"):
                        log.warning(f"Redirección a HTTPS: {loc}")
                        status_label.config(
                            text="El servidor redirige a HTTPS. Desactiva la redirección o configura HTTPS con certificado.",
                            foreground="red"
                        )
                        return
                status = e.code

            if status == 200:
                status_label.config(
                    text=f"Conexión OK: {url}", foreground="green")
            else:
                status_label.config(
                    text=f"HTTP {status} (el servidor respondió)", foreground="orange")
            return

        except Exception as e:
            log.warning(f"HEAD falló, probando GET: {e}")

        # 2) GET (fallback)
        try:
            req = urllib.request.Request(url, method="GET")
            try:
                with opener.open(req, timeout=5) as resp:
                    status = getattr(resp, "status", resp.getcode())
            except urllib.error.HTTPError as e:
                if e.code in (301, 302, 307, 308):
                    loc = e.headers.get("Location", "")
                    if loc.startswith("https://"):
                        log.warning(f"Redirección a HTTPS: {loc}")
                        status_label.config(
                            text="El servidor redirige a HTTPS (requiere certificado).",
                            foreground="red"
                        )
                        return
                status = e.code

            if status == 200:
                status_label.config(
                    text=f"Conexión OK (GET): {url}", foreground="green")
            else:
                status_label.config(
                    text=f"HTTP {status} (GET)", foreground="orange")

        except Exception as e:
            msg = f"Error de conexión: {e}"
            log.error(msg)
            status_label.config(text=msg, foreground="red")

    def aceptar():
        ip = e_ip.get().strip()
        port = e_port.get().strip()
        folder = e_folder.get().strip()
        prov = e_prov.get().strip()

        if not ip or not port or not folder:
            messagebox.showwarning(
                "Faltan datos", "Completa IP, puerto y carpeta.")
            return

        cfg = {"server_ip": ip, "server_port": port, "server_folder": folder}
        guardar_json(CONFIG_FILE, cfg)

        if prov:
            status_label.config(
                text="Aprovisionando clave...", foreground="#0055aa")
            root.update_idletasks()
            sk = provision_secret(ip, port, folder, prov)
            if sk:
                cfg_local = cargar_json(CONFIG_FILE) or {}
                cfg_local.pop("secret_key", None)
                cfg_local["secret_key_dpapi"] = dpapi_encrypt(sk)
                guardar_json(CONFIG_FILE, cfg_local)
                status_label.config(
                    text="Clave aprovisionada y guardada.", foreground="green")
                log.info("Secret key aprovisionada correctamente")
                root.after(300, root.destroy)
                return
            else:
                status_label.config(
                    text="No se pudo aprovisionar la clave (revisa la contraseña).",
                    foreground="red"
                )
                log.warning("Aprovisionamiento fallido")
                return

        messagebox.showwarning(
            "Falta secret",
            "Ingresa la contraseña de aprovisionamiento para obtener la clave del servidor."
        )

    def cancelar():
        root.destroy()
        sys.exit(0)

    frm_botones = ttk.Frame(frm)
    frm_botones.grid(column=0, row=7, columnspan=3, pady=(10, 0), sticky="e")
    ttk.Button(frm_botones, text="Probar conexión", command=probar_conexion)\
        .pack(side="left", padx=5)
    ttk.Button(frm_botones, text="Aceptar", command=aceptar)\
        .pack(side="left", padx=5)
    ttk.Button(frm_botones, text="Cancelar", command=cancelar)\
        .pack(side="left", padx=5)

    root.mainloop()


# Aprovisionamiento (cliente) usando el nombre de campo correcto del server


def provision_secret(ip: str, port: str, folder: str, provision_password: str) -> str | None:
    try:
        folder = folder.strip("/")
        url = f"http://{ip}:{port}/{folder}/api/provision_secret.php"
        data_cliente = cargar_json(
            DATA_CLIENTE_FILE) or load_or_create_data_cliente()
        client_id = data_cliente.get("id") or "unknown"
        payload = {"provision_password": provision_password,
                   "client_id": client_id}
        data = json.dumps(payload).encode("utf-8")
        req = urllib.request.Request(url, data=data, method="POST", headers={
                                     "Content-Type": "application/json"})
        with urllib.request.urlopen(req, timeout=HTTP_TIMEOUT) as resp:
            body = resp.read().decode("utf-8")
            try:
                obj = json.loads(body)
            except json.JSONDecodeError:
                log.error(f"Respuesta no-JSON al aprovisionar: {body[:200]}")
                return None
            if (obj.get("success") is True or obj.get("ok") is True) and obj.get("secret_key"):
                return obj["secret_key"]
            log.error(f"Aprovisionamiento rechazado: {obj}")
            return None
    except urllib.error.HTTPError as e:
        try:
            err_body = e.read().decode("utf-8", "ignore")
        except Exception:
            err_body = "<sin cuerpo>"
        log.error(f"Provision HTTPError {e.code}: {err_body}")
        return None
    except Exception as e:
        log.error(f"Error aprovisionando secret_key: {e}")
        return None

# =============================
# MAIN
# =============================


def load_or_ask_config():
    ensure_app_folder()
    cfg = cargar_json(CONFIG_FILE)
    if not cfg:
        backup = cargar_json(BACKUP_CONFIG_FILE)
        if backup:
            log.info("Usando configuración de respaldo")
            guardar_json(CONFIG_FILE, backup)
            return backup
        log.info("No hay configuración. Mostrando GUI inicial")
        first_run_gui()
        cfg = cargar_json(CONFIG_FILE)
    return cfg


def validate_server_and_maybe_restore():
    cfg = cargar_json(CONFIG_FILE)
    if not cfg:
        return None
    if test_server_connection(cfg):
        return cfg
    backup = cargar_json(BACKUP_CONFIG_FILE)
    if backup and test_server_connection(backup):
        guardar_json(CONFIG_FILE, backup)
        return backup
    log.error("No se pudo conectar. Mostrando GUI...")
    first_run_gui()
    return cargar_json(CONFIG_FILE)


def main():
    global webhook_server
    log.info("Iniciando cliente wallpaper con sistema de pendientes")
    log.info(f"Python: {sys.version}")
    log.info(f"App folder: {APP_FOLDER}")
    try:
        ensure_app_folder()
        cfg = load_or_ask_config()
        if not cfg:
            log.error("No se pudo obtener configuración. Saliendo...")
            sys.exit(1)
        sec = get_secret_from_cfg(cfg)
        if not sec:
            log.info(
                "No hay secret_key en config; se intentará provisionar desde servidor.")
            first_run_gui()
            cfg = cargar_json(CONFIG_FILE) or {}
            sec = get_secret_from_cfg(cfg)
            if not sec:
                log.error("No se pudo obtener una secret_key válida. Saliendo.")
                sys.exit(1)
        # Validar conexión
        cfg = validate_server_and_maybe_restore()
        if not cfg:
            log.error("Sin configuración válida. Saliendo...")
            sys.exit(1)
        # Crear regla firewall (ignorar error si no hay permisos)
        try:
            crear_regla_firewall(DEFAULT_PORT)
        except Exception as e:
            log.warning(f"No se pudo crear regla firewall: {e}")
        # Datos del cliente
        data = load_or_create_data_cliente()
        # Mostrar estado de pendientes al iniciar
        pendientes = data.get("pending", [])
        if pendientes:
            log.info(f"📋 {len(pendientes)} acciones pendientes al iniciar")
            for accion in pendientes[:3]:
                log.info(
                    f"   - {accion.get('action')} ({accion.get('timestamp', 'sin timestamp')})")
            if len(pendientes) > 3:
                log.info(f"   - ... y {len(pendientes) - 3} más")
        # Sincronizar configuración remota (si hay conexión)
        try:
            sync_config_with_server()
        except Exception as e:
            log.warning(f"No se pudo sincronizar configuración: {e}")
        # Iniciar webhook
        try:
            webhook_server = start_webhook_server(DEFAULT_PORT)
        except Exception:
            log.error("Fallo crítico al iniciar webhook. Abortando.")
            sys.exit(1)
        # Estado inicial
        update_data_cliente(action_performed="startup")
        # Hilo background
        threading.Thread(target=main_loop_background, daemon=True).start()
        log.info("✅ Cliente iniciado correctamente")
        log.info(f"🌐 Webhook escuchando en puerto: {DEFAULT_PORT}")
        log.info(f"⚙️  Configuración: {cfg}")
        log.info("💾 Sistema de acciones pendientes: ACTIVADO")
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        log.info("Interrupción por teclado. Cerrando...")
        try:
            if webhook_server:
                webhook_server.shutdown()
        except Exception as e:
            log.error(f"Error cerrando servidor webhook: {e}")
        sys.exit(0)
    except Exception as e:
        log.error(f"Error fatal: {e}", exc_info=True)
        sys.exit(1)


if __name__ == "__main__":
    main()
