<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
requireLogin();

$db = getDB();

// Cargar plantillas guardadas
$plantillas = $db->query("SELECT * FROM wa_plantillas ORDER BY categoria, nombre")->fetchAll();

// Cargar clientes con WhatsApp
$clientes = $db->query("
    SELECT c.id, c.nombre, c.whatsapp, c.telefono,
           (SELECT ot.codigo_ot FROM ordenes_trabajo ot WHERE ot.cliente_id=c.id ORDER BY ot.created_at DESC LIMIT 1) as ultima_ot,
           (SELECT ot.estado FROM ordenes_trabajo ot WHERE ot.cliente_id=c.id ORDER BY ot.created_at DESC LIMIT 1) as ultimo_estado,
           (SELECT ot.codigo_publico FROM ordenes_trabajo ot WHERE ot.cliente_id=c.id ORDER BY ot.created_at DESC LIMIT 1) as codigo_publico
    FROM clientes c
    WHERE (c.whatsapp IS NOT NULL AND c.whatsapp != '') OR (c.telefono IS NOT NULL AND c.telefono != '')
    ORDER BY c.nombre
")->fetchAll();

$pageTitle  = 'WhatsApp — ' . APP_NAME;
$breadcrumb = [['label'=>'WhatsApp','url'=>null]];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="fw-bold mb-0">
    <span style="color:#25D366">●</span> Comunicaciones WhatsApp
  </h5>
  <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modal-nueva-plantilla">
    <i data-feather="plus" style="width:14px;height:14px"></i> Nueva plantilla
  </button>
</div>

<div class="row g-3">

  <!-- Panel izquierdo: Plantillas -->
  <div class="col-lg-4">
    <div class="tr-card h-100">
      <div class="tr-card-header">
        <h6 class="mb-0 small fw-semibold">📋 PLANTILLAS DE MENSAJES</h6>
      </div>
      <div class="tr-card-body p-2">

        <!-- Filtro por categoría -->
        <div class="btn-group w-100 mb-2" id="filtro-cats">
          <button class="btn btn-sm btn-primary active" data-cat="">Todas</button>
          <button class="btn btn-sm btn-outline-secondary" data-cat="reparacion">Reparación</button>
          <button class="btn btn-sm btn-outline-secondary" data-cat="venta">Venta</button>
          <button class="btn btn-sm btn-outline-secondary" data-cat="general">General</button>
        </div>

        <div id="lista-plantillas">
          <?php foreach($plantillas as $p): ?>
          <div class="plantilla-item p-2 mb-1 rounded border cursor-pointer"
               data-cat="<?= $p['categoria'] ?>"
               data-texto="<?= htmlspecialchars($p['texto'], ENT_QUOTES) ?>"
               data-nombre="<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>"
               onclick="seleccionarPlantilla(this)"
               style="cursor:pointer; transition:.15s">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="fw-semibold small"><?= sanitize($p['nombre']) ?></div>
                <div class="text-muted" style="font-size:11px"><?= sanitize(substr($p['texto'],0,60)) ?>...</div>
              </div>
              <span class="badge bg-<?= $p['categoria']==='reparacion'?'primary':($p['categoria']==='venta'?'success':'secondary') ?> ms-1" style="font-size:10px"><?= $p['categoria'] ?></span>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if(empty($plantillas)): ?>
          <p class="text-muted text-center small py-3">No hay plantillas aún.<br>Crea la primera con el botón de arriba.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Panel derecho: Compositor -->
  <div class="col-lg-8">
    <div class="tr-card">
      <div class="tr-card-header">
        <h6 class="mb-0 small fw-semibold">✉️ REDACTAR Y ENVIAR</h6>
        <span class="badge bg-success">Vía WhatsApp Web</span>
      </div>
      <div class="tr-card-body">

        <!-- Selección de cliente / número -->
        <div class="row g-2 mb-3">
          <div class="col-md-8">
            <label class="tr-form-label">Buscar cliente</label>
            <input type="text" id="buscar-cliente" class="form-control form-control-sm"
                   placeholder="Nombre o número..." autocomplete="off"/>
            <div id="sugerencias-clientes" class="list-group position-absolute" style="z-index:100;max-height:200px;overflow-y:auto;width:calc(100% - 24px)"></div>
          </div>
          <div class="col-md-4">
            <label class="tr-form-label">WhatsApp / Teléfono</label>
            <input type="text" id="numero-destino" class="form-control form-control-sm"
                   placeholder="51999888777"/>
          </div>
        </div>

        <!-- Variables rápidas -->
        <div class="mb-2">
          <label class="tr-form-label">Variables rápidas</label>
          <div class="d-flex flex-wrap gap-1" id="vars-rapidas">
            <button type="button" class="btn btn-xs btn-outline-secondary btn-sm py-0" onclick="insertarVar('{nombre}')">👤 {nombre}</button>
            <button type="button" class="btn btn-xs btn-outline-secondary btn-sm py-0" onclick="insertarVar('{codigo_ot}')">📋 {codigo_ot}</button>
            <button type="button" class="btn btn-xs btn-outline-secondary btn-sm py-0" onclick="insertarVar('{estado}')">🔄 {estado}</button>
            <button type="button" class="btn btn-xs btn-outline-secondary btn-sm py-0" onclick="insertarVar('{equipo}')">💻 {equipo}</button>
            <button type="button" class="btn btn-xs btn-outline-secondary btn-sm py-0" onclick="insertarVar('{total}')">💰 {total}</button>
            <button type="button" class="btn btn-xs btn-outline-secondary btn-sm py-0" onclick="insertarVar('{codigo_consulta}')">🔑 {codigo_consulta}</button>
            <button type="button" class="btn btn-xs btn-outline-secondary btn-sm py-0" onclick="insertarVar('{empresa}')">🏪 {empresa}</button>
            <button type="button" class="btn btn-xs btn-outline-secondary btn-sm py-0" onclick="insertarVar('{fecha_estimada}')">📅 {fecha_estimada}</button>
          </div>
        </div>

        <!-- Área de mensaje -->
        <div class="mb-3">
          <label class="tr-form-label">Mensaje</label>
          <textarea id="mensaje-wa" class="form-control" rows="6"
                    placeholder="Escribe tu mensaje o selecciona una plantilla de la izquierda..."></textarea>
          <div class="d-flex justify-content-between mt-1">
            <span class="text-muted" style="font-size:11px">Puedes usar *negrita*, _cursiva_, ~tachado~</span>
            <span class="text-muted" style="font-size:11px" id="contador-chars">0 caracteres</span>
          </div>
        </div>

        <!-- Preview del mensaje -->
        <div class="mb-3" id="preview-container" style="display:none">
          <label class="tr-form-label">Vista previa</label>
          <div class="p-3 rounded" style="background:#DCF8C6;font-size:14px;line-height:1.5;max-width:400px;border-radius:12px 12px 0 12px;white-space:pre-wrap" id="preview-msg"></div>
        </div>

        <!-- Botones de envío -->
        <div class="d-flex gap-2 flex-wrap">
          <button type="button" class="btn btn-success" onclick="enviarWhatsAppWeb()">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" class="me-1">
              <path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.898 7.898 0 0 0 13.6 2.326zM7.994 14.521a6.573 6.573 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.557 6.557 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592zm3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.729.729 0 0 0-.529.247c-.182.198-.691.677-.691 1.654 0 .977.71 1.916.81 2.049.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232z"/>
            </svg>
            Abrir WhatsApp Web
          </button>
          <button type="button" class="btn btn-outline-secondary" onclick="previsualizarMensaje()">
            <i data-feather="eye" style="width:14px;height:14px"></i> Previsualizar
          </button>
          <button type="button" class="btn btn-outline-primary btn-sm" onclick="limpiarFormulario()">
            <i data-feather="refresh-cw" style="width:14px;height:14px"></i> Limpiar
          </button>
        </div>

      </div>
    </div>

    <!-- Envío masivo / lotes -->
    <div class="tr-card mt-3">
      <div class="tr-card-header">
        <h6 class="mb-0 small fw-semibold">📢 COMUNICADO A MÚLTIPLES CLIENTES</h6>
        <span class="badge bg-warning text-dark">Uno a uno via WhatsApp Web</span>
      </div>
      <div class="tr-card-body">
        <p class="text-muted small mb-3">Selecciona clientes y se abrirá WhatsApp Web para cada uno con el mensaje listo.</p>

        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="tr-form-label">Mensaje del comunicado</label>
            <textarea id="msg-masivo" class="form-control form-control-sm" rows="4"
                      placeholder="Escribe el comunicado para todos..."></textarea>
          </div>
          <div class="col-md-6">
            <label class="tr-form-label">Filtrar clientes</label>
            <select id="filtro-segmento" class="form-select form-select-sm mb-2" onchange="filtrarClientes()">
              <option value="">Todos los clientes</option>
              <option value="frecuente">Frecuentes</option>
              <option value="empresa">Empresas</option>
              <option value="vip">VIP</option>
              <option value="con_ot_activa">Con OT activa</option>
            </select>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="seleccionarTodos()">Seleccionar todos</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deseleccionarTodos()">Limpiar</button>
            </div>
          </div>
        </div>

        <!-- Lista de clientes seleccionables -->
        <div id="lista-clientes-masivo" style="max-height:200px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:8px;padding:8px">
          <?php foreach($clientes as $c):
            $wa = $c['whatsapp'] ?: $c['telefono'];
          ?>
          <div class="form-check cliente-masivo-item py-1"
               data-segmento="<?= $c['segmento'] ?? 'nuevo' ?>"
               data-wa="<?= htmlspecialchars($wa) ?>"
               data-nombre="<?= htmlspecialchars($c['nombre']) ?>"
               data-ot="<?= htmlspecialchars($c['ultima_ot'] ?? '') ?>"
               data-estado="<?= htmlspecialchars($c['ultimo_estado'] ?? '') ?>"
               data-codigo="<?= htmlspecialchars($c['codigo_publico'] ?? '') ?>">
            <input class="form-check-input chk-cliente" type="checkbox"
                   id="cli_<?= $c['id'] ?>" value="<?= $c['id'] ?>"
                   data-wa="<?= htmlspecialchars($wa) ?>"
                   data-nombre="<?= htmlspecialchars($c['nombre']) ?>"/>
            <label class="form-check-label small" for="cli_<?= $c['id'] ?>">
              <span class="fw-semibold"><?= sanitize($c['nombre']) ?></span>
              <span class="text-muted ms-2"><?= sanitize($wa) ?></span>
              <?php if($c['ultima_ot']): ?>
              <span class="badge bg-<?= ESTADOS_OT[$c['ultimo_estado']]['color'] ?? 'secondary' ?> ms-1" style="font-size:10px"><?= sanitize($c['ultima_ot']) ?></span>
              <?php endif; ?>
            </label>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="mt-2 d-flex gap-2 align-items-center">
          <span class="text-muted small" id="conteo-seleccionados">0 seleccionados</span>
          <button type="button" class="btn btn-success btn-sm ms-auto" onclick="enviarMasivo()">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" class="me-1"><path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.898 7.898 0 0 0 13.6 2.326zM7.994 14.521a6.573 6.573 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.557 6.557 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592zm3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.729.729 0 0 0-.529.247c-.182.198-.691.677-.691 1.654 0 .977.71 1.916.81 2.049.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232z"/></svg>
            Enviar a seleccionados
          </button>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Modal nueva plantilla -->
<div class="modal fade" id="modal-nueva-plantilla" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="guardar_plantilla.php">
        <div class="modal-header">
          <h6 class="modal-title fw-bold">Nueva plantilla de mensaje</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="tr-form-label">Nombre de la plantilla *</label>
            <input type="text" name="nombre" class="form-control" required placeholder="Ej: Equipo listo para recoger"/>
          </div>
          <div class="mb-2">
            <label class="tr-form-label">Categoría</label>
            <select name="categoria" class="form-select">
              <option value="reparacion">Reparación</option>
              <option value="venta">Venta</option>
              <option value="general">General</option>
            </select>
          </div>
          <div class="mb-2">
            <label class="tr-form-label">Mensaje *</label>
            <textarea name="texto" class="form-control" rows="5" required
                      placeholder="Hola {nombre}, tu equipo {equipo} ya está *listo* para recoger 🎉&#10;&#10;Código: {codigo_ot}&#10;Código consulta: {codigo_consulta}"></textarea>
            <div class="text-muted small mt-1">
              Variables: {nombre} {codigo_ot} {estado} {equipo} {total} {codigo_consulta} {empresa} {fecha_estimada}
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary btn-sm">Guardar plantilla</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$empresa = getConfig('empresa_nombre', $db) ?: APP_NAME;
$clientesJS = json_encode(array_map(fn($c) => [
    'id'     => $c['id'],
    'nombre' => $c['nombre'],
    'wa'     => $c['whatsapp'] ?: $c['telefono'],
    'ot'     => $c['ultima_ot'] ?? '',
    'estado' => $c['ultimo_estado'] ?? '',
    'codigo' => $c['codigo_publico'] ?? '',
], $clientes));

$pageScripts = <<<JS
<script>
const EMPRESA  = <?= json_encode($empresa) ?>;
const CLIENTES = $clientesJS;

// ── Buscador de cliente ──────────────────────────────────
const inputBuscar = document.getElementById('buscar-cliente');
const sugerencias = document.getElementById('sugerencias-clientes');

inputBuscar.addEventListener('input', function() {
  const q = this.value.toLowerCase().trim();
  sugerencias.innerHTML = '';
  if (q.length < 2) return;
  const matches = CLIENTES.filter(c =>
    c.nombre.toLowerCase().includes(q) || (c.wa||'').includes(q)
  ).slice(0, 8);
  matches.forEach(c => {
    const a = document.createElement('a');
    a.className = 'list-group-item list-group-item-action small py-1';
    a.innerHTML = '<strong>' + escHtml(c.nombre) + '</strong> <span class="text-muted ms-2">' + escHtml(c.wa||'sin número') + '</span>';
    a.onclick = () => {
      document.getElementById('numero-destino').value = limpiarNumero(c.wa || '');
      inputBuscar.value = c.nombre;
      sugerencias.innerHTML = '';
      // Auto-llenar variables del cliente seleccionado
      window._clienteActual = c;
    };
    sugerencias.appendChild(a);
  });
});
document.addEventListener('click', e => { if(!inputBuscar.contains(e.target)) sugerencias.innerHTML=''; });

// ── Seleccionar plantilla ────────────────────────────────
function seleccionarPlantilla(el) {
  document.querySelectorAll('.plantilla-item').forEach(p => p.style.background='');
  el.style.background = '#f0fdf4';
  document.getElementById('mensaje-wa').value = el.dataset.texto;
  contarChars();
}

// ── Insertar variable en cursor ──────────────────────────
function insertarVar(v) {
  const ta = document.getElementById('mensaje-wa');
  const s = ta.selectionStart, e = ta.selectionEnd;
  ta.value = ta.value.substring(0,s) + v + ta.value.substring(e);
  ta.selectionStart = ta.selectionEnd = s + v.length;
  ta.focus();
  contarChars();
}

// ── Contador de caracteres ───────────────────────────────
document.getElementById('mensaje-wa').addEventListener('input', contarChars);
function contarChars() {
  document.getElementById('contador-chars').textContent =
    document.getElementById('mensaje-wa').value.length + ' caracteres';
}

// ── Reemplazar variables con datos del cliente ───────────
function resolverVariables(texto, cliente) {
  if (!cliente) return texto;
  return texto
    .replace(/{nombre}/g,          cliente.nombre || '')
    .replace(/{codigo_ot}/g,        cliente.ot     || '')
    .replace(/{estado}/g,           cliente.estado || '')
    .replace(/{codigo_consulta}/g,  cliente.codigo || '')
    .replace(/{empresa}/g,          EMPRESA);
}

// ── Previsualizar ────────────────────────────────────────
function previsualizarMensaje() {
  const raw = document.getElementById('mensaje-wa').value;
  const msg = resolverVariables(raw, window._clienteActual);
  const preview = msg
    .replace(/\\*(.*?)\\*/g, '<strong>$1</strong>')
    .replace(/_(.*?)_/g, '<em>$1</em>')
    .replace(/~(.*?)~/g, '<s>$1</s>')
    .replace(/\\n/g, '<br>');
  document.getElementById('preview-msg').innerHTML = preview;
  document.getElementById('preview-container').style.display = '';
}

// ── Enviar por WhatsApp Web ──────────────────────────────
function enviarWhatsAppWeb() {
  const numero = limpiarNumero(document.getElementById('numero-destino').value);
  const raw    = document.getElementById('mensaje-wa').value.trim();
  if (!numero) { alert('Ingresa un número de WhatsApp.'); return; }
  if (!raw)    { alert('Escribe un mensaje.'); return; }
  const msg = resolverVariables(raw, window._clienteActual);
  const url = 'https://wa.me/' + numero + '?text=' + encodeURIComponent(msg);
  window.open(url, '_blank');
}

// ── Filtro de plantillas ─────────────────────────────────
document.getElementById('filtro-cats').querySelectorAll('button').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('#filtro-cats button').forEach(b => {
      b.classList.remove('btn-primary','active');
      b.classList.add('btn-outline-secondary');
    });
    this.classList.add('btn-primary','active');
    this.classList.remove('btn-outline-secondary');
    const cat = this.dataset.cat;
    document.querySelectorAll('.plantilla-item').forEach(p => {
      p.style.display = (!cat || p.dataset.cat === cat) ? '' : 'none';
    });
  });
});

// ── Envío masivo ─────────────────────────────────────────
function seleccionarTodos() {
  document.querySelectorAll('.chk-cliente:not([style*="display:none"])').forEach(c => c.checked=true);
  actualizarConteo();
}
function deseleccionarTodos() {
  document.querySelectorAll('.chk-cliente').forEach(c => c.checked=false);
  actualizarConteo();
}
document.querySelectorAll('.chk-cliente').forEach(c => c.addEventListener('change', actualizarConteo));
function actualizarConteo() {
  const n = document.querySelectorAll('.chk-cliente:checked').length;
  document.getElementById('conteo-seleccionados').textContent = n + ' seleccionados';
}
function filtrarClientes() {
  const seg = document.getElementById('filtro-segmento').value;
  document.querySelectorAll('.cliente-masivo-item').forEach(item => {
    const mostrar = !seg || item.dataset.segmento === seg ||
      (seg === 'con_ot_activa' && item.dataset.ot && item.dataset.estado !== 'entregado' && item.dataset.estado !== 'cancelado');
    item.style.display = mostrar ? '' : 'none';
  });
}

let _indiceMasivo = 0;
let _listaMasivo  = [];

function enviarMasivo() {
  const msg = document.getElementById('msg-masivo').value.trim();
  if (!msg) { alert('Escribe el mensaje del comunicado.'); return; }
  const seleccionados = [...document.querySelectorAll('.chk-cliente:checked')];
  if (!seleccionados.length) { alert('Selecciona al menos un cliente.'); return; }

  _listaMasivo  = seleccionados.map(c => ({
    nombre: c.dataset.nombre,
    wa:     limpiarNumero(c.dataset.wa),
  }));
  _indiceMasivo = 0;

  if (!confirm('Se abrirá WhatsApp Web para ' + _listaMasivo.length + ' clientes, uno a la vez.\\n\\n¿Continuar?')) return;
  enviarSiguiente(msg);
}

function enviarSiguiente(msg) {
  if (_indiceMasivo >= _listaMasivo.length) {
    alert('✅ Comunicado enviado a todos los seleccionados.');
    return;
  }
  const c   = _listaMasivo[_indiceMasivo];
  const txt = msg.replace(/{nombre}/g, c.nombre).replace(/{empresa}/g, EMPRESA);
  const url = 'https://wa.me/' + c.wa + '?text=' + encodeURIComponent(txt);
  window.open(url, '_blank');
  _indiceMasivo++;
  if (_indiceMasivo < _listaMasivo.length) {
    setTimeout(() => {
      if (confirm('WhatsApp ' + _indiceMasivo + '/' + _listaMasivo.length + ' enviado.\\n¿Abrir el siguiente para ' + _listaMasivo[_indiceMasivo].nombre + '?')) {
        enviarSiguiente(msg);
      }
    }, 1500);
  } else {
    setTimeout(() => alert('✅ Listo. Comunicado enviado a todos.'), 1000);
  }
}

// ── Helpers ──────────────────────────────────────────────
function limpiarNumero(n) {
  n = (n||'').replace(/\\D/g,'');
  if (!n) return '';
  if (!n.startsWith('51') && n.length <= 9) n = '51' + n;
  return n;
}
function limpiarFormulario() {
  document.getElementById('mensaje-wa').value = '';
  document.getElementById('numero-destino').value = '';
  document.getElementById('buscar-cliente').value = '';
  document.getElementById('preview-container').style.display = 'none';
  window._clienteActual = null;
  document.querySelectorAll('.plantilla-item').forEach(p => p.style.background='');
}
function escHtml(s) {
  return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
