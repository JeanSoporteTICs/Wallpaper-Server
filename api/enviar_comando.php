<?php
// api/enviar_comando.php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$TERMINALES = ['completado','fallo','cancelado','no_encontrado','expirado'];

function read_json_input(): array {
  $raw = file_get_contents('php://input');
  if (!$raw) return [];
  $data = json_decode($raw, true);
  return (json_last_error() === JSON_ERROR_NONE && is_array($data)) ? $data : [];
}

function boolval_loose($v): bool {
  if (is_bool($v)) return $v;
  $s = strtolower((string)$v);
  return in_array($s, ['1','true','on','yes','y','t'], true);
}

$input  = read_json_input();
$params = array_merge($_GET, $_POST, $input);

// Auth
$authorized = false;
if (!empty($params['secret_key']) && $params['secret_key'] === SECRET_KEY) {
  $authorized = true;
} elseif (!empty($_SESSION['csrf_token']) && !empty($params['csrf_token']) && $params['csrf_token'] === $_SESSION['csrf_token']) {
  $authorized = true;
}
if (!$authorized) json_out(['success'=>false,'message'=>'Token CSRF o secret_key inválido'], 403);

// Cliente
$clienteId = $params['id'] ?? '';
if (!$clienteId || !preg_match('/^[a-zA-Z0-9_-]+$/', $clienteId)) {
  json_out(['success'=>false,'message'=>'ID de cliente no especificado o inválido'], 400);
}

// Archivo
$archivoCliente = CLIENTS_DIR . '/' . $clienteId . '.json';
if (!is_file($archivoCliente)) json_out(['success'=>false,'message'=>'Cliente no encontrado'], 404);

// Lectura
$fp = fopen($archivoCliente, 'r');
if (!$fp) json_out(['success'=>false,'message'=>'No se pudo abrir el archivo del cliente'], 500);
flock($fp, LOCK_SH);
$json = stream_get_contents($fp);
flock($fp, LOCK_UN);
fclose($fp);

$data = json_decode($json, true);
if (!is_array($data)) json_out(['success'=>false,'message'=>'JSON del cliente corrupto o inválido'], 500);

$pendientes = is_array($data['pending'] ?? null) ? $data['pending'] : [];
if (!empty($params['only_open']) && boolval_loose($params['only_open'])) {
  $pendientes = array_values(array_filter($pendientes, function($cmd) use ($TERMINALES) {
    $estado = $cmd['estado'] ?? 'pendiente';
    return !in_array($estado, $TERMINALES, true);
  }));
}

json_out(['success'=>true,'pending'=>$pendientes,'count'=>count($pendientes)]);
