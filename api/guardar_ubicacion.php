<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';


session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
  json_out(['success'=>false,'message'=>'Token CSRF inválido'], 403);
}

foreach (['id','edificio','piso','servicio','oficina','responsable'] as $f) {
  if (empty($_POST[$f])) json_out(['success'=>false,'message'=>"El campo {$f} es requerido"], 400);
}

$id   = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$_POST['id']);
$data = [
  'edificio' => (string)$_POST['edificio'],
  'piso'     => (string)$_POST['piso'],
  'servicio' => (string)$_POST['servicio'],
  'oficina'  => (string)$_POST['oficina'],
  'responsable'  => (string)$_POST['responsable'],
];

$ubicacionesDir = DATA_DIR . '/ubicaciones';
@is_dir($ubicacionesDir) || @mkdir($ubicacionesDir, 0755, true);

$filePath = $ubicacionesDir . '/' . $id . '.json';
if (file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
  json_out(['success'=>false,'message'=>'Error al guardar los datos'], 500);
}

json_out(['success'=>true,'message'=>'Ubicación guardada correctamente']);
