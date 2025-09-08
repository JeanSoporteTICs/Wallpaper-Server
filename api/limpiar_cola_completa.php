<?php
// api/limpiar_cola_completa.php - Versión final con logs y seguridad

header('Content-Type: application/json');

// ===============================
// CONFIGURACIÓN
// ===============================
$SECRET_KEY  = "CAMBIA_ESTA_CLAVE_EN_TU_SERVIDOR";
$BASE_DIR    = dirname(__DIR__);
$CLIENTS_DIR = $BASE_DIR . '/data/clientes/';
$COLA_DIR    = $BASE_DIR . '/cola/';
$LOG_FILE    = $BASE_DIR . '/logs/limpieza.log';

// ===============================
// FUNCIONES
// ===============================
function logLimpieza($message) {
    global $LOG_FILE;
    $line = date('[Y-m-d H:i:s] ') . $message . "\n";
    file_put_contents($LOG_FILE, $line, FILE_APPEND);
}

// ===============================
// LEER INPUT
// ===============================
$input = json_decode(file_get_contents('php://input'), true);

// ===============================
// VALIDAR SECRET_KEY
// ===============================
if (!isset($input['secret_key']) || $input['secret_key'] !== $SECRET_KEY) {
    logLimpieza("ERROR: Invalid secret_key desde " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " recibido: " . ($input['secret_key'] ?? 'NULL'));
    echo json_encode(["success" => false, "message" => "Secret key inválida"]);
    exit;
}

// ===============================
// 1. LIMPIAR ARCHIVOS DE LA COLA
// ===============================
$archivosCola = glob($COLA_DIR . '*.json');
$eliminadosCola = 0;

foreach ($archivosCola as $archivo) {
    $data = json_decode(file_get_contents($archivo), true);
    if (!$data) {
        unlink($archivo);
        $eliminadosCola++;
        continue;
    }

    $estado = $data['estado'] ?? '';
    $fecha = isset($data['fecha_creacion']) ? strtotime($data['fecha_creacion']) : time();

    // Eliminar si está completada o tiene más de 7 días
    if ($estado === 'completado' || (time() - $fecha) > 7 * 24 * 3600) {
        unlink($archivo);
        $eliminadosCola++;
    }
}

// ===============================
// 2. LIMPIAR COMANDOS EJECUTADOS DE CLIENTES
// ===============================
$clientes = glob($CLIENTS_DIR . '*.json');
$eliminadosComandos = 0;

foreach ($clientes as $clienteArchivo) {
    $clienteData = json_decode(file_get_contents($clienteArchivo), true);
    if (!$clienteData || !isset($clienteData['pending'])) continue;

    $antes = count($clienteData['pending']);
    $clienteData['pending'] = array_filter($clienteData['pending'], function($cmd) {
        return ($cmd['estado'] ?? 'pendiente') === 'pendiente';
    });
    $despues = count($clienteData['pending']);
    $eliminadosComandos += ($antes - $despues);

    file_put_contents($clienteArchivo, json_encode($clienteData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ===============================
// LOG FINAL Y RESPUESTA
// ===============================
logLimpieza("Limpieza completada: $eliminadosCola archivos de cola eliminados, $eliminadosComandos comandos ejecutados eliminados");

echo json_encode([
    'success' => true,
    'message' => 'Limpieza completada',
    'archivos_cola_eliminados' => $eliminadosCola,
    'comandos_ejecutados_eliminados' => $eliminadosComandos
]);
exit;
