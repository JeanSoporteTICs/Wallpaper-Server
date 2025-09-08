<?php
declare(strict_types=1);
// api/eliminar_cliente.php
require_once __DIR__ . '/../config.php';


session_start();
header('Content-Type: application/json; charset=utf-8');

try {
  // CSRF
  $csrf = $_POST['csrf_token'] ?? '';
  if (empty($_SESSION['csrf_token']) || $csrf !== $_SESSION['csrf_token']) {
    json_out(['success' => false, 'message' => 'Token CSRF inválido'], 403);
  }

  $id = $_POST['id'] ?? '';
  if (!$id || !preg_match('/^[a-zA-Z0-9_-]+$/', $id)) {
    json_out(['success' => false, 'message' => 'ID inválido'], 400);
  }

  $clienteFile    = CLIENTS_DIR . '/' . $id . '.json';
  $ubicacionFile  = DATA_DIR . '/ubicaciones/' . $id . '.json'; // ajusta si tienes otra ruta

  $existedCliente   = is_file($clienteFile);
  $existedUbicacion = is_file($ubicacionFile);

  $okCliente   = !$existedCliente   || @unlink($clienteFile);
  $okUbicacion = !$existedUbicacion || @unlink($ubicacionFile);

  if (!$okCliente) {
    json_out(['success' => false, 'message' => 'No se pudo eliminar el archivo del cliente'], 500);
  }
  if (!$okUbicacion && $existedUbicacion) {
    // no abortamos si solo falló ubicación; reportamos aviso
    json_out([
      'success' => true,
      'message' => 'Cliente eliminado. (Aviso: la ubicación no se pudo eliminar, revisa permisos)'
    ], 200);
  }

  $parts = [];
  $parts[] = $existedCliente ? 'Cliente eliminado' : 'Cliente no existía';
  $parts[] = $existedUbicacion ? 'Ubicación eliminada' : 'Ubicación no existía';
  json_out(['success' => true, 'message' => implode('. ', $parts) . '.'], 200);

} catch (Throwable $e) {
  json_out(['success' => false, 'message' => 'Error inesperado: ' . $e->getMessage()], 500);
}
