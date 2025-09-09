<?php
// Esta vista recibe las variables: $clientes, $errorCarga, $mensajes, $csrf_token
extract($mensajes);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <link rel="icon" href="./img/favicon.ico" type="image/x-icon">
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Administración de Clientes</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="loading-overlay">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">Cargando...</span>
    </div>
  </div>

<main class="container-fluid py-4">
  <h1 class="text-center mb-4">Clientes Registrados</h1>

  <?php if ($errorCarga): ?>
    <div class="alert alert-danger">
      <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($errorCarga) ?>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-danger alert-floating">
      <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success alert-floating">
      <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if ($resultado_envio): ?>
  <div class="alert alert-success alert-floating">
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      <h5><i class="bi bi-check-circle-fill"></i> ¡Acción enviada correctamente!</h5>
      <p><strong><?= htmlspecialchars($resultado_envio['mensaje']) ?></strong></p>
      <div class="mt-2">
          <small class="text-muted">
              <strong>Acción:</strong> <?= $controller->obtenerNombreAccion($resultado_envio['accion_ejecutada']) ?> | 
              <strong>ID:</strong> <?= htmlspecialchars($resultado_envio['id_tarea']) ?> | 
              <strong>Hora:</strong> <?= htmlspecialchars($resultado_envio['timestamp']) ?>
          </small>
      </div>
      <div class="mt-2">
          <a href="api/estado_tarea.php?ids=<?= urlencode($resultado_envio['id_tarea']) ?>"
            target="_blank" class="btn btn-sm btn-outline-primary">
              <i class="bi bi-info-circle"></i> Ver estado detallado
          </a>
          <button onclick="location.reload()" class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-arrow-repeat"></i> Actualizar lista
          </button>
      </div>
  </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" action="api/enviar.php" class="needs-validation" novalidate id="mainForm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

    <div class="row mb-4">
      <div class="col-md-5 mb-3">
        <label for="fondo" class="form-label fw-bold">Archivo de fondo:</label>
        
        <div class="file-drop-area" id="fileDropArea">
          <i class="bi bi-cloud-arrow-up display-4 text-primary mb-3"></i>
          <p class="mb-2">Arrastra y suelta tu archivo aquí</p>
          <p class="text-muted small mb-3">o</p>
          <input type="file" class="form-control" name="fondo" id="fondo" 
                 accept=".jpg,.jpeg,.png,.gif,.bmp,.webp" required>
          <div class="invalid-feedback">
            Formatos aceptados: JPG, JPEG, PNG, GIF, BMP, WEBP. Tamaño máximo: 10MB.
          </div>
        </div>
        
        <div class="file-info mt-2" id="fileInfo"></div>
        <small class="text-muted">Formatos soportados: JPG, PNG, GIF, BMP, WEBP</small>
      </div>
<div class="col-md-4 mb-3">
  <label for="accion" class="form-label fw-bold">Acción:</label>
  <select class="form-select" name="accion" id="accion" required onchange="mostrarOpciones()">
      <option value="" disabled selected>Seleccione...</option>
      <option value="set_wallpaper">Cambiar fondo</option>
      <option value="lock">Bloquear cambio de fondo</option>
      <option value="unlock">Desbloquear cambio de fondo</option>
  </select>
  <div class="invalid-feedback">Por favor seleccione una acción</div>

  <!-- Estilo dentro de la misma columna -->
  <div id="estiloDiv" class="mt-3 hidden">
    <label for="estilo" class="form-label fw-bold">Estilo:</label>
    <select class="form-select" name="estilo" id="estilo">
      <option value="fill">Rellenar</option>
      <option value="fit">Ajustar</option>
      <option value="stretch">Extender</option>
      <option value="tile">Mosaico</option>
      <option value="center">Centrar</option>
    </select>
  </div>
<br>
  <button type="submit" class="btn btn-primary w-100" id="submitBtn">
            <i class="bi bi-send-fill"></i> Enviar
          </button>

         
</div>

      <div class="col-md-2 mb-3">
        <div class="action-container">
          <div class="text-center mb-3">
            <p class="fw-bold mb-1">Vista previa</p>
            <img src="" class="img-preview" id="imgPreview" alt="Vista previa" style="display: none; max-height: 320px;">
          </div>
         
        </div>
      </div>
    </div>

    <div class="col-md-12 mt-4">
      <div class="row">
        <div class="col-md-8">
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="busqueda" class="form-control" placeholder="Buscar cliente...">
            <button class="btn btn-outline-secondary" type="button" id="limpiarBusqueda">
              <i class="bi bi-x-lg"></i>
            </button>
          </div>
        </div>
        <div class="col-md-4">
          <select id="filtroRapido" class="form-select">
            <option value="">Filtro rápido...</option>
            <option value="sin-ubicacion">Sin ubicación</option>
            <option value="con-ubicacion">Con ubicación</option>
            <option value="bloqueados">Bloqueados</option>
            <option value="desbloqueados">Desbloqueados</option>
          </select>
        </div>
      </div>
    </div>

    <div class="col-md-12">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <span id="contadorClientes">Mostrando <?= count($clientes) ?> clientes</span>
        <div>
          <button type="button" class="btn btn-outline-secondary btn-sm me-2" id="btnSeleccionarPagina">
            <i class="bi bi-check-square"></i> Seleccionar página
          </button>
          <button type="button" class="btn btn-outline-secondary btn-sm" id="btnDeseleccionarTodos">
            <i class="bi bi-x-square"></i> Deseleccionar todos
          </button>
        </div>
      </div>
      <div class="table-responsive">
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
              <tr>
                <td colspan="13" class="text-center text-muted py-4">
                  <i class="bi bi-exclamation-circle"></i> No hay clientes registrados
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($clientes as $id => $cliente): ?>
                <tr>
                  <td>
                    <input type="checkbox" 
                          name="clientes[]" 
                          value="<?= htmlspecialchars($id) ?>" 
                          class="form-check-input cliente-checkbox">
                  </td>
                  <td><?= htmlspecialchars($id) ?></td>
                  <td><?= htmlspecialchars($cliente['nombre']) ?></td>
                  <td><?= htmlspecialchars($cliente['numero_serie']) ?></td>
                  <td><?= htmlspecialchars($cliente['ip']) ?></td>
                  <td><?= htmlspecialchars($cliente['mac']) ?></td>
                  <td><?= htmlspecialchars($cliente['ultima_conexion']) ?></td>
                  <td>
                    <?php
                    $pendientes = isset($cliente['pending']) && is_array($cliente['pending']) ? count($cliente['pending']) : 0;
                    echo $pendientes > 0 
                        ? "<span class='badge bg-warning'>$pendientes pendientes</span>" 
                        : "<span class='badge bg-success'>0</span>";
                    ?>
                  </td>
                  <td>
                    <span class="status-badge status-badge-<?= $cliente['bloqueado'] === 'bloqueado' ? 'bloqueado' : 'desbloqueado' ?>">
                      <?= $cliente['bloqueado'] === 'bloqueado' ? '<i class="bi bi-lock-fill"></i> Bloqueado' : '<i class="bi bi-unlock-fill"></i> Desbloqueado' ?>
                    </span>
                  </td>
                  <td><?= htmlspecialchars($cliente['edificio']) ?></td>
                  <td><?= htmlspecialchars($cliente['servicio']) ?></td>
                  <td><?= htmlspecialchars($cliente['oficina']) ?></td>
               <td>
  <button type="button" 
          class="btn btn-primary btn-sm btnAgregarUbicacion" 
          data-bs-toggle="modal" 
          data-bs-target="#modalAgregarUbicacion"
          data-id="<?= htmlspecialchars($id) ?>"
          data-edificio="<?= htmlspecialchars($cliente['edificio']) ?>"
          data-servicio="<?= htmlspecialchars($cliente['servicio']) ?>"
          data-oficina="<?= htmlspecialchars($cliente['oficina']) ?>">
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
      <nav aria-label="Paginación de clientes" id="paginationNav">
        <ul class="pagination justify-content-center mt-3">
          <!-- La paginación se generará dinámicamente con JavaScript -->
        </ul>
      </nav>
    </div>
  </form>

  <!-- Modal Agregar Ubicación -->
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

  <!-- Modal de Progreso -->
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
 
   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script> -->
  <script src="assets/js/app.js"></script>
</body>
 
</html>
