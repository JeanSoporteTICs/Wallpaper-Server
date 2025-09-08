<?php
// api/provision_secret.php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Solo JSON POST
$raw = file_get_contents('php://input');
$in  = $raw ? json_decode($raw, true) : null;
if (!is_array($in)) {
  json_out(['ok' => false, 'message' => 'Invalid JSON'], 400);
}

// Validar contraseña de aprovisionamiento
$pw = (string)($in['provision_password'] ?? '');
if ($pw !== PROVISION_PASSWORD) {
  json_out(['ok' => false, 'message' => 'Provision password invalid'], 403);
}

// Devuelve la SECRET_KEY real
json_out(['ok' => true, 'secret_key' => SECRET_KEY], 200);
