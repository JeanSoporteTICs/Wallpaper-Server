// app.js – versión optimizada
document.addEventListener('DOMContentLoaded', () => {
  // ------------------------------
  // Referencias y estado
  // ------------------------------
  const modalUbicacion = new bootstrap.Modal(document.getElementById('modalAgregarUbicacion'));
  const modalProgreso  = new bootstrap.Modal(document.getElementById('modalProgreso'));
  const fileDropArea   = document.getElementById('fileDropArea');
  const fileInput      = document.getElementById('fondo');
  const imgPreview     = document.getElementById('imgPreview');
  const fileInfo       = document.getElementById('fileInfo');

  let datosUbicacion = [];
  let allClientes = [];
  let filteredClientes = [];
  let currentPage = 1;
  const rowsPerPage = 10;
  let sortColumn = 'id';
  let sortDirection = 'asc';
  let intervaloProgreso = null;

  // ------------------------------
  // Init
  // ------------------------------
  function init() {
    initClientesDesdeTabla();
    initDragAndDrop();
    cargarDatosUbicaciones().then(initSelectsUbicacion);
    initEventListeners();
    setupPagination();
    mostrarOpciones();
  }

  // ------------------------------
  // Utilidades UI
  // ------------------------------
  function mostrarAlerta(mensaje, tipo, tiempo = 5000) {
    const alerta = document.createElement('div');
    alerta.className = `alert alert-${tipo} alert-dismissible fade show alert-floating`;
    alerta.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 1060; min-width: 300px; margin-bottom: 10px;';
    alerta.setAttribute('role', 'alert');
    alerta.innerHTML = `
      <span>${mensaje}</span>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;

    let alertContainer = document.getElementById('alert-container');
    if (!alertContainer) {
      alertContainer = document.createElement('div');
      alertContainer.id = 'alert-container';
      alertContainer.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 1060;';
      document.body.appendChild(alertContainer);
    }

    alertContainer.appendChild(alerta);

    const alertas = alertContainer.querySelectorAll('.alert-floating');
    alertas.forEach((a, i) => { a.style.top = `${20 + (i * 70)}px`; });

    const bsAlert = new bootstrap.Alert(alerta);
    setTimeout(() => { if (alerta.isConnected) bsAlert.close(); }, tiempo);

    alerta.addEventListener('closed.bs.alert', () => {
      setTimeout(() => {
        alerta.remove();
        const restantes = alertContainer.querySelectorAll('.alert-floating');
        restantes.forEach((a, i) => { a.style.top = `${20 + (i * 70)}px`; });
        if (restantes.length === 0) alertContainer.remove();
      }, 300);
    });
  }

  const debounce = (fn, wait) => {
    let t;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...args), wait);
    };
  };

  const normalizeText = (t) =>
    String(t).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

  // ------------------------------
  // Event Listeners
  // ------------------------------
  function initEventListeners() {
    // Búsqueda / Filtro
    document.getElementById('busqueda').addEventListener('input', debounce(buscarClientes, 300));
    document.getElementById('limpiarBusqueda').addEventListener('click', limpiarBusqueda);
    document.getElementById('filtroRapido').addEventListener('change', aplicarFiltroRapido);

    // Selección
    document.getElementById('checkTodos').addEventListener('change', toggleSeleccionTodos);
    document.getElementById('btnSeleccionarPagina').addEventListener('click', seleccionarPaginaActual);
    document.getElementById('btnDeseleccionarTodos').addEventListener('click', deseleccionarTodos);

    // Formularios
    document.getElementById('mainForm').addEventListener('submit', manejarEnvioFormularioPrincipal);
    document.getElementById('formAgregarUbicacion').addEventListener('submit', manejarEnvioFormularioUbicacion);

    // Ordenamiento
    document.querySelectorAll('.sortable').forEach(header => {
      header.addEventListener('click', () => ordenarTabla(header.dataset.sort));
    });

    // Botón de ubicación (abre modal precargado)
    document.querySelectorAll('.btnAgregarUbicacion').forEach(btn => {
      btn.addEventListener('click', () => {
        prepararModalUbicacion(btn.dataset.id, btn.dataset.edificio, btn.dataset.servicio, btn.dataset.oficina);
      });
    });

    // Cambio de acción
    const accionSelect = document.getElementById('accion');
    accionSelect.addEventListener('change', () => {
      if (fileInput.files.length > 0 && accionSelect.value !== 'set_wallpaper') {
        if (!confirm('Al cambiar de acción se perderá la imagen seleccionada. ¿Continuar?')) {
          accionSelect.value = 'set_wallpaper';
          mostrarOpciones();
          return;
        }
        limpiarSoloArchivo();
        mostrarOpciones();
      } else {
        mostrarOpciones();
      }
    });
    // Eliminar cliente (por fila)
  document.querySelectorAll('.btnEliminarCliente').forEach(btn => {
    btn.addEventListener('click', () => onEliminarCliente(btn));
  });
  }
  // ------------------------------
  // Drag & Drop archivo
  // ------------------------------
  function initDragAndDrop() {
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(evt => {
      fileDropArea.addEventListener(evt, (e) => { e.preventDefault(); e.stopPropagation(); }, false);
    });
    ['dragenter', 'dragover'].forEach(evt => fileDropArea.addEventListener(evt, () => fileDropArea.classList.add('active')));
    ['dragleave', 'drop'].forEach(evt => fileDropArea.addEventListener(evt, () => fileDropArea.classList.remove('active')));

    fileDropArea.addEventListener('drop', (e) => handleFiles(e.dataTransfer.files));
    fileInput.addEventListener('change', () => handleFiles(fileInput.files));
  }

  function handleFiles(files) {
    if (!files || !files.length) return;
    const file = files[0];

    const size = file.size > 1024 * 1024
      ? (file.size / (1024 * 1024)).toFixed(2) + ' MB'
      : (file.size / 1024).toFixed(2) + ' KB';
    fileInfo.innerHTML = `<i class="bi bi-file-image"></i> ${file.name} (${size})`;

    // Auto-seleccionar la acción de cambiar fondo
    const accion = document.getElementById('accion');
    accion.value = 'set_wallpaper';
    mostrarOpciones();

    if (file.type.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = (ev) => {
        imgPreview.src = ev.target.result;
        imgPreview.style.display = 'block';
      };
      reader.readAsDataURL(file);
    } else {
      imgPreview.src = '';
      imgPreview.style.display = 'none';
    }
  }

  // ------------------------------
  // Opciones según acción
  // ------------------------------
  window.mostrarOpciones = function () {
    const accion = document.getElementById('accion').value;
    const estiloDiv = document.getElementById('estiloDiv');
    const fondo = document.getElementById('fondo');

    if (accion === 'set_wallpaper') {
      estiloDiv.classList.remove('hidden');
      fondo.required = true;
    } else {
      estiloDiv.classList.add('hidden');
      fondo.required = false;
    }
  };

  function validarArchivoWallpaper() {
    const archivoInput = document.getElementById('fondo');
    if (archivoInput.files.length === 0) {
      mostrarAlerta('Por favor seleccione un archivo de imagen', 'warning');
      return false;
    }
    const archivo = archivoInput.files[0];
    const tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];
    if (!tiposPermitidos.includes(archivo.type)) {
      mostrarAlerta('Tipo de archivo no permitido. Use JPG, PNG, GIF, BMP o WEBP.', 'danger');
      return false;
    }
    if (archivo.size > 10 * 1024 * 1024) {
      mostrarAlerta('El archivo es demasiado grande. Tamaño máximo: 10MB.', 'danger');
      return false;
    }
    return true;
  }

  // ------------------------------
  // Ubicaciones (selects dependientes)
  // ------------------------------
  async function cargarDatosUbicaciones() {
    try {
      const res = await fetch('data/ubicaciones.json');
      if (!res.ok) throw new Error(`Error HTTP ${res.status}`);
      datosUbicacion = await res.json();
      return datosUbicacion;
    } catch (e) {
      console.error('Error cargando ubicaciones:', e);
      mostrarAlerta('Error al cargar datos de ubicaciones', 'danger');
      return [];
    }
  }

  function initSelectsUbicacion() {
    const selE = document.getElementById('edificio');
    const selP = document.getElementById('piso');
    const selS = document.getElementById('servicio');

    selE.innerHTML = '<option value="">Seleccione edificio</option>';
    selP.innerHTML = '<option value="">Seleccione piso</option>'; selP.disabled = true;
    selS.innerHTML = '<option value="">Seleccione servicio</option>'; selS.disabled = true;

    datosUbicacion.forEach(e => selE.appendChild(new Option(e.edificio, e.edificio)));

    selE.addEventListener('change', () => actualizarSelectPiso(selE.value, selP, selS));
    selP.addEventListener('change', () => actualizarSelectServicio(selE.value, selP.value, selS));
  }

  function actualizarSelectPiso(edificioSeleccionado, selP, selS) {
    selP.innerHTML = '<option value="">Seleccione piso</option>';
    selS.innerHTML = '<option value="">Seleccione servicio</option>'; selS.disabled = true;

    if (!edificioSeleccionado) { selP.disabled = true; return; }

    const edificio = datosUbicacion.find(e => e.edificio === edificioSeleccionado);
    if (!edificio || !edificio.pisos) { selP.disabled = true; return; }

    const pisosOrdenados = Object.keys(edificio.pisos).sort((a, b) => {
      const na = parseInt(a.replace(/\D/g, '')) || 0;
      const nb = parseInt(b.replace(/\D/g, '')) || 0;
      return na - nb;
    });
    pisosOrdenados.forEach(p => selP.appendChild(new Option(p, p)));
    selP.disabled = false;
  }

  function actualizarSelectServicio(edificioSeleccionado, pisoSeleccionado, selS) {
    selS.innerHTML = '<option value="">Seleccione servicio</option>';
    selS.disabled = true;

    if (!pisoSeleccionado) return;
    const edificio = datosUbicacion.find(e => e.edificio === edificioSeleccionado);
    if (!edificio || !edificio.pisos || !edificio.pisos[pisoSeleccionado]) return;

    const servicios = [...edificio.pisos[pisoSeleccionado]].sort();
    servicios.forEach(s => selS.appendChild(new Option(s, s)));
    selS.disabled = false;
  }

  function prepararModalUbicacion(clienteId, edificio, servicio, oficina) {
    document.getElementById('ubicacionClienteId').value = clienteId;
    document.getElementById('oficina').value = oficina || '';

    const selE = document.getElementById('edificio');
    const selP = document.getElementById('piso');
    const selS = document.getElementById('servicio');

    selE.value = '';
    selP.innerHTML = '<option value="">Seleccione piso</option>'; selP.disabled = true;
    selS.innerHTML = '<option value="">Seleccione servicio</option>'; selS.disabled = true;

    if (!edificio) return;

    // Selecciona edificio y propaga
    selE.value = edificio;
    actualizarSelectPiso(edificio, selP, selS);

    if (servicio) {
      const edificioData = datosUbicacion.find(e => e.edificio === edificio);
      if (edificioData && edificioData.pisos) {
        for (const [piso, servicios] of Object.entries(edificioData.pisos)) {
          if (servicios.includes(servicio)) {
            selP.value = piso;
            actualizarSelectServicio(edificio, piso, selS);
            selS.value = servicio;
            break;
          }
        }
      }
    }
  }

  async function manejarEnvioFormularioUbicacion(e) {
    e.preventDefault();
    const form = e.currentTarget;
    const btnGuardar = document.getElementById('guardarUbicacionBtn');
    const spinner = btnGuardar.querySelector('.spinner-border');

    if (!form.checkValidity()) {
      e.stopPropagation();
      form.classList.add('was-validated');
      return;
    }

    btnGuardar.disabled = true;
    spinner.style.display = 'inline-block';

    try {
      const res = await fetch(form.action, { method: 'POST', body: new FormData(form) });
      const data = await res.json();

      if (res.ok && data.success) {
        mostrarAlerta('Ubicación guardada correctamente', 'success');
        modalUbicacion.hide();
        setTimeout(() => location.reload(), 800);
      } else {
        mostrarAlerta(data.message || 'Error al guardar la ubicación', 'danger');
      }
    } catch (err) {
      console.error(err);
      mostrarAlerta('Error de conexión', 'danger');
    } finally {
      btnGuardar.disabled = false;
      spinner.style.display = 'none';
    }
  }

  // ------------------------------
  // Tabla de clientes (memoria, orden, filtro, paginación)
  // ------------------------------
  function initClientesDesdeTabla() {
    const filas = Array.from(document.querySelectorAll('#clientesTable tbody tr'));
    allClientes = filas.filter(tr => !tr.querySelector('td[colspan]'));
    filteredClientes = [...allClientes];

    allClientes.forEach(fila => {
      const td = fila.querySelectorAll('td');
      fila._data = {
        id: td[1]?.textContent ?? '',
        nombre: td[2]?.textContent ?? '',
        serie: td[3]?.textContent ?? '',
        ip: td[4]?.textContent ?? '',
        mac: td[5]?.textContent ?? '',
        conexion: td[6]?.textContent ?? '',
        pendientes: td[7]?.textContent ?? '',
        estado: td[8]?.textContent ?? '',
        edificio: td[9]?.textContent ?? '',
        servicio: td[10]?.textContent ?? '',
        oficina: td[11]?.textContent ?? ''
      };
    });

    actualizarContadorClientes();
  }

  function aplicarFiltroRapido() {
    const filtro = document.getElementById('filtroRapido').value;
    switch (filtro) {
      case 'sin-ubicacion':
        filteredClientes = allClientes.filter(f => !f._data.edificio || f._data.edificio === '-' || !f._data.servicio || f._data.servicio === '-');
        break;
      case 'con-ubicacion':
        filteredClientes = allClientes.filter(f => f._data.edificio && f._data.edificio !== '-' && f._data.servicio && f._data.servicio !== '-');
        break;
      case 'bloqueados':
        filteredClientes = allClientes.filter(f => f._data.estado.includes('Bloqueado'));
        break;
      case 'desbloqueados':
        filteredClientes = allClientes.filter(f => f._data.estado.includes('Desbloqueado'));
        break;
      default:
        filteredClientes = [...allClientes];
    }
    currentPage = 1;
    renderClientes();
    setupPagination();
    actualizarContadorClientes();
  }

  function buscarClientes() {
    const filtro = normalizeText(document.getElementById('busqueda').value);
    if (!filtro) {
      filteredClientes = [...allClientes];
    } else {
      filteredClientes = allClientes.filter(fila => {
        const texto = Object.values(fila._data).map(v => normalizeText(v)).join(' ');
        return texto.includes(filtro);
      });
    }
    currentPage = 1;
    renderClientes();
    setupPagination();
    actualizarContadorClientes();
  }

  function limpiarBusqueda() {
    document.getElementById('busqueda').value = '';
    buscarClientes();
  }

  function aplicarOrdenamiento() {
    filteredClientes.sort((a, b) => {
      let va = String(a._data[sortColumn] ?? '').toLowerCase();
      let vb = String(b._data[sortColumn] ?? '').toLowerCase();
      if (va < vb) return sortDirection === 'asc' ? -1 : 1;
      if (va > vb) return sortDirection === 'asc' ? 1 : -1;
      return 0;
    });
  }

  function ordenarTabla(columna) {
    if (sortColumn === columna) {
      sortDirection = (sortDirection === 'asc') ? 'desc' : 'asc';
    } else {
      sortColumn = columna;
      sortDirection = 'asc';
    }
    document.querySelectorAll('.sortable i').forEach(i => i.className = 'bi bi-arrow-down-up');
    const icon = document.querySelector(`[data-sort="${columna}"] i`);
    if (icon) icon.className = sortDirection === 'asc' ? 'bi bi-sort-down' : 'bi bi-sort-up';

    renderClientes();
    setupPagination();
  }

  function renderClientes() {
    const tbody = document.querySelector('#clientesTable tbody');

    // Eliminar posibles "no-results"
    tbody.querySelectorAll('tr.no-results').forEach(e => e.remove());

    // Ocultar todas
    allClientes.forEach(tr => { tr.style.display = 'none'; });

    if (filteredClientes.length === 0) {
      const tr = document.createElement('tr');
      tr.className = 'no-results text-center text-muted';
      tr.innerHTML = '<td colspan="13" class="py-3"><i class="bi bi-search"></i> No se encontraron coincidencias</td>';
      tbody.appendChild(tr);
      return;
    }

    aplicarOrdenamiento();

    // Mostrar sólo filtradas (paginación se encarga de ventanas)
    filteredClientes.forEach(tr => { tr.style.display = ''; });
    mostrarPagina(currentPage);
  }

 function setupPagination() {
  const pageCount = Math.ceil(filteredClientes.length / rowsPerPage) || 1;
  if (currentPage > pageCount) currentPage = pageCount;  // 👈 clamp

  const nav = document.querySelector('#paginationNav ul');
  nav.innerHTML = '';

  if (pageCount <= 1) {
    document.getElementById('paginationNav').style.display = 'none';
    mostrarPagina(1); // asegura render
    return;
  }
  document.getElementById('paginationNav').style.display = 'block';

    const addPageItem = (label, disabled, onClick, aria = '') => {
      const li = document.createElement('li');
      li.className = `page-item ${disabled ? 'disabled' : ''}`;
      li.innerHTML = `<a class="page-link" href="#" ${aria}>${label}</a>`;
      if (!disabled) li.addEventListener('click', (e) => { e.preventDefault(); onClick(); });
      nav.appendChild(li);
    };

    addPageItem('&laquo;', currentPage === 1, () => { currentPage--; mostrarPagina(currentPage); setupPagination(); }, 'aria-label="Anterior"');

    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(pageCount, startPage + 4);
    for (let i = startPage; i <= endPage; i++) {
      const li = document.createElement('li');
      li.className = `page-item ${i === currentPage ? 'active' : ''}`;
      li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
      li.addEventListener('click', (e) => { e.preventDefault(); currentPage = i; mostrarPagina(currentPage); setupPagination(); });
      nav.appendChild(li);
    }

    addPageItem('&raquo;', currentPage === pageCount, () => { currentPage++; mostrarPagina(currentPage); setupPagination(); }, 'aria-label="Siguiente"');

    mostrarPagina(currentPage);
  }

  function mostrarPagina(page) {
    const start = (page - 1) * rowsPerPage;
    const end = start + rowsPerPage;

    filteredClientes.forEach((tr, idx) => {
      tr.style.display = (idx >= start && idx < end) ? '' : 'none';
    });
    actualizarCheckTodos();
  }

  function actualizarContadorClientes() {
    const contador = document.getElementById('contadorClientes');
    const seleccionados = document.querySelectorAll('.cliente-checkbox:checked').length;
    const total = allClientes.length;
    const visible = filteredClientes.length;
    contador.textContent = (visible === total)
      ? `Mostrando ${total} clientes${seleccionados ? ` (${seleccionados} seleccionados)` : ''}`
      : `Mostrando ${visible} de ${total} clientes${seleccionados ? ` (${seleccionados} seleccionados)` : ''}`;
  }

  // ------------------------------
  // Selección de clientes
  // ------------------------------
  function toggleSeleccionTodos() {
    const checked = this.checked;
    document.querySelectorAll('#clientesTable tbody tr').forEach(tr => {
      if (tr.style.display !== 'none') {
        const cb = tr.querySelector('.cliente-checkbox');
        if (cb) cb.checked = checked;
      }
    });
    actualizarCheckTodos();
    actualizarContadorClientes();
  }

  function seleccionarPaginaActual() {
    document.querySelectorAll('#clientesTable tbody tr').forEach(tr => {
      if (tr.style.display !== 'none') {
        const cb = tr.querySelector('.cliente-checkbox');
        if (cb) cb.checked = true;
      }
    });
    actualizarCheckTodos();
    actualizarContadorClientes();
  }

  function deseleccionarTodos() {
    document.querySelectorAll('.cliente-checkbox').forEach(cb => cb.checked = false);
    actualizarCheckTodos();
    actualizarContadorClientes();
  }

  function actualizarCheckTodos() {
    const visibles = Array.from(document.querySelectorAll('#clientesTable tbody tr')).filter(tr => tr.style.display !== 'none');
    const visiblesCB = visibles.map(tr => tr.querySelector('.cliente-checkbox')).filter(Boolean);
    const some = visiblesCB.some(cb => cb.checked);
    const all  = visiblesCB.length > 0 && visiblesCB.every(cb => cb.checked);
    const master = document.getElementById('checkTodos');
    master.checked = all;
    master.indeterminate = !all && some;
  }

  // ------------------------------
  // Envío principal (AJAX a api/enviar.php)
  // ------------------------------
 async function manejarEnvioFormularioPrincipal(e) {
  e.preventDefault();

  const seleccionados = Array.from(document.querySelectorAll('.cliente-checkbox:checked'));
  const total = seleccionados.length;
  if (total === 0) { mostrarAlerta('Seleccione al menos un cliente', 'warning'); return; }

  const accion = document.getElementById('accion').value;
  if (!accion) { mostrarAlerta('Seleccione una acción', 'warning'); return; }
  if (accion === 'set_wallpaper' && !validarArchivoWallpaper()) return;

  // Mostrar SIEMPRE el modal y ocultar overlay para no taparlo
  document.querySelector('.loading-overlay').style.display = 'none';
  document.getElementById('submitBtn').disabled = true;

  abrirModalProgreso('Preparando envío...', 0, 0, total);
  await animateProgressTo(15, 300); // animación suave

  try {
    // Armar FormData con TODOS los clientes (1 → 1000)
    const form = document.getElementById('mainForm');
    const fd = new FormData();
    fd.append('csrf_token', form.querySelector('input[name="csrf_token"]').value);
    fd.append('accion', accion);

    const estilo = document.getElementById('estilo');
    if (accion === 'set_wallpaper' && estilo) fd.append('estilo', estilo.value);

    const archivo = document.getElementById('fondo').files[0];
    if (accion === 'set_wallpaper' && archivo) fd.append('fondo', archivo);

    // MUY IMPORTANTE: adjuntar todos los clientes seleccionados
    for (const cb of seleccionados) fd.append('clientes[]', cb.value);

    actualizarModalProgreso(20, 'Enviando al servidor...');
    await animateProgressTo(35, 500);

    // ÚNICA llamada al backend
    const res = await fetch(form.action, { method: 'POST', body: fd });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();

    if (data.error) {
      actualizarModalProgreso(100, 'Error');
      setTimeout(() => { cerrarModalProgreso(); }, 600);
      mostrarAlerta(data.error, 'danger');
      document.getElementById('submitBtn').disabled = false;
      return;
    }

    // Si todo fue inmediato (sin pendientes), cerrar rápido
    const comandoIds = extraerComandoIds(data);
    if (!comandoIds.length) {
      actualizarModalProgreso(100, 'Completado');
      setTimeout(() => {
        cerrarModalProgreso();
        mostrarAlerta(generarMensajeResultado(data), 'success');
        limpiarFormularioCompleto();
        document.getElementById('submitBtn').disabled = false;
      }, 800);
      return;
    }

    // Hay pendientes → progreso real con polling
    // Hay pendientes → progreso real con polling
actualizarModalProgreso(50, `En cola (${comandoIds.length})...`);

monitorearProgresoMultiples(
  comandoIds,
  (finalData, meta) => {
    const completados = Number(finalData?.completados ?? 0);
    const total       = Number(finalData?.total ?? comandoIds.length);
    const pct         = total > 0 ? Math.round((completados / total) * 100) : 0;

    const titulo = (meta?.timedOut || meta?.stalled) ? 'Finalizado (incompleto)' : 'Completado';
    actualizarModalProgreso(pct, titulo, completados, total);

    const resumen = generarResumenTareas(finalData);
    setTimeout(() => {
      cerrarModalProgreso();
      mostrarAlerta(resumen, (meta?.timedOut || meta?.stalled) ? 'warning' : 'success', 10000);
      limpiarFormularioCompleto();
      document.getElementById('submitBtn').disabled = false;
    }, 800);
  },
  {
    // Consulta cada 2s
    pollInterval: 2000,

    // Deja que el monitor calcule timeout/idle coherentes con expirySec
    expirySec: 45
  }
);


  } catch (err) {
    console.error(err);
    actualizarModalProgreso(100, 'Error');
    setTimeout(() => { cerrarModalProgreso(); }, 600);
    mostrarAlerta(`Error al procesar la solicitud: ${err.message}`, 'danger');
    document.getElementById('submitBtn').disabled = false;
  }
}

  
// });


function removeClientRowAndRefresh(tr) {
  // Elimina de arrays de memoria usados por filtros/orden/paginación
  const idxAll = allClientes.indexOf(tr);
  if (idxAll >= 0) allClientes.splice(idxAll, 1);

  const idxFilt = filteredClientes.indexOf(tr);
  if (idxFilt >= 0) filteredClientes.splice(idxFilt, 1);

  tr.remove();
  // Re-render y actualizar UI
  renderClientes();
  setupPagination();
  actualizarContadorClientes();
}



  function generarMensajeResultado(data) {
    if (!data || !data.resultados) return 'Acción completada.';
    let exito = 0, pendiente = 0, fallo = 0;
    Object.values(data.resultados).forEach(r => {
      if (r.estado === 'exito_webhook') exito++;
      else if (r.estado === 'pendiente') pendiente++;
      else if (r.estado === 'fallo') fallo++;
    });
    const total = Object.keys(data.resultados).length;
    let msg = `Procesado: ${total} clientes. `;
    if (exito) msg += `${exito} ejecutados inmediatamente. `;
    if (pendiente) msg += `${pendiente} en cola pendiente. `;
    if (fallo) msg += `${fallo} con errores.`;
    return msg.trim();
  }

  function extraerComandoIds(data) {
    const ids = [];
    if (data && data.resultados && typeof data.resultados === 'object') {
      for (const k in data.resultados) {
        const r = data.resultados[k];
        if (r && r.comando_id) ids.push(r.comando_id);
      }
    }
    return ids;
  }

  // ------------------------------
  // Progreso (polling múltiple)
  // ------------------------------
function monitorearProgresoMultiples(comandoIds, onComplete, opts = {}) {
  let {
    pollInterval = 2000,   // ms
    timeoutMs,             // si no se pasa, se calcula
    maxIdlePolls,          // si no se pasa, se calcula
    expirySec = 45
  } = opts;

  if (!Array.isArray(comandoIds) || comandoIds.length === 0) {
    if (typeof onComplete === 'function') onComplete({ total: 0, completados: 0, detalles: {} }, { timedOut:false, stalled:false });
    return;
  }

  // Evitar monitores duplicados
  if (window.__progresoActive) return;
  window.__progresoActive = true;

  const pollSec = Math.max(1, Math.round(pollInterval / 1000));
  if (maxIdlePolls == null) maxIdlePolls = Math.ceil(expirySec / pollSec) + 2;
  if (timeoutMs == null)    timeoutMs    = (expirySec + 30) * 1000;

  const urlBase = `api/estado_tarea.php?ids=${encodeURIComponent(comandoIds.join(','))}&expiry=${encodeURIComponent(expirySec)}`;

  let startTime = Date.now();
  let lastDone  = 0;
  let idleCount = 0;
  let aborted   = false;

  // Cancelación limpia al cerrar el modal
  const modalEl = document.getElementById('modalProgreso');
  const stopOnHide = () => { aborted = true; modalEl.removeEventListener('hidden.bs.modal', stopOnHide); };
  modalEl.addEventListener('hidden.bs.modal', stopOnHide);

  const sleep = (ms) => new Promise(r => setTimeout(r, ms));

  (async function loop() {
    try {
      while (!aborted) {
        const controller = new AbortController();
        const to = setTimeout(() => controller.abort(), Math.max(8000, pollInterval)); // timeout de red defensivo

        let lastData;
        try {
          const res = await fetch(urlBase, { cache: 'no-store', signal: controller.signal });
          lastData = await res.json();
        } finally {
          clearTimeout(to);
        }

        const total = Number(lastData.total ?? comandoIds.length);
        const done  = Number(lastData.completados ?? 0);

        // no bajar el porcentaje si el backend responde algo menor
        const barNow = parseInt(document.querySelector('#modalProgreso .progress-bar')?.getAttribute('aria-valuenow') || '0', 10);
        const computedPct = (typeof lastData.progreso === 'number')
          ? Math.max(0, Math.min(100, Math.round(lastData.progreso)))
          : (total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0);
        const pct = Math.max(barNow, computedPct);

        const estadoTxt = `Estado: ${lastData.estado || 'procesando'} (${done}/${total})`;
        actualizarModalProgreso(pct, estadoTxt, done, total);

        if (done > lastDone) { idleCount = 0; lastDone = done; } else { idleCount++; }

        const finished = (pct >= 100) || (lastData.estado && String(lastData.estado).toLowerCase() === 'completado');
        const timedOut = (Date.now() - startTime) > timeoutMs;
        const stalled  = idleCount >= maxIdlePolls;

        if (finished || timedOut || stalled) {
          window.__progresoActive = false;
          if (timedOut) mostrarAlerta('Monitoreo finalizado por timeout.', 'warning', 6000);
          if (stalled)  mostrarAlerta('Monitoreo finalizado por estancamiento.', 'warning', 6000);
          if (typeof onComplete === 'function') onComplete(lastData || { total, completados: done, detalles: {} }, { timedOut, stalled });
          return;
        }

        await sleep(pollInterval); // ← evita solapamientos (no hay setInterval)
      }
    } catch (err) {
      console.error('Error consultando estado:', err);
      window.__progresoActive = false;
      mostrarAlerta('Error consultando el estado de las tareas.', 'danger', 6000);
      if (typeof onComplete === 'function') onComplete({ total: comandoIds.length, completados: 0, detalles: {} }, { error:true });
    }
  })();
}



function generarResumenTareas(data) {
  if (!data || !data.detalles) return "Sin información de tareas.";

  const estados = {
    completado: 0,
    expirado: 0,
    no_encontrado: 0,
    fallo: 0,
    cancelado: 0,
    pendiente: 0
  };

  Object.values(data.detalles).forEach(cmd => {
    const estado = (cmd.estado || "pendiente").toLowerCase();
    if (estados.hasOwnProperty(estado)) {
      estados[estado]++;
    } else {
      estados.pendiente++;
    }
  });

  const total = Object.keys(data.detalles).length;
  let resumen = `Resumen de ${total} comandos:\n`;

  if (estados.completado > 0) resumen += `✅ ${estados.completado} completados\n`;
  if (estados.expirado > 0) resumen += `⚠️ ${estados.expirado} expirados (cliente sin respuesta)\n`;
  if (estados.no_encontrado > 0) resumen += `⚠️ ${estados.no_encontrado} no encontrados en el agente\n`;
  if (estados.fallo > 0) resumen += `❌ ${estados.fallo} fallidos\n`;
  if (estados.cancelado > 0) resumen += `⛔ ${estados.cancelado} cancelados\n`;
  if (estados.pendiente > 0) resumen += `⌛ ${estados.pendiente} aún pendientes\n`;

  return resumen.trim();
}


function abrirModalProgreso(texto, pct = 0, completados = 0, total = 0) {
  const el = document.getElementById('modalProgreso');
  el.querySelector('.progress-bar').style.width = `${pct}%`;
  el.querySelector('.progress-bar').textContent = `${pct}%`;
  el.querySelector('.progress-bar').setAttribute('aria-valuenow', String(pct));
  el.querySelector('.contador').textContent = String(completados);
  el.querySelector('.total').textContent = String(total);
  el.querySelector('.estado').textContent = texto;
  (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show();
}
function actualizarModalProgreso(pct, texto, completados = null, total = null) {
  const el = document.getElementById('modalProgreso');
  const bar = el.querySelector('.progress-bar');
  const p = Math.max(0, Math.min(100, Math.round(pct)));
  bar.style.width = `${p}%`;
  bar.textContent = `${p}%`;
  bar.setAttribute('aria-valuenow', String(p));
  if (completados !== null) el.querySelector('.contador').textContent = String(completados);
  if (total !== null) el.querySelector('.total').textContent = String(total);
  if (texto) el.querySelector('.estado').textContent = texto;
}
function cerrarModalProgreso() {
  const el = document.getElementById('modalProgreso');
  (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).hide();
}
async function animateProgressTo(targetPct, durationMs = 400) {
  const el = document.getElementById('modalProgreso');
  const bar = el.querySelector('.progress-bar');
  const start = parseInt(bar.getAttribute('aria-valuenow') || '0', 10);
  const end = Math.max(start, Math.min(100, targetPct));
  const startTs = performance.now();

  return new Promise(resolve => {
    function tick(now) {
      const t = Math.min(1, (now - startTs) / durationMs);
      const cur = Math.round(start + (end - start) * t);
      bar.style.width = `${cur}%`;
      bar.textContent = `${cur}%`;
      bar.setAttribute('aria-valuenow', String(cur));
      if (t < 1) requestAnimationFrame(tick); else resolve();
    }
    requestAnimationFrame(tick);
  });
}


  // ------------------------------
  // Limpiezas
  // ------------------------------
  function limpiarSoloArchivo() {
    fileInput.value = '';
    imgPreview.src = '';
    imgPreview.style.display = 'none';
    fileInfo.innerHTML = '<i class="bi bi-cloud-upload"></i> Arrastra una imagen o haz clic aquí';
  }

  function limpiarFormularioCompleto() {
    // 1) archivo
    limpiarSoloArchivo();

    // 2) checkboxes
    document.querySelectorAll('.cliente-checkbox').forEach(cb => cb.checked = false);
    const master = document.getElementById('checkTodos');
    master.checked = false;
    master.indeterminate = false;

    // 3) acción
    const accion = document.getElementById('accion');
    if (accion) { accion.selectedIndex = 0; mostrarOpciones(); }

    // 4) filtro rápido
    const fr = document.getElementById('filtroRapido');
    if (fr) fr.selectedIndex = 0;

    // 5) búsqueda
    const bq = document.getElementById('busqueda');
    if (bq) bq.value = '';

    // 6) selects de ubicación del modal
    const selE = document.getElementById('edificio');
    const selP = document.getElementById('piso');
    const selS = document.getElementById('servicio');
    const ofi  = document.getElementById('oficina');
    if (selE) selE.selectedIndex = 0;
    if (selP) { selP.selectedIndex = 0; selP.disabled = true; }
    if (selS) { selS.selectedIndex = 0; selS.disabled = true; }
    if (ofi)  ofi.value = '';

    // 7) reset lista filtrada, render, paginación y contador
    filteredClientes = [...allClientes];
    currentPage = 1;
    renderClientes();
    setupPagination();
    actualizarContadorClientes();

    // 8) quitar clases de validación
    document.querySelectorAll('.was-validated').forEach(f => f.classList.remove('was-validated'));

    mostrarAlerta('Formulario limpiado correctamente', 'success', 3000);
  }
  async function onEliminarCliente(btn) {
    const id = btn.dataset.id;
    if (!id) return;

    if (!confirm(`¿Seguro que deseas eliminar el cliente "${id}"? Se eliminará también su ubicación.`)) {
      return;
    }

    // Deshabilita el botón mientras procesa
    const prevHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>`;

    try {
      const formData = new FormData();
      formData.append('id', id);
      const csrf = document.querySelector('input[name="csrf_token"]')?.value || '';
      formData.append('csrf_token', csrf);

      const resp = await fetch('api/eliminar_cliente.php', {
        method: 'POST',
        body: formData
      });
      const data = await resp.json();

      if (!resp.ok || !data.success) {
        throw new Error(data?.message || `HTTP ${resp.status}`);
      }

      // Quita la fila del DOM y actualiza estructuras/paginación/contador
      const tr = btn.closest('tr');
      if (tr) {
        removeClientRowAndRefresh(tr);
      }

      mostrarAlerta(data.message || 'Cliente eliminado', 'success');
    } catch (err) {
      console.error(err);
      mostrarAlerta(`No se pudo eliminar: ${err.message}`, 'danger');
    } finally {
      btn.disabled = false;
      btn.innerHTML = prevHTML;
    }
  }

  function removeClientRowAndRefresh(tr) {
    // Elimina de arrays de memoria usados por filtros/orden/paginación
    const idxAll = allClientes.indexOf(tr);
    if (idxAll >= 0) allClientes.splice(idxAll, 1);

    const idxFilt = filteredClientes.indexOf(tr);
    if (idxFilt >= 0) filteredClientes.splice(idxFilt, 1);

    tr.remove();
    // Re-render y actualizar UI
    renderClientes();
    setupPagination();
    actualizarContadorClientes();
  }

  // ------------------------------
  // Go!
  // ------------------------------
  init();
});
