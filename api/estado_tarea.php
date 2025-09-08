<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$TERMINALES = ['completado','fallo','cancelado','no_encontrado','expirado'];
$DEFAULT_EXPIRY_SECONDS = 45;
$MAX_IDS = 5000;
// ----------------------
// Utilidades
// ----------------------
function json_out(array $payload, int $status = 200): void {
  http_response_code($status);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

function safe_json_decode(?string $json): ?array {
  if (!is_string($json) || $json === '') return null;
  try {
    /** @var array|null */
    $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    return is_array($data) ? $data : null;
  } catch (Throwable $e) {
    return null;
  }
}

// ----------------------
// Validación de entrada
// ----------------------
if (empty($_GET['ids'])) {
  json_out(['error' => 'IDs de tareas no especificados'], 400);
}

$expirySec = isset($_GET['expiry']) ? (int)$_GET['expiry'] : DEFAULT_EXPIRY_SECONDS;
if ($expirySec < 10)   $expirySec = 10;
if ($expirySec > 3600) $expirySec = 3600;

// Normaliza y valida IDs
$comandoIds = array_values(array_unique(array_filter(array_map('trim', explode(',', (string)$_GET['ids'])))));
$totalIds   = count($comandoIds);
if ($totalIds === 0 || $totalIds > $MAX_IDS) {
  json_out(['error' => 'Número de IDs inválido o excesivo'], 400);
}
$idSet = array_flip($comandoIds); // lookup O(1)

// Directorio de clientes
$clientesDir = CLIENTS_DIR;
if (!is_dir($clientesDir)) {
  json_out(['error' => 'Directorio de clientes no encontrado'], 500);
}

// ----------------------
// Estructura base de respuesta
// ----------------------
$resultados = [
  'completados' => 0,
  'total'       => $totalIds,
  'estado'      => 'pendiente',
  'progreso'    => 0,
  'detalles'    => []    // cmd_id => detalle
];

$now         = time();
$encontrados = [];       // cmd_id => ['clienteFile'=>..., 'idx'=>..., 'detalle'=>...]

// ----------------------
// Recorrido de clientes
// ----------------------
foreach (glob($clientesDir . '*.json') as $file) {
  // Abrimos para posible escritura (para marcar expirado)
  $fp = @fopen($file, 'c+');
  if (!$fp) continue;

  // Lectura con lock compartido
  if (!flock($fp, LOCK_SH)) { fclose($fp); continue; }
  $json = stream_get_contents($fp);
  flock($fp, LOCK_UN);

  $data = safe_json_decode($json);
  if (!is_array($data) || empty($data['pending']) || !is_array($data['pending'])) {
    fclose($fp);
    continue;
  }

  $clienteId = pathinfo($file, PATHINFO_FILENAME);

  // Explora pending y busca los comandos que nos interesan
  foreach ($data['pending'] as $i => $cmd) {
    $cmdId = $cmd['comando_id'] ?? '';
    if ($cmdId === '' || !isset($idSet[$cmdId])) continue; // más rápido que in_array

    $estado = $cmd['estado'] ?? 'pendiente';
    $tsStr  = $cmd['timestamp'] ?? '';
    $ts     = $tsStr ? strtotime($tsStr) : false;
    $age    = ($ts !== false) ? max(0, $now - $ts) : null;

    // Expira pendientes "viejos"
    if ($estado === 'pendiente' && $age !== null && $age >= $expirySec) {
      $data['pending'][$i]['estado'] = 'expirado';
      $estado = 'expirado';

      // Persistimos con lock exclusivo
      if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
      }
    }

    // Guarda el detalle que usaremos para la respuesta
    $detalle = [
      'estado'     => $estado,
      'cliente_id' => $clienteId,
      'action'     => $cmd['action'] ?? '',
      'timestamp'  => $tsStr,
      'age_sec'    => $age
    ];

    $encontrados[$cmdId] = [
      'clienteFile' => $file,
      'idx'         => $i,
      'detalle'     => $detalle
    ];
  }

  fclose($fp);
}

// ----------------------
// Construcción de la respuesta
// ----------------------
$completados = 0;

foreach ($comandoIds as $cmdId) {
  if (!isset($encontrados[$cmdId])) {
    // No aparece en ningún cliente => lo marcamos terminal como no_encontrado
    $resultados['detalles'][$cmdId] = [
      'estado'     => 'no_encontrado',
      'cliente_id' => '',
      'action'     => '',
      'timestamp'  => '',
      'age_sec'    => null
    ];
    $completados++;
    continue;
  }

  $detalle = $encontrados[$cmdId]['detalle'];
  $resultados['detalles'][$cmdId] = $detalle;

  // Si está en un estado terminal, suma al contador
  if (in_array($detalle['estado'], $TERMINALES, true)) {
    $completados++;
  }
}

// Estado global y progreso
$resultados['completados'] = $completados;
$resultados['progreso']    = $totalIds > 0 ? (int)round(($completados / $totalIds) * 100) : 0;

if ($completados >= $totalIds) {
  $resultados['estado'] = 'completado';
} else {
  $resultados['estado'] = ($completados > 0) ? 'ejecutando' : 'pendiente';
}

// ----------------------
// Salida
// ----------------------
json_out($resultados);
