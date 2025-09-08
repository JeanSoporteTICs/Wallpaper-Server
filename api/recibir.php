<?php
// api/recibir.php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// --- Utils ---
function logRecibir(string $message): void {
    if (!is_dir(LOGS_DIR)) {
        @mkdir(LOGS_DIR, 0755, true);
    }
    file_put_contents(LOGS_DIR . '/recibir.log', date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
}

// Asegurar carpeta de clientes
if (!is_dir(CLIENTS_DIR)) {
    @mkdir(CLIENTS_DIR, 0755, true);
}

// Leer JSON una sola vez
$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    logRecibir('ERROR: No input received');
    json_out(['error' => 'No input'], 400);
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    logRecibir('ERROR: Invalid JSON: ' . substr($raw, 0, 200));
    json_out(['error' => 'Invalid JSON'], 400);
}

// Auth por SECRET_KEY
if (empty($payload['secret_key']) || $payload['secret_key'] !== SECRET_KEY) {
    logRecibir('ERROR: Invalid secret_key from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    json_out(['error' => 'Invalid secret_key'], 403);
}

// Ping de prueba (aceptar 'ping' o 'test')
if (!empty($payload['ping']) || !empty($payload['test'])) {
    logRecibir('Ping OK from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    json_out(['ok' => true, 'pong' => true]);
}

// Devolver configuración del servidor si se solicita
if (!empty($payload['query_config']) && !empty($payload['id'])) {
    $id = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$payload['id']);
    logRecibir('Config query from: ' . $id);

    $server_config_file = DATA_DIR . '/server_config.json';
    if (is_file($server_config_file)) {
        $cfg = json_decode((string)file_get_contents($server_config_file), true);
        json_out(['ok' => true, 'config' => (is_array($cfg) ? $cfg : null)]);
    }
    json_out(['ok' => true, 'config' => null]);
}

// Registro/actualización de cliente
if (isset($payload['client']) && is_array($payload['client'])) {
    $client = $payload['client'];

    if (empty($client['id'])) {
        logRecibir('ERROR: client id missing');
        json_out(['error' => 'client id missing'], 400);
    }

    $id = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$client['id']);
    $client_file = CLIENTS_DIR . '/' . $id . '.json';
    logRecibir('Client data received from: ' . $id);

    // Cargar estado previo (si existe) con lectura segura
    $existing = [];
    $isNewClient = !is_file($client_file);
    if (!$isNewClient) {
        $prev = file_get_contents($client_file);
        $existing = is_string($prev) ? json_decode($prev, true) : null;
        if (!is_array($existing)) {
            $existing = [];
        }
    }

    // Tomar comandos pendientes que el servidor ya tiene
    $pending_commands = isset($existing['pending']) && is_array($existing['pending'])
        ? $existing['pending']
        : [];

    // Solo enviar al cliente los que siguen pendientes
    $comandos_pendientes = array_values(array_filter($pending_commands, function ($cmd) {
        $estado = $cmd['estado'] ?? 'pendiente';
        return $estado === 'pendiente';
    }));

    // Mezclar datos del cliente nuevo con el existente, preservando pending del servidor
    $merged_client = array_merge($existing, $client);

    // Si el cliente manda pending propios, se agregan detrás (opcional)
    if (isset($payload['pending']) && is_array($payload['pending'])) {
        $merged_client['pending'] = array_merge($comandos_pendientes, $payload['pending']);
    } else {
        $merged_client['pending'] = $comandos_pendientes;
    }

    // Metadata
    $merged_client['last_seen'] = $client['last_seen'] ?? date('c');
    $merged_client['ip']        = $client['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

    // Guardar (con LOCK_EX para evitar condiciones de carrera)
    if (!is_dir(dirname($client_file))) {
        @mkdir(dirname($client_file), 0755, true);
    }
    $ok = file_put_contents(
        $client_file,
        json_encode($merged_client, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );

    if ($ok === false) {
        logRecibir('ERROR: Failed to write client file for ' . $id);
        json_out(['error' => 'Failed to persist client'], 500);
    }

    // Respuesta al cliente
    $response = [
        'ok'               => true,
        'saved'            => true,
        'pending_commands' => $comandos_pendientes, // lo que debe ejecutar
        'pending_count'    => count($comandos_pendientes),
        'is_new_client'    => $isNewClient
    ];

    logRecibir('Sent ' . count($comandos_pendientes) . ' pending commands to: ' . $id);
    json_out($response);
}

// Caso no contemplado
logRecibir('ERROR: Unhandled payload from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
json_out(['error' => 'Unhandled payload'], 400);
