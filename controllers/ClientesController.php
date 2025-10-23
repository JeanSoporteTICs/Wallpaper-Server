<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

class ClientesController {
    private string $clientesDir;
    private string $ubicacionesDir;

    public function __construct() {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        ini_set('error_log', LOGS_DIR . '/php_errors.log');
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        // Inicializar rutas
        $this->clientesDir = CLIENTS_DIR . '/';
        $this->ubicacionesDir = DATA_DIR . '/ubicaciones/';
    }

    public function obtenerNombreAccion($accion) {
        $acciones = [
            'set_wallpaper' => 'Cambiar fondo de pantalla',
            'lock'          => 'Bloquear cambios de fondo',
            'unlock'        => 'Desbloquear cambios de fondo',
        ];
        return $acciones[$accion] ?? $accion;
    }

    public function cargarClientes(): array {
        $clientes = [];
        $errorCarga = '';

        $archivos = glob($this->clientesDir . '*.json') ?: [];
        foreach ($archivos as $file) {
            try {
                $data = json_decode((string)file_get_contents($file), true);
                if (!is_array($data)) continue;

                $id = $data['id'] ?? pathinfo($file, PATHINFO_FILENAME);

                // Ubicación
                $ubicacionArchivo = $this->ubicacionesDir . $id . '.json';
                $edificio = $servicio = $oficina =  $responsable ='';

                if (is_file($ubicacionArchivo)) {
                    $ubicacionData = json_decode((string)file_get_contents($ubicacionArchivo), true) ?: [];
                    $edificio = $ubicacionData['edificio'] ?? '';
                    $servicio = $ubicacionData['servicio'] ?? '';
                    $oficina  = $ubicacionData['oficina']  ?? '';
                    $responsable  = $ubicacionData['responsable']  ?? '';
                }

                // --- DATOS DE HARDWARE ---
                $hardwareRaw = $data['hardware'] ?? '[]';
                $hardware = [];
                if (is_string($hardwareRaw)) {
                    $decoded = json_decode($hardwareRaw, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $hardware = $decoded;
                    }
                } elseif (is_array($hardwareRaw)) {
                    $hardware = $hardwareRaw;
                }

                // --- DATOS DE RED ---
                $red = $data['red'] ?? [];
                $mascara = $red['mascara'] ?? 'N/A';
                $gateway = $red['gateway'] ?? 'N/A';
                $dns = is_array($red['dns'] ?? null) ? implode(', ', $red['dns']) : 'N/A';
                $proxy_activo = ($red['proxy']['activo'] ?? false) ? 'Sí' : 'No';
                $proxy_servidor = $red['proxy']['servidor'] ?? 'N/A';

                $clientes[$id] = [
                    'nombre'            => $data['username'] ?? 'N/A',
                    'numero_serie'      => $data['serie'] ?? 'N/A',
                    'ip'                => $data['ip'] ?? 'N/A',
                    'mac'               => $data['mac'] ?? 'N/A',
                    'so'                => $data['so'] ?? 'N/A',
                    'ultima_conexion'   => $data['last_seen'] ?? 'Sin registro',
                    'pending'           => $data['pending'] ?? [],
                    'bloqueado'         => (!empty($data['locked']) ? 'bloqueado' : 'desbloqueado'),
                    'edificio'          => $edificio,
                    'servicio'          => $servicio,
                    'oficina'           => $oficina,
                    'responsable'       => $responsable,
                    'mascara'           => $mascara,
                    'gateway'           => $gateway,
                    'dns'               => $dns,
                    'proxy_activo'      => $proxy_activo,
                    'proxy_servidor'    => $proxy_servidor,
                    'proxy_exclusiones' => $red['proxy']['exclusiones'] ?? 'N/A',
                    'impresoras'        => $data['impresoras'] ?? null,
                    'hardware'          => $hardware,
                ];
            } catch (Throwable $e) {
                error_log("Error cargando cliente $file: " . $e->getMessage());
            }
        }

        return ['clientes' => $clientes, 'error' => $errorCarga];
    }

    public function obtenerMensajesFlash(): array {
        $resultado_envio = $_SESSION['resultado_envio'] ?? null;
        unset($_SESSION['resultado_envio']);

        $resultados = $_SESSION['resultados_envio'] ?? null;
        unset($_SESSION['resultados_envio']);

        $error = $_SESSION['error'] ?? null;
        unset($_SESSION['error']);

        $success = $_SESSION['success'] ?? null;
        unset($_SESSION['success']);

        return compact('resultado_envio', 'resultados', 'error', 'success');
    }

    public function obtenerCsrfToken(): string {
        return (string)($_SESSION['csrf_token'] ?? '');
    }

    public function eliminarCliente(string $id): array {
        $id = trim($id);
        if ($id === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $id)) {
            return ['success' => false, 'message' => 'ID inválido'];
        }

        $clienteFile = $this->clientesDir . $id . '.json';
        $ubicacionFile = $this->ubicacionesDir . $id . '.json';

        if (!file_exists($clienteFile)) {
            return ['success' => false, 'message' => 'Cliente no encontrado'];
        }

        $okCliente = @unlink($clienteFile);
        $okUbi = true;
        if (file_exists($ubicacionFile)) {
            $okUbi = @unlink($ubicacionFile);
        }

        if (!$okCliente) {
            return ['success' => false, 'message' => 'No se pudo eliminar el archivo del cliente'];
        }

        return [
            'success' => true,
            'message' => 'Cliente eliminado correctamente' . ($okUbi ? ' (ubicación eliminada)' : '')
        ];
    }
}