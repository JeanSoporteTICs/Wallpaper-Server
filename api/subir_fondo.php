<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

// api/subir_fondo.php


header('Content-Type: application/json; charset=utf-8');

// Verificar clave secreta
$input = json_decode(file_get_contents('php://input'), true);
if (empty($input['secret_key']) || $input['secret_key'] !== SECRET_KEY) {
  json_out(['error' => 'Acceso no autorizado'], 403);
}

// Validar datos
if (empty($input['nombre']) || !isset($input['datos'])) {
  json_out(['error' => 'Datos incompletos'], 400);
}

// Guardar imagen
$nombreArchivo     = preg_replace('/[^a-zA-Z0-9\.\-_]/', '', (string)$input['nombre']);
$rutaArchivo       = FONDOS_DIR . '/' . $nombreArchivo;
$datosDecodificados= base64_decode((string)$input['datos'], true);

if ($datosDecodificados === false || file_put_contents($rutaArchivo, $datosDecodificados) === false) {
  json_out(['error' => 'Error al guardar la imagen'], 500);
}

json_out(['ok' => true, 'nombre' => $nombreArchivo]);
