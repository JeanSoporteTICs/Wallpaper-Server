<?php
// api/procesar_pending.php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

function logProcesamiento(string $msg): void {
  file_put_contents(LOGS_DIR . '/procesar_pending.log', date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
}

function ejecutarComandoCliente(string $clienteId, array $cmd): string {
  // Implementa aquí la ejecución real (set_wallpaper / lock / unlock)
  logProcesamiento("Ejecutando {$cmd['action']} en {$clienteId}");
  // Devuelve "OK" si fue bien, o lanza excepción / devuelve error
  return 'OK';
}

logProcesamiento('=== INICIANDO PROCESADOR DE PENDING ===');

foreach (glob(CLIENTS_DIR . '/*.json') as $archivoCliente) {
  $raw  = file_get_contents($archivoCliente);
  $data = $raw ? json_decode($raw, true) : [];
  if (!is_array($data)) $data = [];

  $clienteId  = $data['id'] ?? basename($archivoCliente, '.json');
  $pendientes = &$data['pending'];
  if (!is_array($pendientes)) $pendientes = [];

  $procesados = 0;

  foreach ($pendientes as &$cmd) {
    if (($cmd['estado'] ?? 'pendiente') === 'pendiente') {
      $cmd['estado']       = 'procesando';
      $cmd['fecha_inicio'] = date('c');
      try {
        $resultado          = ejecutarComandoCliente($clienteId, $cmd);
        $cmd['estado']      = 'completado';   // <— UNIFICADO CON TERMINALES
        $cmd['respuesta']   = substr((string)$resultado, 0, 500);
        $cmd['fecha_ejecucion'] = date('c');
      } catch (Throwable $e) {
        $cmd['estado']    = 'fallo';
        $cmd['respuesta'] = substr($e->getMessage(), 0, 500);
        $cmd['fecha_ejecucion'] = date('c');
      }
      $procesados++;
    }
  }
  unset($cmd); // por seguridad con referencias

  file_put_contents($archivoCliente, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
  logProcesamiento("Cliente {$clienteId}: {$procesados} comandos procesados");
}

logProcesamiento('=== FINALIZANDO PROCESADOR DE PENDING ===');
