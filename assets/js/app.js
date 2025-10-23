// app.js – versión optimizada con modal de detalles de red
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
    tiempoTranscurrido()
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

  const _show = (el) => el && el.classList.remove('hidden');
  const _hide = (el) => el && el.classList.add('hidden');

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

    // Botón de ubicación
    document.querySelectorAll('.btnAgregarUbicacion').forEach(btn => {
      btn.addEventListener('click', () => {
        prepararModalUbicacion(btn.dataset.id, btn.dataset.edificio, btn.dataset.servicio, btn.dataset.oficina, btn.dataset.responsable);
      });
    });

    // 👇 NUEVO: Botón de detalles de red
 document.querySelectorAll('.btnVerDetalles').forEach(btn => {
  btn.addEventListener('click', () => {
    const id = btn.dataset.id;
    document.getElementById('modalIdCliente').textContent = id;
    document.getElementById('modalIp').textContent = btn.dataset.ip;
    document.getElementById('modalMac').textContent = btn.dataset.mac;
    document.getElementById('modalMascara').textContent = btn.dataset.mascara;
    document.getElementById('modalGateway').textContent = btn.dataset.gateway;
    document.getElementById('modalDns').textContent = btn.dataset.dns;
    document.getElementById('modalProxyActivo').textContent = btn.dataset.proxyActivo;
    document.getElementById('modalProxyServidor').textContent = btn.dataset.proxyServidor;
    
    // 👇 NUEVO: Llenar con texto completo y establecer tooltip
    const exclusionesTextarea = document.getElementById('modalProxyExclusiones');
const exclusionesValue = btn.dataset.proxyExclusiones || 'N/A';
exclusionesTextarea.value = exclusionesValue; // <- Esto muestra el tooltip

      });
    });

    // Eliminar cliente
    document.querySelectorAll('.btnEliminarCliente').forEach(btn => {
      btn.addEventListener('click', () => onEliminarCliente(btn));
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
  }

  // ------------------------------
  // Drag & Drop archivo
  // ------------------------------
  function initDragAndDrop() {
    if (!fileDropArea || !fileInput) return;
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(evt => {
      fileDropArea.addEventListener(evt, (e) => { e.preventDefault(); e.stopPropagation(); }, false);
    });
    ['dragenter', 'dragover'].forEach(evt =>
      fileDropArea.addEventListener(evt, () => fileDropArea.classList.add('active'))
    );
    ['dragleave', 'drop'].forEach(evt =>
      fileDropArea.addEventListener(evt, () => fileDropArea.classList.remove('active'))
    );
  }



// Mostrar hardware en modal con barras de progreso
document.querySelectorAll('.btnVerHardware').forEach(btn => {
  btn.addEventListener('click', () => {
    const id = btn.dataset.id;
    const hardware = btn.dataset.hardware ? JSON.parse(btn.dataset.hardware) : null;

    document.getElementById('modalHardwareId').textContent = id;
    const content = document.getElementById('hardwareContent');

    let html = '';

    if (!hardware || Object.keys(hardware).length === 0) {
      html = '<p class="text-muted">No hay datos de hardware disponibles.</p>';
    } else {
      // CPU
      if (hardware.cpu) {
        html += `<p><strong>CPU:</strong> ${escapeHtml(hardware.cpu)}</p>`;
      }

      // RAM con barra de progreso
      if (hardware.ram && typeof hardware.ram === 'object') {
        const porcentaje = parseFloat(hardware.ram.porcentaje_uso) || 0;
        const usada = hardware.ram.usada || 'N/A';
        const total = hardware.ram.total || 'N/A';
        html += `
          <p><strong>RAM:</strong> ${usada} / ${total}</p>
          <div class="progress" style="height: 20px; margin-bottom: 12px;">
            <div class="progress-bar ${porcentaje > 85 ? 'bg-danger' : porcentaje > 70 ? 'bg-warning' : 'bg-success'}"
                 role="progressbar"
                 style="width: ${Math.min(100, porcentaje)}%"
                 aria-valuenow="${porcentaje}"
                 aria-valuemin="0"
                 aria-valuemax="100">
              ${porcentaje}%
            </div>
          </div>
        `;
      } else if (hardware.ram) {
        html += `<p><strong>RAM:</strong> ${escapeHtml(hardware.ram)}</p>`;
      }

      // Discos con barras de progreso
      if (Array.isArray(hardware.discos) && hardware.discos.length > 0) {
        html += `<p><strong>Discos:</strong></p>`;
        hardware.discos.forEach(disco => {
          if (typeof disco === 'object') {
            const dispositivo = escapeHtml(disco.dispositivo || 'N/A');
            const usado = disco.usado_gb ?? 'N/A';
            const total = disco.capacidad_total_gb ?? 'N/A';
            const porcentaje = parseFloat(disco.porcentaje_uso) || 0;
            html += `
              <div class="mb-3">
                <small><strong>${dispositivo}</strong>: ${usado} GB / ${total} GB</small>
                <div class="progress" style="height: 16px; margin-top: 4px;">
                  <div class="progress-bar ${porcentaje > 85 ? 'bg-danger' : porcentaje > 70 ? 'bg-warning' : 'bg-success'}"
                       role="progressbar"
                       style="width: ${Math.min(100, porcentaje)}%"
                       aria-valuenow="${porcentaje}"
                       aria-valuemin="0"
                       aria-valuemax="100">
                    ${porcentaje}%
                  </div>
                </div>
              </div>
            `;
          } else {
            html += `<p><strong>Disco:</strong> ${escapeHtml(disco)}</p>`;
          }
        });
      } else if (hardware.discos) {
        html += `<p><strong>Discos:</strong> ${escapeHtml(hardware.discos)}</p>`;
      }
    }

    document.getElementById('hardwareContent').innerHTML = html;
  });
});


document.querySelectorAll('.btnVerImpresoras').forEach(btn => {
  btn.addEventListener('click', () => {
    const id = btn.dataset.id;
    const impresorasData = btn.dataset.impresoras ? JSON.parse(btn.dataset.impresoras) : null;
    
    document.getElementById('modalImpresorasId').textContent = id;
    const content = document.getElementById('impresorasContent');
    
    if (!impresorasData || impresorasData.error) {
      content.innerHTML = `<div class="alert alert-warning">No se pudieron obtener las impresoras.</div>`;
      return;
    }
    
    if (!impresorasData.impresoras || impresorasData.impresoras.length === 0) {
      content.innerHTML = `<div class="alert alert-info">No hay impresoras instaladas.</div>`;
      return;
    }
    
    let html = `
        <table class="table table-sm table-responsive">
          <thead class="table-light">
            <tr>
              <th>Nombre</th>
              <th>Puerto</th>
              <th>Estado</th>
              <th>Trabajos</th>
              <th>Predeterminada</th>
            </tr>
          </thead>
          <tbody>
      `;
      
      impresorasData.impresoras.forEach(imp => {
        // Estado con icono y color
        let estadoHtml = '✅ lista';
        let estadoClass = 'text-success';
        
        if (imp.tiene_error) {
          estadoClass = 'text-danger';
          if (imp.estado === 'en pausa') {
            estadoHtml = '⏸️ en pausa';
          } else if (imp.estado === 'sin papel') {
            estadoHtml = '📄 sin papel';
          } else if (imp.estado === 'atascada') {
            estadoHtml = '🚨 atascada';
          } else if (imp.estado === 'offline') {
            estadoHtml = '📴 offline';
          } else {
            estadoHtml = `⚠️ ${imp.estado}`;
          }
        }
        
        // Trabajos en cola
        const trabajos = imp.trabajos_pendientes > 0 
          ? `<span class="badge bg-warning">${imp.trabajos_pendientes}</span>`
          : '<span class="text-muted">0</span>';
        
        // Predeterminada
        const predeterminada = imp.predeterminada 
          ? '<i class="bi bi-printer"></i>' 
          : '';
        
        html += `
          <tr>
            <td>${imp.nombre || 'N/A'}</td>
            <td><code>${imp.puerto || 'desconocido'}</code></td>
            <td class="${estadoClass}">${estadoHtml}</td>
            <td>${trabajos}</td>
            <td>${predeterminada}</td>
          </tr>
        `;
      });
      
      html += `
          </tbody>
        </table>
        <small class="text-muted">Total: ${impresorasData.total_impresoras || impresorasData.impresoras.length} impresoras</small>
      `;
      
      content.innerHTML = html;
  });
});


  // ------------------------------
  // Opciones según acción
  // ------------------------------
  window.mostrarOpciones = function () {
    const accion         = document.getElementById('accion')?.value || '';
    const estiloDiv      = document.getElementById('estiloDiv');
    const paramWallpaper = document.getElementById('paramWallpaper');
    const msgDiv         = document.getElementById('mensajeDiv');
    const prevWall       = document.getElementById('previewWallpaper');

    if (accion === 'set_wallpaper') {
      _show(estiloDiv);
      _show(paramWallpaper);
      _hide(msgDiv);
      _show(prevWall);
      if (fileInput) fileInput.required = true;
    } else if (accion === 'show_message') {
      _hide(estiloDiv);
      _hide(paramWallpaper);
      _show(msgDiv);
      _hide(prevWall);
      if (fileInput) fileInput.required = false;
    } else {
      _hide(estiloDiv);
      _hide(paramWallpaper);
      _hide(msgDiv);
      _hide(prevWall);
      if (fileInput) fileInput.required = false;
    }
  };

  function validarMensaje() {
    const t = (document.getElementById('msgTitle')?.value || '').trim();
    const m = (document.getElementById('msgBody')?.value  || '').trim();
    const timeout = (document.getElementById('msgTimeout')?.value || '').trim();
    if (!t && !m) {
      mostrarAlerta('Indica al menos Título o Mensaje.', 'warning');
      return false;
    }
    if (t.length > 120) {
      mostrarAlerta('El título no puede superar 120 caracteres.', 'warning');
      return false;
    }
    if (m.length > 4000) {
      mostrarAlerta('El mensaje no puede superar 4000 caracteres.', 'warning');
      return false;
    }
    if (timeout !== '') {
      const n = parseInt(timeout, 10);
      if (Number.isNaN(n) || n <= 0 || n > 3600) {
        mostrarAlerta('El cierre automático debe ser un número entre 1 y 3600 segundos.', 'warning');
        return false;
      }
    }
    return true;
  }

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

  (function () {
    const input = document.getElementById('fondo');
    const drop  = document.getElementById('fileDropArea');
    const img   = document.getElementById('imgPreview');
    const info  = document.getElementById('fileInfo');
    const prev  = document.getElementById('previewWallpaper');
    if (!input) return;
    const reset = () => {
      if (info) info.textContent = 'Arrastra una imagen o haz clic aquí';
      if (img)  { img.src = ''; img.style.display = 'none'; }
      if (prev) prev.classList.add('hidden');
      input.value = '';
    };
    const previewOK = () => {
      const f = input.files?.[0];
      if (!f || !img) return;
      if (info) info.textContent = `${f.name} (${(f.size/1024/1024).toFixed(2)} MB)`;
      img.src = URL.createObjectURL(f);
      img.style.display = 'block';
      prev?.classList.remove('hidden');
    };
    input.addEventListener('change', () => {
      if (validarArchivoWallpaper()) previewOK(); else reset();
    });
    if (drop) {
      drop.addEventListener('dragover', e => e.preventDefault());
      drop.addEventListener('drop', e => {
        e.preventDefault();
        const file = e.dataTransfer?.files?.[0];
        if (!file) return;
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        if (validarArchivoWallpaper()) previewOK(); else reset();
      });
    }
  })();

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

  function prepararModalUbicacion(clienteId, edificio, servicio, oficina, responsable) {
    document.getElementById('ubicacionClienteId').value = clienteId;
    document.getElementById('oficina').value = oficina || '';
    document.getElementById('responsable').value = responsable || '';
    const selE = document.getElementById('edificio');
    const selP = document.getElementById('piso');
    const selS = document.getElementById('servicio');
    selE.value = '';
    selP.innerHTML = '<option value="">Seleccione piso</option>'; selP.disabled = true;
    selS.innerHTML = '<option value="">Seleccione servicio</option>'; selS.disabled = true;
    if (!edificio) return;
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
  // Tabla de clientes
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
        edificio: td[5]?.textContent ?? '',
        responsable: td[6]?.textContent ?? '',
        conexion: td[7]?.textContent ?? '',
        so: td[8]?.textContent ?? '',
        pendientes: td[9]?.textContent ?? '',
        estado: td[10]?.textContent ?? '',

        mac: td[11]?.textContent ?? '',
        servicio: td[12]?.textContent ?? '',
        oficina: td[13]?.textContent ?? '',
        exclusiones: [14].textContent ?? '',
      };
    });
    actualizarContadorClientes();
  }

function aplicarFiltroRapido() {
  const filtro = document.getElementById('filtroRapido').value;
  switch (filtro) {
        case 'sin-ubicacion':
    
      filteredClientes = allClientes.filter(f =>
        f._data.edificio && f._data.edificio !== '-' &&
        f._data.servicio && f._data.servicio !== '-'
      );
      break;
    case 'con-ubicacion': // 👈 PROBLEMA AQUÍ
        filteredClientes = allClientes.filter(f =>
        !f._data.edificio|| f._data.edificio === '-'||
        !f._data.servicio|| f._data.servicio === '-'
      );
      break;
    case 'bloqueados':
      filteredClientes = allClientes.filter(f => 
        f._data.estado && 
        f._data.estado.toLowerCase().includes('bloqueado')
      );
      break;
    case 'desbloqueados':
      filteredClientes = allClientes.filter(f => 
        !f._data.estado || 
        !f._data.estado.toLowerCase().includes('bloqueado')
      );
      break;
    default:
      filteredClientes = [...allClientes];
  }
  currentPage = 1;
  renderClientes();
  setupPagination();
  actualizarContadorClientes();
  actualizarCheckTodos();
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
    tbody.querySelectorAll('tr.no-results').forEach(e => e.remove());
    allClientes.forEach(tr => { tr.style.display = 'none'; });
    if (filteredClientes.length === 0) {
      const tr = document.createElement('tr');
      tr.className = 'no-results text-center text-muted';
      tr.innerHTML = '<td colspan="13" class="py-3"><i class="bi bi-search"></i> No se encontraron coincidencias</td>';
      tbody.appendChild(tr);
      return;
    }
    aplicarOrdenamiento();
    filteredClientes.forEach(tr => { tr.style.display = ''; });
    mostrarPagina(currentPage);
  }
function tiempoTranscurrido() {
 const rtf = new Intl.RelativeTimeFormat('es', { numeric: 'auto' });
  const units = [['year',31536000],['month',2592000],['week',604800],['day',86400],['hour',3600],['minute',60],['second',1]];
  document.querySelectorAll('time.ts[datetime]').forEach(t => {
    const d = new Date(t.getAttribute('datetime'));
    if (isNaN(d)) return;
    const diff = Math.round((d - new Date()) / 1000);
    for (const [unit, secs] of units) {
      const v = Math.trunc(diff / secs);
      if (v !== 0) { t.title = d.toLocaleString(); t.textContent = rtf.format(v, unit); break; }
    }
  });
}
  function setupPagination() {
    const pageCount = Math.ceil(filteredClientes.length / rowsPerPage) || 1;
    if (currentPage > pageCount) currentPage = pageCount;
    const nav = document.querySelector('#paginationNav ul');
    nav.innerHTML = '';
    if (pageCount <= 1) {
      document.getElementById('paginationNav').style.display = 'none';
      mostrarPagina(1);
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
  // Envío principal
  // ------------------------------
  async function manejarEnvioFormularioPrincipal(e) {
    e.preventDefault();
    const seleccionados = Array.from(document.querySelectorAll('.cliente-checkbox:checked'));
    const total = seleccionados.length;
    if (total === 0) { mostrarAlerta('Seleccione al menos un cliente', 'warning'); return; }
    const accion = document.getElementById('accion').value;
    if (!accion) { mostrarAlerta('Seleccione una acción', 'warning'); return; }
    if (accion === 'set_wallpaper' && !validarArchivoWallpaper()) return;
    if (accion === 'show_message' && !validarMensaje()) return;

    document.querySelector('.loading-overlay').style.display = 'none';
    document.getElementById('submitBtn').disabled = true;
    abrirModalProgreso('Preparando envío...', 0, 0, total);
    await animateProgressTo(15, 300);

    try {
      const form = document.getElementById('mainForm');
      const fd = new FormData();
      fd.append('csrf_token', form.querySelector('input[name="csrf_token"]').value);
      fd.append('accion', accion);

      if (accion === 'set_wallpaper') {
        const estilo = document.getElementById('estilo');
        if (estilo) fd.append('estilo', estilo.value);
        const archivo = document.getElementById('fondo').files[0];
        if (archivo) fd.append('fondo', archivo);
      }

      if (accion === 'show_message') {
        const t  = (document.getElementById('msgTitle')?.value || '').trim();
        const m  = (document.getElementById('msgBody')?.value  || '').trim();
        const to = (document.getElementById('msgTimeout')?.value || '').trim();
        if (t)  fd.append('title', t);
        if (m)  fd.append('message', m);
        if (to) fd.append('timeout_seconds', to);
      }

      for (const cb of seleccionados) fd.append('clientes[]', cb.value);

      actualizarModalProgreso(20, 'Enviando al servidor...');
      await animateProgressTo(35, 500);

      const res = await fetch(form.action, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      });

      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();

      if (data.error) {
        actualizarModalProgreso(100, 'Error');
        setTimeout(() => { cerrarModalProgreso(); }, 600);
        mostrarAlerta(data.error, 'danger');
        document.getElementById('submitBtn').disabled = false;
        return;
      }

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
        { pollInterval: 2000, expirySec: 45 }
      );
    } catch (err) {
      console.error(err);
      actualizarModalProgreso(100, 'Error');
      setTimeout(() => { cerrarModalProgreso(); }, 600);
      mostrarAlerta(`Error al procesar la solicitud: ${err.message}`, 'danger');
      document.getElementById('submitBtn').disabled = false;
    }
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
      pollInterval = 2000,
      expirySec = 45
    } = opts;
    if (!Array.isArray(comandoIds) || comandoIds.length === 0) {
      if (typeof onComplete === 'function') onComplete({ total: 0, completados: 0, detalles: {} }, { timedOut:false, stalled:false });
      return;
    }
    if (window.__progresoActive) return;
    window.__progresoActive = true;
    const pollSec = Math.max(1, Math.round(pollInterval / 1000));
    const maxIdlePolls = Math.ceil(expirySec / pollSec) + 2;
    const timeoutMs = (expirySec + 30) * 1000;
    const urlBase = `api/estado_tarea.php?ids=${encodeURIComponent(comandoIds.join(','))}&expiry=${encodeURIComponent(expirySec)}`;
    let startTime = Date.now();
    let lastDone  = 0;
    let idleCount = 0;
    let aborted   = false;

    const modalEl = document.getElementById('modalProgreso');
    const stopOnHide = () => { aborted = true; modalEl.removeEventListener('hidden.bs.modal', stopOnHide); };
    modalEl.addEventListener('hidden.bs.modal', stopOnHide);

    const sleep = (ms) => new Promise(r => setTimeout(r, ms));

    (async function loop() {
      try {
        while (!aborted) {
          const controller = new AbortController();
          const to = setTimeout(() => controller.abort(), Math.max(8000, pollInterval));
          let lastData;
          try {
            const res = await fetch(urlBase, { cache: 'no-store', signal: controller.signal });
            lastData = await res.json();
          } finally {
            clearTimeout(to);
          }
          const total = Number(lastData.total ?? comandoIds.length);
          const done  = Number(lastData.completados ?? 0);
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
          await sleep(pollInterval);
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
    limpiarSoloArchivo();
    document.querySelectorAll('.cliente-checkbox').forEach(cb => cb.checked = false);
    const master = document.getElementById('checkTodos');
    master.checked = false;
    master.indeterminate = false;
    const accion = document.getElementById('accion');
    if (accion) { accion.selectedIndex = 0; mostrarOpciones(); }
    const fr = document.getElementById('filtroRapido');
    if (fr) fr.selectedIndex = 0;
    const bq = document.getElementById('busqueda');
    if (bq) bq.value = '';
    const ti = document.getElementById('msgTitle');
    if (ti) ti.value = '';
    const me = document.getElementById('msgBody');
    if (me) me.value = '';
    const time = document.getElementById('msgTimeout');
    if (time) time.value = '';
    const selE = document.getElementById('edificio');
    const selP = document.getElementById('piso');
    const selS = document.getElementById('servicio');
    const ofi  = document.getElementById('oficina');
    const res = document.getElementById('responsable');
    if (selE) selE.selectedIndex = 0;
    if (selP) { selP.selectedIndex = 0; selP.disabled = true; }
    if (selS) { selS.selectedIndex = 0; selS.disabled = true; }
    if (ofi)  ofi.value = '';
    if (res)  res.value = '';
    filteredClientes = [...allClientes];
    currentPage = 1;
    renderClientes();
    setupPagination();
    actualizarContadorClientes();
    document.querySelectorAll('.was-validated').forEach(f => f.classList.remove('was-validated'));
    mostrarAlerta('Formulario limpiado correctamente', 'success', 3000);
  }

  async function onEliminarCliente(btn) {
    const id = btn.dataset.id;
    if (!id) return;
    if (!confirm(`¿Seguro que deseas eliminar el cliente "${id}"? Se eliminará también su ubicación.`)) {
      return;
    }
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
    const idxAll = allClientes.indexOf(tr);
    if (idxAll >= 0) allClientes.splice(idxAll, 1);
    const idxFilt = filteredClientes.indexOf(tr);
    if (idxFilt >= 0) filteredClientes.splice(idxFilt, 1);
    tr.remove();
    renderClientes();
    setupPagination();
    actualizarContadorClientes();
  }

  // ------------------------------
  // Go!
  // ------------------------------
  init();
});

// Función auxiliar para escapar HTML
function escapeHtml(text) {
  if (typeof text !== 'string') return String(text);
  const map = {
    '&': '&amp;',
    '<': '<',
    '>': '>',
    '"': '&quot;',
    "'": '&#039;'
  };
  return text.replace(/[&<>"']/g, m => map[m]);
}