<?php
// ==========================
// Entrada de datos al template
// ==========================
// La vista recibe: $clientes, $errorCarga, $mensajes, $csrf_token
// Desempaquetamos $mensajes de forma segura (sin extract)
$error           = $mensajes['error']           ?? '';
$success         = $mensajes['success']         ?? '';
$resultado_envio = $mensajes['resultado_envio'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <!-- ==========================
       META / CSS / TÍTULO
       ========================== -->
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Administración de Clientes</title>

  <!-- Favicon -->
  <link rel="icon" href="./img/favicon.ico" type="image/x-icon">

  <!-- Bootstrap + Iconos -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

  <!-- Estilos propios -->
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <!-- ==========================
       OVERLAY DE CARGA (opcional)
       ========================== -->
  <div class="loading-overlay">
    <div class="spinner-border text-light" role="status">
      <span class="visually-hidden">Cargando...</span>
    </div>
  </div>

  <!-- ==========================
       CONTENIDO PRINCIPAL
       ========================== -->
  <main class="container-fluid py-4">
    <h1 class="text-center mb-4">Clientes Registrados</h1>

    <!-- ==========================
         ALERTAS SUPERIORES
         ========================== -->
    <?php if (!empty($errorCarga)): ?>
      <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($errorCarga) ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger alert-floating">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div class="alert alert-success alert-floating">
        <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <?php if (!empty($resultado_envio)): ?>
      <div class="alert alert-success alert-floating">
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        <h5><i class="bi bi-check-circle-fill"></i> ¡Acción enviada correctamente!</h5>
        <p class="mb-1"><strong><?= htmlspecialchars($resultado_envio['mensaje'] ?? '') ?></strong></p>
        <div class="mt-2">
          <small class="text-muted">
            <strong>Acción:</strong>
            <?= isset($controller) && method_exists($controller, 'obtenerNombreAccion')
                  ? htmlspecialchars($controller->obtenerNombreAccion($resultado_envio['accion_ejecutada'] ?? ''))
                  : htmlspecialchars($resultado_envio['accion_ejecutada'] ?? 'N/D') ?> |
            <strong>ID:</strong> <?= htmlspecialchars($resultado_envio['id_tarea'] ?? 'N/D') ?> |
            <strong>Hora:</strong> <?= htmlspecialchars($resultado_envio['timestamp'] ?? '') ?>
          </small>
        </div>
        <div class="mt-2 d-flex gap-2">
          <?php if (!empty($resultado_envio['id_tarea'])): ?>
            <a href="api/estado_tarea.php?ids=<?= urlencode($resultado_envio['id_tarea']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
              <i class="bi bi-info-circle"></i> Ver estado detallado
            </a>
          <?php endif; ?>
          <button onclick="location.reload()" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-repeat"></i> Actualizar lista
          </button>
        </div>
      </div>
    <?php endif; ?>

    <!-- ==========================
         FORMULARIO PRINCIPAL
         ========================== -->
    <form method="POST" enctype="multipart/form-data" action="api/enviar.php" class="needs-validation" novalidate id="mainForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

      <!-- =============================================
           FILA DE 3 COLUMNAS: ACCIÓN | PARÁMETROS | VISTA
           ============================================= -->
      <div class="row gx-1 gy-3 align-items-stretch">
        <!-- Columna 1: ACCIÓN -->
        <div class="col-12 col-lg-4" id="opcionesCol">
          <div class="card h-100">
            <div class="card-header bg-light">
              <i class="bi bi-send"></i>
              <strong>Acción</strong>
            </div>
            <div class="card-body fixed-panel">
              <div class="panel-content">
                <div class="mb-3">
                  <label for="accion" class="form-label fw-bold">Selecciona una acción</label>
                  <select class="form-select" name="accion" id="accion" required onchange="mostrarOpciones()">
                    <option value="" disabled selected>Seleccione...</option>
                    <option value="set_wallpaper">Cambiar fondo</option>
                    <option value="lock">Bloquear cambio de fondo</option>
                    <option value="unlock">Desbloquear cambio de fondo</option>
                    <option value="show_message">Enviar mensaje</option>
                  </select>
                  <div class="invalid-feedback">Por favor seleccione una acción</div>
                </div>
                <div id="estiloDiv" class="mb-3 hidden">
                  <label for="estilo" class="form-label fw-bold">Estilo</label>
                  <select class="form-select" name="estilo" id="estilo">
                    <option value="fill">Rellenar</option>
                    <option value="fit">Ajustar</option>
                    <option value="stretch">Extender</option>
                    <option value="tile">Mosaico</option>
                    <option value="center">Centrar</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="card-footer bg-light d-grid">
              <button type="submit" id="submitBtn" class="btn btn-primary">
                <i class="bi bi-send"></i> Enviar a seleccionados
              </button>
            </div>
          </div>
        </div>

        <!-- Columna 2: PARÁMETROS -->
        <div class="col-12 col-lg-4" id="paramCol">
          <div class="card h-100">
            <div class="card-header bg-light">
              <strong>Parámetros</strong>
            </div>
            <div class="card-body fixed-panel">
              <div class="panel-content">
                <div id="paramWallpaper">
                  <div id="fileDropArea" class="file-drop-area">
                    <input type="file" id="fondo" name="fondo" accept="image/*" class="visually-hidden" />
                    <label for="fondo" class="w-100 m-0">
                      <div id="fileInfo" class="file-info">
                        <i class="bi bi-cloud-upload"></i>&nbsp; Arrastra una imagen o haz clic aquí
                      </div>
                    </label>
                  </div>
                  <small class="text-muted d-block mt-2">
                    Formatos soportados: JPG, PNG, GIF, BMP, WEBP. Máx. 10MB.
                  </small>
                </div>
                <div id="mensajeDiv" class="hidden">
                  <div class="mb-2">
                    <label for="msgTitle" class="form-label">Título (opcional)</label>
                    <input id="msgTitle" type="text" maxlength="120" class="form-control" placeholder="Título del mensaje" />
                  </div>
                  <div class="mb-2">
                    <label for="msgBody" class="form-label">Mensaje</label>
                    <textarea id="msgBody" class="form-control" rows="4" maxlength="4000" placeholder="Escribe el mensaje..."></textarea>
                    <small class="text-muted"><span id="msgCount">0</span>/4000</small>
                  </div>
                  <div class="mb-2">
                    <label for="msgTimeout" class="form-label">Cierre automático (segundos, opcional)</label>
                    <input id="msgTimeout" type="number" min="1" max="3600" class="form-control" placeholder="Ej: 15" />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Columna 3: VISTA -->
        <div class="col-12 col-lg-4" id="previewCol">
          <div class="card h-100">
            <div class="card-body fixed-panel">
              <div class="panel-content">
                <div id="previewWallpaper" class="hidden">
                  <img id="imgPreview" class="img-preview" alt="Vista previa" style="display:none;" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- =============================================
           TOOLBAR: búsqueda, filtros y selección
           ============================================= -->
      <div class="row g-2 align-items-center mt-4 mb-3">
        <div class="col-12 col-md-4">
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="busqueda" class="form-control" placeholder="Buscar por ID, nombre, IP, MAC, ubicación..." />
            <button class="btn btn-outline-secondary" type="button" id="limpiarBusqueda" title="Limpiar">
              <i class="bi bi-x-circle"></i>
            </button>
          </div>
        </div>
        <div class="col-12 col-md-3">
          <select id="filtroRapido" class="form-select">
            <option value="">Todos</option>
            <option value="sin-ubicacion">Sin ubicación</option>
            <option value="con-ubicacion">Con ubicación</option>
            <option value="bloqueados">Bloqueados</option>
            <option value="desbloqueados">Desbloqueados</option>
          </select>
        </div>
        <div class="col-12 col-md-3 d-flex gap-2">
          <button class="btn btn-outline-primary" type="button" id="btnSeleccionarPagina" title="Seleccionar visibles">
            <i class="bi bi-check-square"></i> Seleccionar visibles
          </button>
          <button class="btn btn-outline-secondary" type="button" id="btnDeseleccionarTodos" title="Deseleccionar todos">
            <i class="bi bi-square"></i> Deseleccionar
          </button>
        </div>
        <div class="col-12 col-md-2 text-md-end">
          <small id="contadorClientes" class="text-muted d-inline-block mt-2 mt-md-0"></small>
        </div>
      </div>

      <!-- =============================================
           TABLA DE CLIENTES
           ============================================= -->
      <div class="table-responsive mt-4">
        <table class="table table-hover table-bordered" id="clientesTable">
          <thead class="table-light">
            <tr>
              <th width="40">
                <input type="checkbox" id="checkTodos" class="form-check-input cursor-pointer" title="Seleccionar todos">
              </th>
              <th class="sortable" data-sort="id">ID <i class="bi bi-arrow-down-up"></i></th>
              <th class="sortable" data-sort="nombre">Nombre <i class="bi bi-arrow-down-up"></i></th>
              <th class="sortable" data-sort="serie">Número de Serie <i class="bi bi-arrow-down-up"></i></th>
              <th class="sortable" data-sort="ip">IP <i class="bi bi-arrow-down-up"></i></th>
              <th class="sortable" data-sort="servicio">Servicio <i class="bi bi-arrow-down-up"></i></th>
              <th class="sortable" data-sort="responsable">Responsable <i class="bi bi-arrow-down-up"></i></th>

              <th class="sortable" data-sort="conexion">Último Registro <i class="bi bi-arrow-down-up"></i></th>
              <th class="sortable text-center" data-sort="os">SO <i class="bi bi-arrow-down-up"></i></th>


              <th class="sortable" data-sort="pendientes">Pendientes <i class="bi bi-arrow-down-up"></i></th>
              <th class="sortable" data-sort="estado">Estado <i class="bi bi-arrow-down-up"></i></th>

              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($clientes)): ?>
              <tr>
                <td colspan="13" class="text-center text-muted py-4">
                  <i class="bi bi-exclamation-circle"></i> No hay clientes registrados
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($clientes as $id => $cliente): ?>
                <?php
                  $pendientes = (isset($cliente['pending']) && is_array($cliente['pending'])) ? count($cliente['pending']) : 0;
                  $isBloq     = ($cliente['bloqueado'] ?? '') === 'bloqueado';
                  // Preparar datos para exclusiones de proxy (Opción 2)
                  $full_exclusions = htmlspecialchars($cliente['proxy_exclusiones'] ?? 'N/A');
                  $safe_title = htmlspecialchars($full_exclusions, ENT_QUOTES, 'UTF-8');
                  // Truncar para mostrar en la tabla
                  $display_exclusions = (strlen($full_exclusions) > 50) ? substr($full_exclusions, 0, 47) . '...' : $full_exclusions;
                ?>
                <tr>
                  <td data-label="Seleccionar">
                    <input type="checkbox"
                           name="clientes[]"
                           value="<?= htmlspecialchars($id) ?>"
                           class="form-check-input cliente-checkbox">
                  </td>
                  <td data-label="ID"><?= htmlspecialchars($id) ?></td>
                  <td data-label="Nombre"><?= htmlspecialchars($cliente['nombre'] ?? '') ?></td>
                  <td data-label="Serie"><?= htmlspecialchars($cliente['numero_serie'] ?? '') ?></td>
                  <td data-label="IP"><?= htmlspecialchars($cliente['ip'] ?? '') ?></td>


                  <td data-label="Servicio"><?= htmlspecialchars($cliente['servicio'] ?? '-') ?></td>
                  <td data-label="Responsable"><?= htmlspecialchars($cliente['responsable'] ?? '-') ?></td>


                  <?php $ts = (string)($cliente['ultima_conexion'] ?? ($cliente['last_seen'] ?? '')); ?>
                  <td data-label="Último Registro"><time class="ts" datetime="<?= htmlspecialchars($ts) ?>"><?= htmlspecialchars($ts) ?></time></td>
                  <td data-label="SO" class="text-center"><?= htmlspecialchars($cliente['so'] ?? '') ?></td>
                  <td data-label="Pendientes">
                    <?= $pendientes > 0
                      ? "<span class='badge bg-warning'>$pendientes pendientes</span>"
                      : "<span class='badge bg-success'>0</span>" ?>
                  </td>
                  <td data-label="Estado" data-estado="<?= $isBloq ? 'bloqueado' : 'desbloqueado' ?>">
  <span class="status-badge status-badge-<?= $isBloq ? 'bloqueado' : 'desbloqueado' ?>" title="<?= $isBloq ? 'Bloqueado' : 'Desbloqueado' ?>">
    <i class="bi bi-<?= $isBloq ? 'lock' : 'unlock' ?>-fill"></i>
  </span>
</td>


                  <td data-label="Acciones" class="text-nowrap">
                    <button type="button"
                     title="Detalles Ubicación"
                            class="btn btn-primary btn-sm btnAgregarUbicacion"
                            data-bs-toggle="modal"
                            data-bs-target="#modalAgregarUbicacion"
                            data-id="<?= htmlspecialchars($id) ?>"
                            data-edificio="<?= htmlspecialchars($cliente['edificio'] ?? '') ?>"
                            data-servicio="<?= htmlspecialchars($cliente['servicio'] ?? '') ?>"
                            data-oficina="<?= htmlspecialchars($cliente['oficina'] ?? '') ?>"
                            data-responsable="<?= htmlspecialchars($cliente['responsable'] ?? '') ?>">
                      <i class="bi bi-geo-alt"></i> 
                    </button>
                    <!-- 👇 BOTÓN DETALLES DE RED CON EXCLUSIONES TRUNCADAS -->
                    <button type="button"
                    title="Detalles red"
                            class="btn btn-info btn-sm btnVerDetalles ms-1"
                            data-bs-toggle="modal"
                            data-bs-target="#modalDetallesRed"
                            data-id="<?= htmlspecialchars($id) ?>"
                            data-ip="<?= htmlspecialchars($cliente['ip'] ?? 'N/A') ?>"
                            data-mac="<?= htmlspecialchars($cliente['mac'] ?? 'N/A') ?>"
                            data-mascara="<?= htmlspecialchars($cliente['mascara'] ?? 'N/A') ?>"
                            data-gateway="<?= htmlspecialchars($cliente['gateway'] ?? 'N/A') ?>"
                            data-dns="<?= htmlspecialchars($cliente['dns'] ?? 'N/A') ?>"
                            data-proxy-activo="<?= htmlspecialchars($cliente['proxy_activo'] ?? 'No') ?>"
                            data-proxy-servidor="<?= htmlspecialchars($cliente['proxy_servidor'] ?? 'N/A') ?>"
                            data-proxy-exclusiones="<?= $safe_title // Valor completo para el tooltip/modal ?>">
                      <i class="bi bi-info-circle"></i> 
                    </button>
                    <button type="button"
                     title="Detalles Impresoras"
                            class="btn btn-secondary btn-sm btnVerImpresoras ms-1"
                            data-bs-toggle="modal"
                            data-bs-target="#modalImpresoras"
                            data-id="<?= htmlspecialchars($id) ?>"
                            data-impresoras='<?= htmlspecialchars(json_encode($cliente['impresoras'] ?? null)) ?>'>
                      <i class="bi bi-printer"></i> 
                    </button>
<button type="button"
        title="Detalles de Hardware"
        class="btn btn-success btn-sm btnVerHardware ms-1"
        data-bs-toggle="modal"
        data-bs-target="#modalHardware"
        data-id="<?= htmlspecialchars($id) ?>"
        data-hardware='<?= htmlspecialchars(json_encode($cliente['hardware'] ?? [])) ?>'>
  <i class="bi bi-cpu"></i> 
</button>

                    <button type="button"
                     title="Eliminar"
                            class="btn btn-danger btn-sm btnEliminarCliente ms-1"
                            data-id="<?= htmlspecialchars($id) ?>">
                      <i class="bi bi-trash"></i>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <nav aria-label="Paginación de clientes" id="paginationNav">
        <ul class="pagination justify-content-center mt-3"></ul>
      </nav>
    </form>

    <!-- Modal: Agregar/Editar ubicación -->
    <div class="modal fade" id="modalAgregarUbicacion" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form id="formAgregarUbicacion" method="POST" action="api/guardar_ubicacion.php" class="needs-validation" novalidate>
            <div class="modal-header">
              <h5 class="modal-title">Editar Ubicación</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
              <input type="hidden" name="id" id="ubicacionClienteId">
              <div class="mb-3">
                <label for="edificio" class="form-label">Edificio</label>
                <select class="form-select" id="edificio" name="edificio" required>
                  <option value="">Seleccione edificio</option>
                </select>
                <div class="invalid-feedback">Seleccione un edificio</div>
              </div>
              <div class="mb-3">
                <label for="piso" class="form-label">Piso</label>
                <select class="form-select" id="piso" name="piso" disabled required>
                  <option value="">Seleccione piso</option>
                </select>
                <div class="invalid-feedback">Seleccione un piso</div>
              </div>
              <div class="mb-3">
                <label for="servicio" class="form-label">Servicio</label>
                <select class="form-select" id="servicio" name="servicio" disabled required>
                  <option value="">Seleccione servicio</option>
                </select>
                <div class="invalid-feedback">Seleccione un servicio</div>
              </div>
              <div class="mb-3">
                <label for="oficina" class="form-label">Oficina</label>
                <input type="text" class="form-control" name="oficina" id="oficina" required>
                <div class="invalid-feedback">Ingrese la oficina</div>
              </div>
              <div class="mb-3">
                <label for="responsable" class="form-label">Responsable</label>
                <input type="text" class="form-control" name="responsable" id="responsable" required>
                <div class="invalid-feedback">Ingrese Responsable</div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-primary" id="guardarUbicacionBtn">
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="display: none;"></span>
                Guardar
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>


<!-- Modal: Hardware -->
<div class="modal fade" id="modalHardware" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Hardware de: <span id="modalHardwareId"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div id="hardwareContent">
          <p class="text-muted">Cargando...</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>  

<!-- Modal: Detalles de red -->
<div class="modal fade" id="modalDetallesRed" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalles del cliente: <span id="modalIdCliente"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <!-- 👇 AÑADIDO: table-responsive -->
        <table class="table table-sm table-responsive">
          <tbody>
            <tr><th scope="row">IP</th><td id="modalIp"></td></tr>
            <tr><th scope="row">MAC</th><td id="modalMac"></td></tr>
            <tr><th scope="row">Máscara de red</th><td id="modalMascara"></td></tr>
            <tr><th scope="row">Puerta de enlace</th><td id="modalGateway"></td></tr>
            <tr><th scope="row">DNS</th><td id="modalDns"></td></tr>
            <tr><th scope="row">Proxy activo</th><td id="modalProxyActivo"></td></tr>
            <tr><th scope="row">Servidor de proxy</th><td id="modalProxyServidor"></td></tr>
            <!-- 👇 NUEVA FILA: Exclusiones en un textarea -->
            <tr>
              <th scope="row">Exclusiones de proxy</th>
              <td>
                <textarea id="modalProxyExclusiones" 
                          class="form-control" 
                          rows="3" 
                          readonly 
                          style="width: 100%; max-width: 100%; resize: none; font-family: monospace; font-size: 0.875rem;">
                </textarea>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
    <!-- Modal: Impresoras -->
    <div class="modal fade" id="modalImpresoras" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Impresoras de: <span id="modalImpresorasId"></span></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            <div id="impresorasContent">
              <p class="text-muted">Cargando...</p>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>
    <!-- Modal: Progreso -->
    <div class="modal fade progress-modal" id="modalProgreso" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Procesando equipos</h5>
          </div>
          <div class="modal-body">
            <div class="progress" style="height: 20px;">
              <div class="progress-bar progress-bar-striped progress-bar-animated"
                   role="progressbar" style="width: 0%" aria-valuenow="0"
                   aria-valuemin="0" aria-valuemax="100">0%</div>
            </div>
            <p class="text-center mt-3">
              <span class="contador">0</span> de <span class="total">0</span> equipos procesados
            </p>
            <div class="text-center">
              <small class="text-muted estado">Iniciando...</small>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/app.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      if (typeof window.mostrarOpciones === 'function') window.mostrarOpciones();
    });
  </script>
</body>
</html>