<?php
require_once __DIR__ . '/config.php';
require_once 'controllers/ClientesController.php';

$controller = new ClientesController();

// Cargar datos de clientes
$datosClientes = $controller->cargarClientes();
$clientes = $datosClientes['clientes'];
$errorCarga = $datosClientes['error'];

// Obtener mensajes flash
$mensajes = $controller->obtenerMensajesFlash();

// Obtener token CSRF
$csrf_token = $controller->obtenerCsrfToken();

// Incluir la vista
require_once 'views/clientes.php';
?>