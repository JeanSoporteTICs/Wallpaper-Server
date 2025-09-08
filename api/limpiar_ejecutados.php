<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';


session_start();
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
if (empty($input['secret_key']) || $input['secret_key'] !== SECRET_KEY) {
  json_out(['success'=>false,'message'=>'Secret key inválida'], 403);
}

$clienteId = $input['id'] ?? '';
if (!$clienteId) json_out(['success'=>false,'message'=>'ID de cliente no especificado'], 400);

$cliente_file = CLIENTS_DIR . '/' . $clienteId . '.json';
if (!is_file($cliente_file)) json_out(['success'=>false,'message'=>'Cliente no encontrado'], 404);

$data = json_decode((string)file_get_contents($cliente_file), true) ?: [];
$antes = count($data['pending'] ?? []);
$data['pending'] = array_values(array_filter($data['pending'] ?? [], function ($cmd) {
  return (($cmd['estado'] ?? 'pendiente') === 'pendiente');
}));

$despues = count($data['pending']);
file_put_contents($cliente_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

json_out([
  'success' => true,
  'message' => 'Limpiados ' . ($antes - $despues) . ' comandos ejecutados',
  'pendientes_restantes' => $despues
]);

