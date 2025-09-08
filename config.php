<?php
// config.php
declare(strict_types=1);

// Clave maestra que usan cliente<->servidor para firmar
const SECRET_KEY = 'CAMBIA_ESTA_CLAVE_EN_TU_SERVIDOR';

// Contraseña de aprovisionamiento (solo para traer SECRET_KEY)
// Pon una diferente y guárdala en un lugar seguro
const PROVISION_PASSWORD = '5T1.2024';

const BASE_DIR    = __DIR__;
const DATA_DIR    = BASE_DIR . '/data';
const CLIENTS_DIR = DATA_DIR . '/clientes';
const FONDOS_DIR  = BASE_DIR . '/fondos';
const LOGS_DIR    = BASE_DIR . '/logs';

if (!function_exists('json_out')) {
  function json_out(array $p, int $status=200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo json_encode($p, JSON_UNESCAPED_UNICODE);
    exit;
  }
}

@is_dir(DATA_DIR)    || @mkdir(DATA_DIR,    0755, true);
@is_dir(CLIENTS_DIR) || @mkdir(CLIENTS_DIR, 0755, true);
@is_dir(FONDOS_DIR)  || @mkdir(FONDOS_DIR,  0755, true);
@is_dir(LOGS_DIR)    || @mkdir(LOGS_DIR,    0755, true);
