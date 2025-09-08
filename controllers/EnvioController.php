<?php
require_once __DIR__ . '/../models/TareaModel.php';
require_once __DIR__ . '/../models/ClienteModel.php';
require_once __DIR__ . '/../models/ArchivoModel.php';
require_once __DIR__ . '/../utils/Validacion.php';
require_once __DIR__ . '/../utils/WebhookUtils.php';

class EnvioController {
    private $tareaModel;
    private $clienteModel;
    private $archivoModel;
    private $webhookUtils;
    private $validacion;
    
    public function __construct() {
        session_start();
        
        // Configuración para ejecución larga
        ini_set('max_execution_time', 0);
        set_time_limit(0);
        ignore_user_abort(true);
        
        // Desactivar visualización de errores en producción
        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 0);
        error_reporting(E_ALL);
        ini_set('log_errors', 1);
        ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');
        
        $this->tareaModel = new TareaModel();
        $this->clienteModel = new ClienteModel();
        $this->archivoModel = new ArchivoModel();
        $this->webhookUtils = new WebhookUtils();
        $this->validacion = new Validacion();
    }
    
    public function procesarEnvio() {
        try {
            // Validar CSRF token
            if (!$this->validacion->validarCsrf($_POST['csrf_token'] ?? '')) {
                throw new Exception('Token CSRF inválido.');
            }
            
            // Validar clientes seleccionados
            if (empty($_POST['clientes']) || !is_array($_POST['clientes'])) {
                throw new Exception('Debe seleccionar al menos un cliente.');
            }
            
            $accion = $_POST['accion'] ?? '';
            $clientesSeleccionados = $_POST['clientes'];
            
            // Validar acción
            if (!$this->validacion->validarAccion($accion)) {
                throw new Exception('Acción no válida: ' . $accion);
            }
            
            // Procesar archivo si es necesario
            $nombreArchivo = '';
            if ($accion === 'set_wallpaper') {
                $nombreArchivo = $this->archivoModel->procesarArchivo($_FILES['fondo'] ?? []);
            }
            
            // Procesar envío a clientes
            $resultados = $this->procesarClientes($accion, $clientesSeleccionados, $nombreArchivo);
            
            // Preparar respuesta
            $this->enviarRespuesta($resultados, $accion);
            
        } catch (Exception $e) {
            $this->manejarError($e->getMessage());
        }
    }
    
    private function procesarClientes($accion, $clientesSeleccionados, $nombreArchivo = '') {
        $resultadosWebhooks = [];
        $clientesParaCola = [];
        
        foreach ($clientesSeleccionados as $clienteId) {
            error_log("--- Procesando cliente: " . $clienteId . " ---");
            
            // Obtener datos del cliente
            $clienteData = $this->clienteModel->obtenerDatosCliente($clienteId);
            
            if (!$clienteData) {
                $resultadosWebhooks[$clienteId] = [
                    'estado' => 'fallo', 
                    'error' => 'Cliente no encontrado',
                    'metodo' => 'none'
                ];
                $clientesParaCola[] = $clienteId;
                continue;
            }
            
            // Generar y verificar webhook
            $webhookUrl = $this->webhookUtils->generarUrlWebhook($clienteData);
            
            if (!$webhookUrl) {
                $resultadosWebhooks[$clienteId] = [
                    'estado' => 'ip_invalida', 
                    'error' => 'IP del cliente no válida para webhook',
                    'metodo' => 'none',
                    'ip_cliente' => $clienteData['ip'] ?? 'no_proporcionada'
                ];
                $clientesParaCola[] = $clienteId;
                continue;
            }
            
            // Verificar conectividad
            if (!$this->webhookUtils->verificarConectividad($webhookUrl)) {
                $resultadosWebhooks[$clienteId] = [
                    'estado' => 'webhook_inaccesible', 
                    'error' => 'No se puede conectar al webhook',
                    'metodo' => 'webhook_fallado',
                    'url_intentada' => $webhookUrl
                ];
                $clientesParaCola[] = $clienteId;
                continue;
            }
            
            // Enviar webhook
            $payload = $this->prepararPayload($accion, $nombreArchivo);
            $webhookResult = $this->webhookUtils->enviarWebhook($webhookUrl, $payload);
            
            if ($webhookResult['success']) {
                $resultadosWebhooks[$clienteId] = [
                    'estado' => 'éxito_webhook', 
                    'metodo' => 'webhook_directo',
                    'http_code' => $webhookResult['http_code'],
                    'url_utilizada' => $webhookUrl
                ];
            } else {
                $resultadosWebhooks[$clienteId] = [
                    'estado' => 'fallo_webhook', 
                    'error' => $webhookResult['error'],
                    'http_code' => $webhookResult['http_code'],
                    'metodo' => 'webhook_fallado',
                    'url_utilizada' => $webhookUrl
                ];
                $clientesParaCola[] = $clienteId;
            }
        }
        
        return [
            'resultados' => $resultadosWebhooks,
            'clientesParaCola' => $clientesParaCola
        ];
    }
    
    private function prepararPayload($accion, $nombreArchivo = '') {
        $payload = ['action' => $accion];
        
        if ($accion === 'set_wallpaper' && $nombreArchivo) {
            $payload['image_name'] = $nombreArchivo;
            $payload['image_url'] = 'https://' . $_SERVER['HTTP_HOST'] . '/fondos/' . $nombreArchivo;
            $payload['style'] = $_POST['estilo'] ?? 'fill';
        }
        
        return $payload;
    }
    
    private function enviarRespuesta($resultados, $accion) {
        $resultadosWebhooks = $resultados['resultados'];
        $clientesParaCola = $resultados['clientesParaCola'];
        
        // Crear tarea en cola si es necesario
        $idTarea = null;
        if (!empty($clientesParaCola)) {
            $idTarea = $this->tareaModel->crearTareaEnCola(
                $accion, 
                $clientesParaCola, 
                $this->archivoModel->getNombreArchivo()
            );
            $this->tareaModel->iniciarProcesamientoCola();
        }
        
        // Estadísticas
        $clientesWebhookExitoso = array_filter($resultadosWebhooks, function($result) {
            return $result['estado'] === 'éxito_webhook';
        });
        
        $clientesWebhookFallido = array_filter($resultadosWebhooks, function($result) {
            return in_array($result['estado'], ['fallo_webhook', 'webhook_inaccesible', 'ip_invalida']);
        });
        
        // Preparar respuesta
        $response = [
            'success' => true,
            'mensaje' => 'Procesamiento completado. ' .
                         count($clientesWebhookExitoso) . ' webhooks exitosos, ' .
                         count($clientesWebhookFallido) . ' webhooks fallados.',
            'estadisticas' => [
                'total_clientes' => count($resultadosWebhooks),
                'webhooks_exitosos' => count($clientesWebhookExitoso),
                'webhooks_fallidos' => count($clientesWebhookFallido),
                'en_cola' => count($clientesParaCola)
            ],
            'resultados_detallados' => $resultadosWebhooks
        ];
        
        if ($idTarea) {
            $response['id_tarea'] = $idTarea;
            $response['clientes_en_cola'] = $clientesParaCola;
            $response['url_estado'] = 'api/estado_tarea.php?id=' . $idTarea;
        }
        
        $response['redirect'] = '../index.php';
        
        // Enviar respuesta JSON
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    private function manejarError($mensaje) {
        error_log("Error en EnvioController: " . $mensaje);
        
        // Preparar respuesta de error
        $response = [
            'success' => false,
            'error' => $mensaje,
            'redirect' => '../index.php'
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}
?>