<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config.php';

// api/enviar.php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', LOGS_DIR . '/php_errors.log');

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
  json_out(['error' => 'Token CSRF inválido'], 403);
}

// --- helpers ---
function validarImagenSubida(array $archivo): array {
  $extensionesPermitidas = ['jpg','jpeg','png','gif','bmp','webp'];
  $maxFileSize = 10 * 1024 * 1024;

  if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    return ['error' => 'Error en la subida del archivo'];
  }
  if (($archivo['size'] ?? 0) > $maxFileSize) {
    return ['error' => 'Archivo demasiado grande'];
  }
  $extension = strtolower(pathinfo($archivo['name'] ?? '', PATHINFO_EXTENSION));
  if (!in_array($extension, $extensionesPermitidas, true)) {
    return ['error' => 'Tipo de archivo no permitido'];
  }
  $imageInfo = @getimagesize($archivo['tmp_name'] ?? '');
  if (!$imageInfo) return ['error' => 'Archivo no es una imagen válida'];
  return ['extension' => $extension];
}

function agregarComandoPendiente(string $accion, string $clienteId, ?string $archivoFondo = null): ?string {
  $clienteFile = CLIENTS_DIR . '/' . $clienteId . '.json';
  if (!is_file($clienteFile)) return null;

  $data = json_decode((string)file_get_contents($clienteFile), true);
  if (!is_array($data)) $data = [];
  if (!isset($data['pending']) || !is_array($data['pending'])) $data['pending'] = [];

  $cmdId = uniqid('cmd_', true);
  $comando = [
    'comando_id' => $cmdId,
    'action'     => $accion,
    'estado'     => 'pendiente',
    'timestamp'  => date('c'),
  ];
  if ($accion === 'set_wallpaper' && $archivoFondo) {
    $comando['image_name'] = $archivoFondo;
    $comando['style']      = $_POST['estilo'] ?? 'fill';
  }

  $data['pending'][] = $comando;
  file_put_contents($clienteFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
  return $cmdId;
}

// --- Validación ---
$accion = $_POST['accion'] ?? '';
$clientesSeleccionados = $_POST['clientes'] ?? [];

if (!$accion || !is_array($clientesSeleccionados) || empty($clientesSeleccionados)) {
  json_out(['error' => 'Acción o clientes no especificados'], 400);
}

// Procesar archivo si aplica
$nombreArchivo = '';
if ($accion === 'set_wallpaper') {
  if (empty($_FILES['fondo'])) json_out(['error' => 'No se recibió archivo de imagen'], 400);
  $validacion = validarImagenSubida($_FILES['fondo']);
  if (!empty($validacion['error'])) json_out(['error' => $validacion['error']], 400);
  $nombreArchivo = 'wallpaper_' . uniqid('', true) . '.' . $validacion['extension'];
  if (!@move_uploaded_file($_FILES['fondo']['tmp_name'], FONDOS_DIR . '/' . $nombreArchivo)) {
    json_out(['error' => 'Error moviendo archivo'], 500);
  }
}

// Validación específica para show_message (sin colas)
$title = '';
$message = '';
$timeoutSeconds = null;
if ($accion === 'show_message') {
  $title = trim((string)($_POST['title'] ?? ''));
  $message = trim((string)($_POST['message'] ?? ''));
  $t = isset($_POST['timeout_seconds']) ? (int)$_POST['timeout_seconds'] : 0;

  if ($title === '' && $message === '') {
    json_out(['error' => 'Debe indicar al menos Título o Mensaje'], 400);
  }
  if (mb_strlen($title) > 120) {
    json_out(['error' => 'El título no puede superar 120 caracteres'], 400);
  }
  if (mb_strlen($message) > 4000) {
    json_out(['error' => 'El mensaje no puede superar 4000 caracteres'], 400);
  }
  if ($t > 0) {
    if ($t < 1 || $t > 3600) json_out(['error' => 'El cierre automático debe estar entre 1 y 3600 segundos'], 400);
    $timeoutSeconds = $t;
  }
}

$resultados = [];
$clientesEnPending = [];

foreach ($clientesSeleccionados as $clienteId) {
  $clienteId = (string)$clienteId;
  $clienteFile = CLIENTS_DIR . '/' . $clienteId . '.json';

  if (!is_file($clienteFile)) {
    $resultados[$clienteId] = ['estado' => 'fallo', 'error' => 'Cliente no encontrado'];
    $clientesEnPending[] = $clienteId;
    continue;
  }

  $clienteData = json_decode((string)file_get_contents($clienteFile), true) ?? [];
  $ip = $clienteData['ip'] ?? null;
  $webhookUrl = $ip ? "http://{$ip}:8584/" : null;

  $webhookEnviado = false;
  if ($webhookUrl) {
    $payload = ['action' => $accion, 'secret_key' => SECRET_KEY];

    if ($accion === 'set_wallpaper') {
      $payload['image_name'] = $nombreArchivo;
      $payload['style']      = $_POST['estilo'] ?? 'fill';
    } elseif ($accion === 'show_message') {
      if ($title !== '')   $payload['title'] = $title;
      if ($message !== '') $payload['message'] = $message;
      if ($timeoutSeconds !== null) $payload['timeout_seconds'] = $timeoutSeconds;
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL            => $webhookUrl,
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
      CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CONNECTTIMEOUT => 2,
      CURLOPT_TIMEOUT        => 3,
    ]);
    curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http >= 200 && $http < 300) $webhookEnviado = true;
  }

  if ($webhookEnviado) {
    $resultados[$clienteId] = ['estado' => 'exito_webhook'];
  } else {
    // IMPORTANTE: show_message NO SE ENCOLA
    if ($accion === 'show_message') {
      $resultados[$clienteId] = ['estado' => 'fallo', 'error' => 'No entregado por webhook'];
    } else {
      $cmdId = agregarComandoPendiente($accion, $clienteId, $nombreArchivo);
      $resultados[$clienteId] = ['estado' => 'pendiente', 'comando_id' => $cmdId];
      $clientesEnPending[] = $clienteId;
    }
  }
}

json_out([
  'success'            => true,
  'accion'             => $accion,
  'resultados'         => $resultados,
  'clientes_en_pending'=> $clientesEnPending
]);
