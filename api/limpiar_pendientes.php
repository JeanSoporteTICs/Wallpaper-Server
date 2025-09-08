<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';


session_start();
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$csrf_ok   = !empty($_SESSION['csrf_token']) && !empty($input['csrf_token']) && $input['csrf_token'] === $_SESSION['csrf_token'];
$secret_ok = !empty($input['secret_key']) && $input['secret_key'] === SECRET_KEY;

if (!$csrf_ok && !$secret_ok) json_out(['success'=>false,'message'=>'Token CSRF o secret_key inválido'], 403);

$clienteId = $input['id'] ?? '';
if (!$clienteId) json_out(['success'=>false,'message'=>'ID de cliente no especificado'], 400);

$archivoCliente = CLIENTS_DIR . '/' . $clienteId . '.json';
if (!is_file($archivoCliente)) json_out(['success'=>false,'message'=>'Cliente no encontrado'], 404);

$data = json_decode((string)file_get_contents($archivoCliente), true) ?: [];
$pendientesCount = count($data['pending'] ?? []);
$data['pending'] = [];

if (file_put_contents($archivoCliente, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
  json_out(['success'=>false,'message'=>'Error al guardar cambios'], 500);
}

json_out(['success'=>true,'message'=>"Se eliminaron $pendientesCount acciones pendientes"]);
