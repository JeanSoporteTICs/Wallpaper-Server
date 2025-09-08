<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$TERMINALES = ['completado','fallo','cancelado','no_encontrado','expirado'];

function read_json_body(): array {
  $raw = file_get_contents('php://input');
  if (!$raw) return [];
  $data = json_decode($raw, true);
  return (json_last_error() === JSON_ERROR_NONE && is_array($data)) ? $data : [];
}

$in     = read_json_body();
$params = array_merge($_GET, $_POST, $in);

// Auth
$authorized = false;
if (!empty($params['secret_key']) && $params['secret_key'] === SECRET_KEY) {
  $authorized = true;
} elseif (!empty($_SESSION['csrf_token']) && !empty($params['csrf_token']) && $params['csrf_token'] === $_SESSION['csrf_token']) {
  $authorized = true;
}
if (!$authorized) json_out(['success'=>false,'message'=>'Token CSRF o secret_key inválido'], 403);

// Validar id
$clienteId = $params['id'] ?? '';
if (!$clienteId || !preg_match('/^[a-zA-Z0-9_-]+$/', $clienteId)) {
  json_out(['success'=>false,'message'=>'ID de cliente no especificado o inválido'], 400);
}

$archivoCliente = CLIENTS_DIR . '/' . $clienteId . '.json';
if (!is_file($archivoCliente)) json_out(['success'=>false,'message'=>'Cliente no encontrado'], 404);

// Lectura con lock
$fp = @fopen($archivoCliente, 'r');
if (!$fp) json_out(['success'=>false,'message'=>'No se pudo abrir el archivo del cliente'], 500);

flock($fp, LOCK_SH);
$json = stream_get_contents($fp);
flock($fp, LOCK_UN);
fclose($fp);

$data = json_decode($json, true);
if (!is_array($data)) json_out(['success'=>false,'message'=>'JSON del cliente inválido'], 500);

$pendientes = isset($data['pending']) && is_array($data['pending']) ? $data['pending'] : [];

$onlyOpen = isset($params['only_open'])
  ? filter_var($params['only_open'], FILTER_VALIDATE_BOOLEAN)
  : false;

if ($onlyOpen) {
  $pendientes = array_values(array_filter($pendientes, function ($cmd) use ($TERMINALES) {
    $estado = $cmd['estado'] ?? 'pendiente';
    return !in_array($estado, $TERMINALES, true);
  }));
}

json_out(['success'=>true, 'pending'=>$pendientes, 'count'=>count($pendientes)]);
