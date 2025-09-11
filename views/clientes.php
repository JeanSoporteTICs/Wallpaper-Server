<?php
// ==========================
// Entrada de datos al template
// ==========================
// La vista recibe: $clientes, $errorCarga, $mensajes, $csrf_token
// Desempaquetamos $mensajes de forma segura (sin extract)
$error           = $mensajes['error']           ?? '';
$success         = $mensajes['success']         ?? '';
$resultado_envio = $mensajes['resultado_envio'] ?? null;
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

  <!-- Estilos propios (usa .file-drop-area, .loading-overlay, .fixed-panel, .panel-content, etc.) -->
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
      <!-- Error al cargar datos iniciales -->
      <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($errorCarga) ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
      <!-- Error de operación (validaciones, backend, etc.) -->
      <div class="alert alert-danger alert-floating">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <!-- Mensaje de éxito -->
      <div class="alert alert-success alert-floating">
        <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <?php if (!empty($resultado_envio)): ?>
      <!-- Resumen del envío de acción (id de tarea, timestamp, etc.) -->
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
      <!-- CSRF -->
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

      <!-- =============================================
           FILA DE 3 COLUMNAS: ACCIÓN | PARÁMETROS | VISTA
           ============================================= -->
      <div class="row gx-1 gy-3 align-items-stretch">

        <!-- ===== Columna 1: ACCIÓN + opciones ===== -->
        <div class="col-12 col-lg-4" id="opcionesCol">
          <div class="card h-100">
            <div class="card-header bg-light">
              <i class="bi bi-send"></i>
              <strong>Acción</strong>
            </div>

            <!-- Cuerpo: selector de acción y (si aplica) estilo de wallpaper -->
            <div class="card-body fixed-panel">
              <div class="panel-content">
                <!-- Selector de acción: set_wallpaper / lock / unlock / show_message -->
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

                <!-- Estilo de wallpaper (solo visible si accion === set_wallpaper) -->
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

            <!-- Pie: botón de envío (capturado por app.js) -->
            <div class="card-footer bg-light d-grid">
              <button type="submit" id="submitBtn" class="btn btn-primary">
                <i class="bi bi-send"></i> Enviar a seleccionados
              </button>
            </div>
          </div>
        </div>

        <!-- ===== Columna 2: PARÁMETROS (elige imagen o escribe mensaje) ===== -->
        <div class="col-12 col-lg-4" id="paramCol">
          <div class="card h-100">
            <div class="card-header bg-light">
              <strong>Parámetros</strong>
            </div>

            <!-- Cuerpo fijo: dentro va el selector de imagen o el formulario de mensaje -->
            <div class="card-body fixed-panel">
              <div class="panel-content">
                <!-- Parámetros para set_wallpaper (drag & drop / click) -->
                <div id="paramWallpaper">
                  <div id="fileDropArea" class="file-drop-area">
                    <!-- Input real (oculto); se usa para enviar el archivo y para validar -->
                    <input type="file" id="fondo" name="fondo" accept="image/*" class="visually-hidden" />
                    <!-- Etiqueta clickable / zona de drop -->
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

                <!-- Parámetros para show_message (se muestra si accion === show_message) -->
                <div id="mensajeDiv" class="hidden">
                  <div class="mb-2">
                    <label for="msgTitle" class="form-label">Título (opcional)</label>
                    <input id="msgTitle" type="text" maxlength="120" class="form-control" placeholder="Título del mensaje" />
                  </div>
                  <div class="mb-2">
                    <label for="msgBody" class="form-label">Mensaje</label>
                    <!-- Achicado (rows=4); control fino via CSS si lo prefieres -->
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

        <!-- ===== Columna 3: VISTA (solo previsualización) ===== -->
        <div class="col-12 col-lg-4" id="previewCol">
          <div class="card h-100">
            <!-- Cuerpo fijo: contendrá la vista previa de la imagen (y/o mensaje si lo activas) -->
            <div class="card-body fixed-panel">
              <div class="panel-content">
                <!-- Vista previa de la imagen (visible si accion === set_wallpaper y hay archivo válido) -->
                <div id="previewWallpaper" class="hidden">
                  <img id="imgPreview" class="img-preview" alt="Vista previa" style="display:none;" />
                </div>

                <!-- Si quisieras una vista previa de mensaje, puedes reactivar este bloque:
                <div id="previewMessage" class="hidden">
                  <h5 id="pvMsgTitle" class="mb-2"></h5>
                  <div id="pvMsgBody" class="border rounded p-3 bg-light" style="min-height: 140px;"></div>
                  <small class="text-muted d-block mt-2" id="pvMsgExtra"></small>
                </div>
                -->
              </div>
            </div>
          </div>
        </div>

      </div> <!-- /row columnas -->

      <!-- =============================================
           TOOLBAR: búsqueda, filtros y selección masiva
           ============================================= -->
      <div class="row g-2 align-items-center mt-4 mb-3">
        <!-- Buscador -->
        <div class="col-12 col-md-4">
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="busqueda" class="form-control" placeholder="Buscar por ID, nombre, IP, MAC, ubicación..." />
            <button class="btn btn-outline-secondary" type="button" id="limpiarBusqueda" title="Limpiar">
              <i class="bi bi-x-circle"></i>
            </button>
          </div>
        </div>

        <!-- Filtro rápido (estado/ubicación) -->
        <div class="col-12 col-md-3">
          <select id="filtroRapido" class="form-select">
            <option value="">Todos</option>
            <option value="sin-ubicacion">Sin ubicación</option>
            <option value="con-ubicacion">Con ubicación</option>
            <option value="bloqueados">Bloqueados</option>
            <option value="desbloqueados">Desbloqueados</option>
          </select>
        </div>

        <!-- Selección masiva (visibles / deseleccionar) -->
        <div class="col-12 col-md-3 d-flex gap-2">
          <button class="btn btn-outline-primary" type="button" id="btnSeleccionarPagina" title="Seleccionar visibles">
            <i class="bi bi-check-square"></i> Seleccionar visibles
          </button>
          <button class="btn btn-outline-secondary" type="button" id="btnDeseleccionarTodos" title="Deseleccionar todos">
            <i class="bi bi-square"></i> Deseleccionar
          </button>
        </div>

        <!-- Contador (lo actualiza app.js) -->
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
              <!-- Selección global -->
              <th width="40">
                <input type="checkbox" id="checkTodos" class="form-check-input cursor-pointer" title="Seleccionar todos">
              </th>
              <!-- Cabeceras ordenables (app.js puede enganchar data-sort) -->
              <th class="sortable" data-sort="id">ID <i class="bi bi-arrow-down-up"></i></th>
              <th class="sortable" data-sort="nombre">Nombre <i class="bi bi-arrow-down-up"></i></th>
              <th class="sortable" data-sort="serie">Número de Serie <i class="bi bi-arrow-down-up"></i></th>
              <th class="sortable" data-sort="ip">IP <i class="bi bi-arrow-down-up"></i></th>
              <th class="sortable" data-sort="mac">MAC <i class="bi bi-arrow-down-up"></i></th>
              <th class="sortable" data-sort="conexion">Último Registro <i class="bi bi-arrow-down-up"></i></th>
              <th class="sortable" data-sort="pendientes">Pendientes <i class="bi bi-arrow-down-up"></i></th>
              <th class="sortable" data-sort="estado">Estado <i class="bi bi-arrow-down-up"></i></th>
              <th class="sortable" data-sort="edificio">Edificio <i class="bi bi-arrow-down-up"></i></th>
              <th class="sortable" data-sort="servicio">Servicio <i class="bi bi-arrow-down-up"></i></th>
              <th class="sortable" data-sort="oficina">Oficina <i class="bi bi-arrow-down-up"></i></th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($clientes)): ?>
              <!-- Sin datos -->
              <tr>
                <td colspan="13" class="text-center text-muted py-4">
                  <i class="bi bi-exclamation-circle"></i> No hay clientes registrados
                </td>
              </tr>
            <?php else: ?>
              <!-- Filas de clientes -->
              <?php foreach ($clientes as $id => $cliente): ?>
                <?php
                  $pendientes = (isset($cliente['pending']) && is_array($cliente['pending'])) ? count($cliente['pending']) : 0;
                  $isBloq     = ($cliente['bloqueado'] ?? '') === 'bloqueado';
                ?>
                <tr>
                  <!-- Checkbox por cliente -->
                  <td>
                    <input type="checkbox"
                           name="clientes[]"
                           value="<?= htmlspecialchars($id) ?>"
                           class="form-check-input cliente-checkbox">
                  </td>

                  <!-- Datos principales -->
                  <td><?= htmlspecialchars($id) ?></td>
                  <td><?= htmlspecialchars($cliente['nombre'] ?? '') ?></td>
                  <td><?= htmlspecialchars($cliente['numero_serie'] ?? '') ?></td>
                  <td><?= htmlspecialchars($cliente['ip'] ?? '') ?></td>
                  <td><?= htmlspecialchars($cliente['mac'] ?? '') ?></td>
                  <td><?= htmlspecialchars($cliente['ultima_conexion'] ?? '') ?></td>

                  <!-- Pendientes -->
                  <td>
                    <?= $pendientes > 0
                      ? "<span class='badge bg-warning'>$pendientes pendientes</span>"
                      : "<span class='badge bg-success'>0</span>" ?>
                  </td>

                  <!-- Estado (bloqueado/desbloqueado) -->
                  <td>
                    <span class="status-badge status-badge-<?= $isBloq ? 'bloqueado' : 'desbloqueado' ?>">
                      <?= $isBloq ? '<i class="bi bi-lock-fill"></i> Bloqueado' : '<i class="bi bi-unlock-fill"></i> Desbloqueado' ?>
                    </span>
                  </td>

                  <!-- Ubicación -->
                  <td><?= htmlspecialchars($cliente['edificio'] ?? '-') ?></td>
                  <td><?= htmlspecialchars($cliente['servicio'] ?? '-') ?></td>
                  <td><?= htmlspecialchars($cliente['oficina'] ?? '-') ?></td>

                  <!-- Acciones por fila (abrir modal de ubicación / eliminar) -->
                  <td class="text-nowrap">
                    <button type="button"
                            class="btn btn-primary btn-sm btnAgregarUbicacion"
                            data-bs-toggle="modal"
                            data-bs-target="#modalAgregarUbicacion"
                            data-id="<?= htmlspecialchars($id) ?>"
                            data-edificio="<?= htmlspecialchars($cliente['edificio'] ?? '') ?>"
                            data-servicio="<?= htmlspecialchars($cliente['servicio'] ?? '') ?>"
                            data-oficina="<?= htmlspecialchars($cliente['oficina'] ?? '') ?>">
                      <i class="bi bi-geo-alt"></i> Ubicación
                    </button>
                    <button type="button"
                            class="btn btn-danger btn-sm btnEliminarCliente ms-1"
                            data-id="<?= htmlspecialchars($id) ?>">
                      <i class="bi bi-trash"></i> Eliminar
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Paginación (se genera desde app.js) -->
      <nav aria-label="Paginación de clientes" id="paginationNav">
        <ul class="pagination justify-content-center mt-3"></ul>
      </nav>
    </form>

    <!-- =============================================
         MODAL: Agregar / Editar ubicación
         ============================================= -->
    <div class="modal fade" id="modalAgregarUbicacion" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form id="formAgregarUbicacion" method="POST" action="api/guardar_ubicacion.php" class="needs-validation" novalidate>
            <div class="modal-header">
              <h5 class="modal-title">Editar Ubicación</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
              <!-- CSRF + ID del cliente -->
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
              <input type="hidden" name="id" id="ubicacionClienteId">

              <!-- Selects dependientes (edificio → piso → servicio) -->
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

    <!-- =============================================
         MODAL: Progreso de procesamiento
         ============================================= -->
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

  <!-- ==========================
       SCRIPTS
       ========================== -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/app.js"></script>
  <script>
    // Al cargar la página, ajusta visibilidad de bloques según la acción seleccionada
    document.addEventListener('DOMContentLoaded', () => {
      if (typeof window.mostrarOpciones === 'function') window.mostrarOpciones();
    });
  </script>
</body>
</html>
