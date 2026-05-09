<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
requireLogin();

$db   = getDB();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = (int)($_POST['cliente_id'] ?? 0);
    if (!$cliente_id && !empty($_POST['cliente_nombre'])) {
        $cCodigo = generarCodigoCliente($db);
        $db->prepare("INSERT INTO clientes (codigo,nombre,ruc_dni,telefono,whatsapp,email,tipo) VALUES (?,?,?,?,?,?,?)")
           ->execute([$cCodigo,trim($_POST['cliente_nombre']),trim($_POST['cliente_dni']??''),trim($_POST['cliente_tel']??''),trim($_POST['cliente_wa']??''),trim($_POST['cliente_email']??''),$_POST['cliente_tipo']??'persona']);
        $cliente_id = $db->lastInsertId();
    }

    $equipo_id = (int)($_POST['equipo_id'] ?? 0);
    if (!$equipo_id) {
        $db->prepare("INSERT INTO equipos (tipo_equipo_id,cliente_id,marca,modelo,serial,color,descripcion) VALUES (?,?,?,?,?,?,?)")
           ->execute([(int)$_POST['tipo_equipo_id'],$cliente_id,trim($_POST['equipo_marca']??''),trim($_POST['equipo_modelo']??''),trim($_POST['equipo_serial']??''),trim($_POST['equipo_color']??''),trim($_POST['equipo_desc']??'')]);
        $equipo_id = $db->lastInsertId();
    }

    // Checklist dinámico: items del DB + extras del form
    $checklistItems = $db->query("SELECT id,nombre FROM checklist_items WHERE activo=1 ORDER BY orden")->fetchAll();
    $checklist = [];
    foreach ($checklistItems as $item) {
        $key = 'check_item_' . $item['id'];
        $checklist[$item['nombre']] = $_POST[$key] ?? 'no_aplica';
    }
    $checklist['_observacion'] = trim($_POST['check_obs'] ?? '');

    $codigoOT      = generarCodigoOT($db);
    $codigoPublico = generarCodigoPublicoOT();

    $costoRep = (float)($_POST['costo_repuestos'] ?? 0);
    $costoMO  = (float)($_POST['costo_mano_obra']  ?? 0);
    $total    = $costoRep + $costoMO;
    $tecnico  = $_POST['tecnico_id'] ? (int)$_POST['tecnico_id'] : null;
    $adelanto        = (float)($_POST['adelanto'] ?? 0);
    $metodo_adelanto = $adelanto > 0 ? ($_POST['metodo_adelanto'] ?? 'efectivo') : null;

    $db->prepare("INSERT INTO ordenes_trabajo (codigo_ot,codigo_publico,cliente_id,equipo_id,tecnico_id,usuario_creador_id,estado,problema_reportado,diagnostico_inicial,checklist,costo_repuestos,costo_mano_obra,costo_total,precio_final,adelanto,metodo_adelanto,fecha_adelanto,fecha_estimada,firma_cliente,garantia_dias) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
       ->execute([$codigoOT,$codigoPublico,$cliente_id,$equipo_id,$tecnico,$user['id'],'ingresado',trim($_POST['problema_reportado']??''),trim($_POST['diagnostico_inicial']??''),json_encode($checklist,JSON_UNESCAPED_UNICODE),$costoRep,$costoMO,$total,$total,$adelanto,$metodo_adelanto,$adelanto>0?date('Y-m-d H:i:s'):null,$_POST['fecha_estimada']?:null,$_POST['firma_cliente']?:null,(int)($_POST['garantia_dias']??30)]);
    $otId = $db->lastInsertId();

    // Registrar adelanto en caja si aplica
    if ($adelanto > 0) {
        $cajaAbierta = $db->prepare("SELECT id FROM cajas WHERE fecha=CURDATE() AND estado='abierta' ORDER BY id DESC LIMIT 1");
        $cajaAbierta->execute();
        $caja = $cajaAbierta->fetchColumn();
        if ($caja) {
            $db->prepare("INSERT INTO movimientos_caja (caja_id,tipo,concepto,monto,referencia,usuario_id) VALUES (?,?,?,?,?,?)")
               ->execute([$caja,'ingreso','Adelanto reparación '.$codigoOT, $adelanto, $codigoOT, $user['id']]);
        }
    }

    $db->prepare("INSERT INTO historial_ot (ot_id,usuario_id,estado_nuevo,comentario) VALUES (?,?,?,?)")
       ->execute([$otId,$user['id'],'ingresado','OT creada']);

    if (!empty($_FILES['fotos']['name'][0])) {
        foreach ($_FILES['fotos']['name'] as $i => $fname) {
            if ($_FILES['fotos']['error'][$i] === 0) {
                $ruta = uploadFoto(['name'=>$fname,'type'=>$_FILES['fotos']['type'][$i],'tmp_name'=>$_FILES['fotos']['tmp_name'][$i],'size'=>$_FILES['fotos']['size'][$i]],'ot/'.$otId);
                if ($ruta) $db->prepare("INSERT INTO fotos_ot (ot_id,ruta,tipo) VALUES (?,?,'ingreso')")->execute([$otId,$ruta]);
            }
        }
    }

    setFlash('success',"OT $codigoOT creada. Código cliente: <strong>$codigoPublico</strong>");
    redirect(BASE_URL . 'modules/ot/ver.php?id=' . $otId);
}

// Cargar datos
$tiposEquipo    = $db->query("SELECT * FROM tipos_equipo WHERE activo=1 ORDER BY nombre")->fetchAll();
$marcas         = $db->query("SELECT * FROM marcas_equipo WHERE activo=1 ORDER BY nombre")->fetchAll();
$tecnicos       = $db->query("SELECT id,CONCAT(nombre,' ',apellido) as nombre FROM usuarios WHERE rol='tecnico' AND activo=1")->fetchAll();
$clientes       = $db->query("SELECT id,codigo,nombre,telefono FROM clientes WHERE activo=1 ORDER BY nombre")->fetchAll();
$checklistItems = $db->query("SELECT * FROM checklist_items WHERE activo=1 ORDER BY orden")->fetchAll();

$pageTitle  = 'Nueva OT — '.APP_NAME;
$breadcrumb = [['label'=>'Órdenes de trabajo','url'=>BASE_URL.'modules/ot/index.php'],['label'=>'Nueva OT','url'=>null]];
require_once __DIR__ . '/../../includes/header.php';
?>

<h5 class="fw-bold mb-4">Nueva orden de trabajo</h5>

<form method="POST" enctype="multipart/form-data" id="form-nueva-ot">
<div class="row g-3">
  <div class="col-lg-8">

    <!-- Cliente -->
    <div class="tr-card mb-3">
      <div class="tr-card-header">
        <h6 class="mb-0"><i data-feather="user" class="me-2" style="width:16px;height:16px"></i>Datos del cliente</h6>
        <div class="form-check form-switch mb-0">
          <input class="form-check-input" type="checkbox" id="toggle-nuevo-cliente" onchange="toggleNuevoCliente(this.checked)">
          <label class="form-check-label small" for="toggle-nuevo-cliente">Cliente nuevo</label>
        </div>
      </div>
      <div class="tr-card-body">
        <div id="bloque-cliente-existente">
          <label class="tr-form-label">Buscar cliente registrado *</label>
          <input type="text" id="input-buscar-cliente" class="form-control mb-1" placeholder="Escribe nombre, código o teléfono..." autocomplete="off"/>
          <div id="lista-clientes" style="max-height:220px;overflow-y:auto;border:1px solid #dee2e6;border-radius:6px;display:none;background:#fff;position:absolute;z-index:999;width:calc(100% - 2rem)"></div>
          <select name="cliente_id" id="sel-cliente" class="form-select" required style="display:none">
            <option value="">— Seleccionar cliente —</option>
            <?php foreach($clientes as $c): ?>
            <option value="<?= $c['id'] ?>"><?= sanitize($c['codigo'].' — '.$c['nombre']) ?> <?= $c['telefono']?'('.$c['telefono'].')':'' ?></option>
            <?php endforeach; ?>
          </select>
          <div id="cliente-seleccionado" class="small text-muted mt-1" style="display:none"></div>
        </div>
        <div id="bloque-cliente-nuevo" style="display:none">
          <div class="row g-2">
            <div class="col-md-2"><label class="tr-form-label">Tipo</label><select name="cliente_tipo" id="nuevo-cliente-tipo" class="form-select form-select-sm"><option value="persona">Persona</option><option value="empresa">Empresa</option></select></div>
            <div class="col-md-3">
              <label class="tr-form-label">DNI / RUC</label>
              <div class="input-group input-group-sm">
                <input type="text" name="cliente_dni" id="nuevo-cliente-dni" class="form-control form-control-sm" maxlength="11" inputmode="numeric" placeholder="8 o 11 dígitos"/>
                <span class="input-group-text" id="nuevo-dni-spinner" style="display:none"><span class="spinner-border spinner-border-sm"></span></span>
              </div>
              <div id="nuevo-dni-msg" class="small mt-1"></div>
            </div>
            <div class="col-md-4"><label class="tr-form-label">Nombre *</label><input type="text" name="cliente_nombre" id="nuevo-cliente-nombre" class="form-control form-control-sm"/></div>
            <div class="col-md-3"><label class="tr-form-label">Teléfono</label><input type="text" name="cliente_tel" class="form-control form-control-sm"/></div>
            <div class="col-md-3"><label class="tr-form-label">WhatsApp</label><input type="text" name="cliente_wa" class="form-control form-control-sm" placeholder="51999..."/></div>
            <div class="col-md-5"><label class="tr-form-label">Correo</label><input type="email" name="cliente_email" class="form-control form-control-sm"/></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Equipo -->
    <div class="tr-card mb-3">
      <div class="tr-card-header">
        <h6 class="mb-0"><i data-feather="cpu" class="me-2" style="width:16px;height:16px"></i>Datos del equipo</h6>
      </div>
      <div class="tr-card-body">
        <div class="row g-2">

          <!-- Tipo equipo + botón + -->
          <div class="col-md-4">
            <label class="tr-form-label">Tipo de equipo *</label>
            <div class="input-group">
              <select name="tipo_equipo_id" id="sel-tipo-equipo" class="form-select" required>
                <option value="">— Tipo —</option>
                <?php foreach($tiposEquipo as $t): ?>
                <option value="<?= $t['id'] ?>"><?= sanitize($t['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="button" class="btn btn-outline-success" title="Agregar nuevo tipo"
                      onclick="agregarOpcion('tipo_equipo','sel-tipo-equipo','Nuevo tipo de equipo (ej: Smartwatch, Proyector...)')">
                <i data-feather="plus" style="width:14px;height:14px"></i>
              </button>
            </div>
          </div>

          <!-- Marca + botón + -->
          <div class="col-md-4">
            <label class="tr-form-label">Marca</label>
            <div class="input-group">
              <select name="equipo_marca" id="sel-marca" class="form-select">
                <option value="">— Marca —</option>
                <?php foreach($marcas as $m): ?>
                <option value="<?= sanitize($m['nombre']) ?>"><?= sanitize($m['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="button" class="btn btn-outline-success" title="Agregar nueva marca"
                      onclick="agregarOpcion('marca','sel-marca','Nueva marca (ej: Xiaomi, Alienware...)')">
                <i data-feather="plus" style="width:14px;height:14px"></i>
              </button>
            </div>
          </div>

          <div class="col-md-4"><label class="tr-form-label">Modelo</label><input type="text" name="equipo_modelo" class="form-control"/></div>
          <div class="col-md-4"><label class="tr-form-label">Serial / N° serie</label><input type="text" name="equipo_serial" class="form-control" placeholder="Importante para garantía"/></div>
          <div class="col-md-2"><label class="tr-form-label">Color</label><input type="text" name="equipo_color" class="form-control" placeholder="Negro"/></div>
          <div class="col-md-6"><label class="tr-form-label">Descripción adicional</label><input type="text" name="equipo_desc" class="form-control" placeholder="Stickers, abolladuras previas..."/></div>
        </div>
      </div>
    </div>

    <!-- Diagnóstico -->
    <div class="tr-card mb-3">
      <div class="tr-card-header"><h6 class="mb-0"><i data-feather="search" class="me-2" style="width:16px;height:16px"></i>Diagnóstico</h6></div>
      <div class="tr-card-body">
        <div class="mb-3">
          <label class="tr-form-label">Problema reportado por el cliente *</label>
          <textarea name="problema_reportado" class="form-control" rows="3" required placeholder="Describe lo que el cliente indica que falla..."></textarea>
        </div>
        <div>
          <label class="tr-form-label">Diagnóstico inicial (técnico)</label>
          <textarea name="diagnostico_inicial" class="form-control" rows="2" placeholder="Primera revisión rápida..."></textarea>
        </div>
      </div>
    </div>

    <!-- Fotos -->
    <div class="tr-card mb-3">
      <div class="tr-card-header"><h6 class="mb-0"><i data-feather="camera" class="me-2" style="width:16px;height:16px"></i>Fotos del equipo</h6></div>
      <div class="tr-card-body">
        <div class="foto-drop-zone" id="foto-drop">
          <i data-feather="upload-cloud" style="width:32px;height:32px;color:#9ca3af"></i>
          <p class="text-muted mb-0 mt-2">Arrastra fotos aquí o haz clic</p>
          <p class="text-muted small">JPG, PNG, WEBP — máx. 5MB</p>
          <input type="file" id="input-fotos" name="fotos[]" multiple accept="image/*" style="display:none"/>
        </div>
        <div class="foto-preview-grid mt-2" id="preview-fotos"></div>
      </div>
    </div>

  </div>

  <!-- Columna derecha -->
  <div class="col-lg-4">

    <!-- Checklist dinámico -->
    <div class="tr-card mb-3">
      <div class="tr-card-header">
        <h6 class="mb-0"><i data-feather="check-square" class="me-2" style="width:16px;height:16px"></i>Checklist físico</h6>
        <button type="button" class="btn btn-outline-success btn-sm py-0"
                onclick="agregarChecklistItem()" title="Agregar nuevo ítem">
          <i data-feather="plus" style="width:13px;height:13px"></i> Ítem
        </button>
      </div>
      <div class="tr-card-body p-2" id="checklist-container">
        <?php foreach($checklistItems as $item): ?>
        <div class="checklist-item" id="chk-row-<?= $item['id'] ?>">
          <span class="small"><?= sanitize($item['nombre']) ?></span>
          <div class="btn-group btn-group-sm" role="group">
            <?php foreach(['bueno'=>'Bueno','malo'=>'Malo','no_aplica'=>'N/A'] as $val=>$txt): ?>
            <input type="radio" class="btn-check" name="check_item_<?= $item['id'] ?>" id="c_<?= $item['id'] ?>_<?= $val ?>" value="<?= $val ?>" <?= $val==='no_aplica'?'checked':'' ?>>
            <label class="btn btn-outline-<?= $val==='bueno'?'success':($val==='malo'?'danger':'secondary') ?> btn-sm py-0"
                   for="c_<?= $item['id'] ?>_<?= $val ?>" style="font-size:11px"><?= $txt ?></label>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
        <div class="mt-2">
          <label class="tr-form-label small">Observación</label>
          <textarea name="check_obs" class="form-control form-control-sm" rows="2" placeholder="Golpes, rayones, partes faltantes..."></textarea>
        </div>
      </div>
    </div>

    <!-- Asignación -->
    <div class="tr-card mb-3">
      <div class="tr-card-header"><h6 class="mb-0"><i data-feather="settings" class="me-2" style="width:16px;height:16px"></i>Asignación</h6></div>
      <div class="tr-card-body">
        <div class="mb-2"><label class="tr-form-label">Técnico asignado</label>
          <select name="tecnico_id" class="form-select form-select-sm">
            <option value="">Sin asignar</option>
            <?php foreach($tecnicos as $t): ?><option value="<?= $t['id'] ?>"><?= sanitize($t['nombre']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2"><label class="tr-form-label">Fecha estimada de entrega</label><input type="date" name="fecha_estimada" class="form-control form-control-sm" min="<?= date('Y-m-d') ?>"/></div>
        <div class="mb-2"><label class="tr-form-label">Garantía (días)</label><input type="number" name="garantia_dias" class="form-control form-control-sm" value="30" min="0"/></div>
      </div>
    </div>

    <!-- Presupuesto -->
    <div class="tr-card mb-3">
      <div class="tr-card-header"><h6 class="mb-0"><i data-feather="dollar-sign" class="me-2" style="width:16px;height:16px"></i>Presupuesto inicial</h6></div>
      <div class="tr-card-body">
        <div class="mb-2"><label class="tr-form-label">Costo repuestos (S/)</label><input type="number" id="costo_repuestos" name="costo_repuestos" class="form-control form-control-sm currency-input" step="0.01" value="0"/></div>
        <div class="mb-2"><label class="tr-form-label">Mano de obra (S/)</label><input type="number" id="costo_mano_obra" name="costo_mano_obra" class="form-control form-control-sm currency-input" step="0.01" value="0"/></div>
        <div class="mb-2"><label class="tr-form-label">Descuento (S/)</label><input type="number" id="descuento" name="descuento" class="form-control form-control-sm currency-input" step="0.01" value="0"/></div>
        <div class="p-2 bg-light rounded text-end">
          <span class="small text-muted">Total:</span>
          <span class="fw-bold fs-5 ms-2" id="total_display">S/ 0.00</span>
          <input type="hidden" name="precio_final" id="precio_final" value="0"/>
        </div>
        <hr class="my-2"/>
        <div class="mb-2"><label class="tr-form-label">Adelanto (S/)</label><input type="number" name="adelanto" class="form-control form-control-sm currency-input" step="0.01" value="0" min="0"/></div>
        <div class="mb-2">
          <label class="tr-form-label">Método adelanto</label>
          <select name="metodo_adelanto" class="form-select form-select-sm">
            <option value="efectivo">Efectivo</option>
            <option value="yape">Yape</option>
            <option value="plin">Plin</option>
            <option value="tarjeta">Tarjeta</option>
            <option value="transferencia">Transferencia</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Firma -->
    <div class="tr-card mb-3">
      <div class="tr-card-header"><h6 class="mb-0"><i data-feather="edit-3" class="me-2" style="width:16px;height:16px"></i>Firma del cliente</h6></div>
      <div class="tr-card-body">
        <p class="text-muted small mb-2">El cliente acepta el ingreso y condiciones del servicio.</p>
        <div id="firma-canvas-wrapper" style="height:120px"><canvas id="firma-canvas" style="width:100%;height:120px"></canvas></div>
        <button type="button" class="btn btn-sm btn-outline-secondary mt-2 w-100" id="btn-clear-firma">
          <i data-feather="trash-2" style="width:13px;height:13px"></i> Limpiar firma
        </button>
        <input type="hidden" name="firma_cliente" id="firma_cliente"/>
      </div>
    </div>

    <button type="submit" class="btn btn-primary w-100 btn-lg">
      <i data-feather="save" style="width:18px;height:18px"></i> Crear orden de trabajo
    </button>
  </div>
</div>
</form>

<!-- Modal para agregar tipo/marca -->
<div class="modal fade" id="modal-agregar" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title" id="modal-agregar-titulo">Agregar nuevo</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="text" id="input-nuevo-valor" class="form-control" placeholder="Nombre..."/>
        <div class="text-danger small mt-1" id="error-agregar" style="display:none"></div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-success btn-sm" id="btn-confirmar-agregar">
          <i data-feather="plus" style="width:13px;height:13px"></i> Agregar
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal para nuevo ítem checklist -->
<div class="modal fade" id="modal-checklist" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title">Nuevo ítem de checklist</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="text" id="input-nuevo-check" class="form-control" placeholder="Ej: Micrófono funcional"/>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-success btn-sm" id="btn-confirmar-check">Agregar</button>
      </div>
    </div>
  </div>
</div>

<?php
$pageScripts = <<<'JS'
<script>
// ── Buscador de cliente ──────────────────────────────────
(function() {
  const input    = document.getElementById('input-buscar-cliente');
  const lista    = document.getElementById('lista-clientes');
  const select   = document.getElementById('sel-cliente');
  const info     = document.getElementById('cliente-seleccionado');
  const opciones = Array.from(select.options).slice(1); // sin el placeholder

  input.addEventListener('input', function() {
    const q = this.value.trim().toLowerCase();
    lista.innerHTML = '';
    if (!q) { lista.style.display = 'none'; return; }
    const filtrados = opciones.filter(o => o.text.toLowerCase().includes(q)).slice(0, 20);
    if (!filtrados.length) { lista.style.display = 'none'; return; }
    filtrados.forEach(o => {
      const div = document.createElement('div');
      div.textContent = o.text;
      div.style.cssText = 'padding:8px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid #f3f4f6';
      div.addEventListener('mouseenter', () => div.style.background = '#f3f4f6');
      div.addEventListener('mouseleave', () => div.style.background = '');
      div.addEventListener('click', () => {
        select.value = o.value;
        input.value  = o.text;
        info.textContent = '✓ ' + o.text;
        info.style.display = '';
        lista.style.display = 'none';
      });
      lista.appendChild(div);
    });
    lista.style.display = '';
  });

  document.addEventListener('click', e => {
    if (!input.contains(e.target) && !lista.contains(e.target)) lista.style.display = 'none';
  });
})();

function toggleNuevoCliente(nuevo) {
  document.getElementById('bloque-cliente-existente').style.display = nuevo ? 'none' : '';
  document.getElementById('bloque-cliente-nuevo').style.display     = nuevo ? ''     : 'none';
  document.getElementById('sel-cliente').required = !nuevo;
}

// ── API DNI/RUC para cliente nuevo ──────────────────────
(function() {
  const inputDoc    = document.getElementById('nuevo-cliente-dni');
  const inputNombre = document.getElementById('nuevo-cliente-nombre');
  const inputTipo   = document.getElementById('nuevo-cliente-tipo');
  const spinner     = document.getElementById('nuevo-dni-spinner');
  const msg         = document.getElementById('nuevo-dni-msg');

  inputDoc.addEventListener('keypress', e => { if (!/\d/.test(e.key)) e.preventDefault(); });
  inputDoc.addEventListener('input', () => { inputDoc.value = inputDoc.value.replace(/\D/g, ''); });

  let timer = null;
  inputDoc.addEventListener('input', function() {
    clearTimeout(timer);
    const doc = this.value.trim();
    msg.textContent = '';
    if (doc.length !== 8 && doc.length !== 11) return;
    spinner.style.display = '';
    timer = setTimeout(() => {
      fetch(window.BASE_URL + 'modules/clientes/api_documento.php?doc=' + doc)
        .then(r => r.json())
        .then(data => {
          spinner.style.display = 'none';
          if (data.ok) {
            inputNombre.value = data.nombre;
            inputTipo.value   = data.tipo;
            msg.innerHTML = '<span class="text-success">✓ Encontrado</span>';
          } else {
            msg.innerHTML = '<span class="text-danger">No encontrado</span>';
          }
        })
        .catch(() => { spinner.style.display = 'none'; });
    }, 400);
  });
})();

// ── Firma y fotos ────────────────────────────────────────
initFirma('firma-canvas', 'firma_cliente');
initFotoDrop('foto-drop', 'preview-fotos', 'input-fotos');

// ── Agregar tipo equipo o marca con botón + ──────────────
let _accionActual = '';
let _selectActual = null;

function agregarOpcion(accion, selectId, placeholder) {
  _accionActual = accion;
  _selectActual = document.getElementById(selectId);
  document.getElementById('modal-agregar-titulo').textContent =
    accion === 'tipo_equipo' ? '➕ Nuevo tipo de equipo' : '➕ Nueva marca';
  const inp = document.getElementById('input-nuevo-valor');
  inp.value = '';
  inp.placeholder = placeholder;
  document.getElementById('error-agregar').style.display = 'none';
  new bootstrap.Modal(document.getElementById('modal-agregar')).show();
  setTimeout(() => inp.focus(), 400);
}

document.getElementById('btn-confirmar-agregar').addEventListener('click', async function() {
  const valor = document.getElementById('input-nuevo-valor').value.trim();
  if (!valor) return;

  const fd = new FormData();
  fd.append('accion', _accionActual);
  fd.append('valor',  valor);

  const r = await fetch('api_agregar.php', { method:'POST', body: fd });
  const d = await r.json();

  if (d.ok) {
    // Agregar opción al select
    const opt = new Option(d.nombre, _accionActual === 'tipo_equipo' ? d.id : d.nombre, true, true);
    _selectActual.add(opt);
    _selectActual.value = _accionActual === 'tipo_equipo' ? d.id : d.nombre;
    bootstrap.Modal.getInstance(document.getElementById('modal-agregar')).hide();
  } else {
    document.getElementById('error-agregar').textContent = d.error || 'Error';
    document.getElementById('error-agregar').style.display = '';
  }
});

// Confirmar con Enter
document.getElementById('input-nuevo-valor').addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); document.getElementById('btn-confirmar-agregar').click(); }
});

// ── Agregar ítem checklist ───────────────────────────────
let _checkCounter = 9000; // ID temporal para nuevos ítems

function agregarChecklistItem() {
  document.getElementById('input-nuevo-check').value = '';
  new bootstrap.Modal(document.getElementById('modal-checklist')).show();
  setTimeout(() => document.getElementById('input-nuevo-check').focus(), 400);
}

document.getElementById('btn-confirmar-check').addEventListener('click', async function() {
  const valor = document.getElementById('input-nuevo-check').value.trim();
  if (!valor) return;

  const fd = new FormData();
  fd.append('accion', 'checklist_item');
  fd.append('valor',  valor);

  const r = await fetch('api_agregar.php', { method:'POST', body: fd });
  const d = await r.json();

  if (d.ok) {
    // Insertar nuevo ítem en el checklist visualmente
    const container = document.getElementById('checklist-container');
    const obsDiv    = container.querySelector('div.mt-2'); // div de observación
    const id        = d.id;
    const div       = document.createElement('div');
    div.className   = 'checklist-item';
    div.id          = 'chk-row-' + id;
    div.innerHTML   = `
      <span class="small">${escHtml(d.nombre)}</span>
      <div class="btn-group btn-group-sm" role="group">
        ${['bueno','malo','no_aplica'].map((v,i) => `
          <input type="radio" class="btn-check" name="check_item_${id}" id="c_${id}_${v}" value="${v}" ${v==='no_aplica'?'checked':''}>
          <label class="btn btn-outline-${v==='bueno'?'success':v==='malo'?'danger':'secondary'} btn-sm py-0"
                 for="c_${id}_${v}" style="font-size:11px">${['Bueno','Malo','N/A'][i]}</label>
        `).join('')}
      </div>`;
    container.insertBefore(div, obsDiv);
    bootstrap.Modal.getInstance(document.getElementById('modal-checklist')).hide();
    // Re-init feather en el nuevo elemento
    feather.replace();
  }
});

document.getElementById('input-nuevo-check').addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); document.getElementById('btn-confirmar-check').click(); }
});

function escHtml(s) {
  return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
