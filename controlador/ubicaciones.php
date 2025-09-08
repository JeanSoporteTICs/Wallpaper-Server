<?php
session_start();

$id = $_POST['id'] ?? '';
$edificio = $_POST['edificio'] ?? '';
$piso = $_POST['piso'] ?? '';
$servicio = $_POST['servicio'] ?? '';
$oficina = $_POST['oficina'] ?? '';

if (!$id || !$edificio || !$piso || !$servicio || !$oficina) {
    $_SESSION['error'] = "Todos los campos son obligatorios.";
    header("Location: ../index.php");
    exit;
}

$ubicacionesDir = dirname(__DIR__) . '/data/ubicaciones/';
if (!is_dir($ubicacionesDir)) {
    mkdir($ubicacionesDir, 0755, true);
}

$ubicacionData = [
    "edificio" => $edificio,
    "piso" => $piso,
    "servicio" => $servicio,
    "oficina" => $oficina,
];

$archivoUbicacion = $ubicacionesDir . $id . '.json';

file_put_contents($archivoUbicacion, json_encode($ubicacionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$_SESSION['mensaje'] = "Ubicación guardada correctamente.";
header("Location: ../index.php");
exit;
