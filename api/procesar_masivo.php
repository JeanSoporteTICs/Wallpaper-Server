<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

// Configuración
$SECRET_KEY = "CAMBIA_ESTA_CLAVE_EN_TU_SERVIDOR";
$DATA_DIR = __DIR__ . "/../data";
$CLIENTS_DIR = $DATA_DIR . "/clientes";

// Validar CSRF
if (empty($_SESSION['csrf_token']) || empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

// Validar acción y clientes
$accion = $_POST['accion'] ?? '';
$clientes = $_POST['clientes'] ?? [];
$parametros = $_POST['parametros'] ?? [];

if (empty($accion) || empty($clientes) || !is_array($clientes)) {
    echo json_encode(['success' => false, 'message' => 'Acción o clientes no especificados']);
    exit;
}

// Crear comando para cada cliente
$comando_base = [
    'action' => $accion,
    'timestamp' => date('c'),
    'estado' => 'pendiente',
    'comando_id' => uniqid('cmd_')
];

// Agregar parámetros específicos según la acción
if ($accion === 'set_wallpaper') {
    $comando_base['image_name'] = $parametros['image_name'] ?? '';
    $comando_base['style'] = $parametros['style'] ?? 'fill';
} elseif ($accion === 'lock' || $accion === 'unlock') {
    // No necesita parámetros adicionales
}

$resultados = [];
$exitosos = 0;
$fallidos = 0;

foreach ($clientes as $clienteId) {
    $cliente_file = $CLIENTS_DIR . "/" . $clienteId . ".json";
    
    if (!file_exists($cliente_file)) {
        $resultados[$clienteId] = 'cliente_no_encontrado';
        $fallidos++;
        continue;
    }
    
    // Leer datos del cliente
    $data = json_decode(file_get_contents($cliente_file), true);
    if (!isset($data['pending'])) {
        $data['pending'] = [];
    }
    
    // Agregar comando a pendientes
    $data['pending'][] = $comando_base;
    
    // Guardar
    if (file_put_contents($cliente_file, json_encode($data, JSON_PRETTY_PRINT))) {
        $resultados[$clienteId] = 'comando_agregado';
        $exitosos++;
    } else {
        $resultados[$clienteId] = 'error_guardado';
        $fallidos++;
    }
}

echo json_encode([
    'success' => true,
    'message' => "Comando agregado a $exitosos clientes, $fallidos fallidos",
    'resultados' => $resultados,
    'total' => count($clientes),
    'exitosos' => $exitosos,
    'fallidos' => $fallidos
]);