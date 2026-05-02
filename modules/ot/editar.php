  <?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
requireLogin();

$db   = getDB();
$user = currentUser();
$id   = (int)($_GET['id'] ?? 0);

// Cargar OT
$ot = $db->prepare("
    SELECT ot.*, c.nombre AS cliente_nombre, c.ruc_dni, c.telefono, c.whatsapp, c.email AS cliente_email,
           te.nombre AS tipo_equipo, e.tipo_equipo_id, e.marca, e.modelo, e.serial, e.color, e.descripcion AS equipo_desc
    FROM ordenes_trabajo ot
    JOIN clientes c ON c.id = ot.cliente_id
    JOIN equipos e ON e.id = ot.equipo_id
    JOIN tipos_equipo te ON te.id = e.tipo_equipo_id
    WHERE ot.id = ?");
$ot->execute([$id]);
$ot = $ot->fetch();
if (!$ot) { setFlash('danger','OT no encontrada'); redirect(BASE_URL.'modules/ot/index.php'); }

// Guardar cambios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Si viene cliente nuevo, crearlo primero
    $cliente_id = (int)($_POST['cliente_id'] ?? 0);
    if (!$cliente_id && !empty($_POST['cliente_nombre'])) {
        $cCodigo = generarCodigoCliente($db);
        $db->prepare("INSERT INTO clientes (codigo,nombre,ruc_dni,telefono,whatsapp,email,tipo) VALUES (?,?,?,?,?,?,?)")
           ->execute([$cCodigo,trim($_POST['cliente_nombre']),trim($_POST['cliente_dni']??''),trim($_POST['cliente_tel']??''),trim($_POST['cliente_wa']??''),trim($_POST['cliente_email']??''),$_POST['cliente_tipo']??'persona']);
        $cliente_id = $db->lastInsertId();
    }
    if (!$cliente_id) $cliente_id = $ot['cliente_id'];
    // Actualizar OT
    $costoRep = (float)($_POST['costo_repuestos'] ?? 0);
    $costoMO  = (float)($_POST['costo_mano_obra']  ?? 0);
    $desc     = (float)($_POST['descuento']         ?? 0);
    $total    = round($costoRep + $costoMO - $desc, 2);
    $tecnico  = $_POST['tecnico_id'] ? (int)$_POST['tecnico_id'] : null;

    $db->prepare("
        UPDATE ordenes_trabajo SET
            cliente_id          = ?,
            tecnico_id          = ?,
            problema_reportado  = ?,
            diagnostico_inicial = ?,
            diagnostico_tecnico = ?,
            observaciones       = ?,
            costo_repuestos     = ?,
            costo_mano_obra     = ?,
            descuento           = ?,
            costo_total         = ?,
            precio_final        = ?,
            fecha_estimada      = ?,
            garantia_dias       = ?
        WHERE id = ?
    ")->execute([
        $cliente_id,
        $tecnico,
        trim($_POST['problema_reportado']  ?? ''),
        trim($_POST['diagnostico_inicial'] ?? ''),
        trim($_POST['diagnostico_tecnico'] ?? ''),
        trim($_POST['observaciones']       ?? ''),
        $costoRep, $costoMO, $desc, $total, $total,
        $_POST['fecha_estimada'] ?: null,
        (int)($_POST['garantia_dias'] ?? 30),
        $id,
    ]);

    // Actualizar equipo
    $db->prepare("UPDATE equipos SET tipo_equipo_id=?, marca=?, modelo=?, serial=?, color=?, descripcion=? WHERE id=?")
       ->execute([
           (int)($_POST['tipo_equipo_id'] ?? $ot['tipo_equipo_id']),
           trim($_POST['equipo_marca']  ?? ''),
           trim($_POST['equipo_modelo'] ?? ''),
           trim($_POST['equipo_serial'] ?? ''),
           trim($_POST['equipo_color']  ?? ''),
           trim($_POST['equipo_desc']   ?? ''),
           $ot['equipo_id'],
       ]);

    // Subir nuevas fotos
    if (!empty($_FILES['fotos']['name'][0])) {
        foreach ($_FILES['fotos']['name'] as $i => $fname) {
            if ($_FILES['fotos']['error'][$i] === 0) {
                $ruta = uploadFoto([
                    'name'=>$fname,'type'=>$_FILES['fotos']['type'][$i],
                    'tmp_name'=>$_FILES['fotos']['tmp_name'][$i],'size'=>$_FILES['fotos']['size'][$i]
                ], 'ot/'.$id);
                if ($ruta) $db->prepare("INSERT INTO fotos_ot (ot_id,ruta,tipo) VALUES (?,?,'proceso')")->execute([$id,$ruta]);
            }
        }
    }

    // Registrar repuestos (borrar y reinsertar)
    $db->prepare("DELETE FROM ot_repuestos WHERE ot_id=?")->execute([$id]);
    $descs  = $_POST['rep_desc']   ?? [];
    $cants  = $_POST['rep_cant']   ?? [];
    $precios= $_POST['rep_precio'] ?? [];
    foreach ($descs as $i => $desc2) {
        $d = trim($desc2); $c = (float)($cants[$i]??1); $p = (float)($precios[$i]??0);
        if (!$d) continue;
        $db->prepare("INSERT INTO ot_repuestos (ot_id,descripcion,cantidad,precio_unit,subtotal) VALUES (?,?,?,?,?)")
           ->execute([$id, $d, $c, $p, round($c*$p,2)]);
    }

    setFlash('success', 'OT actualizada correctamente.');
    redirect(BASE_URL.'modules/ot/ver.php?id='.$id);
}

$tiposEquipo = $db->query("SELECT * FROM tipos_equipo WHERE activo=1 ORDER BY nombre")->fetchAll();
$tecnicos    = $db->query("SELECT id, CONCAT(nombre,' ',apellido) AS nombre FROM usuarios WHERE rol='tecnico' AND activo=1")->fetchAll();
$clientes    = $db->query("SELECT id,codigo,nombre,telefono FROM clientes WHERE activo=1 ORDER BY nombre")->fetchAll();
$clientes    = $db->query("SELECT id,codigo,nombre,telefono FROM clientes WHERE activo=1 ORDER BY nombre")->fetchAll();
$repuestos   = $db->prepare("SELECT * FROM ot_repuestos WHERE ot_id=? ORDER BY id"); $repuestos->execute([$id]); $repuestos=$repuestos->fetchAll();
$fotos       = $db->prepare("SELECT * FROM fotos_ot WHERE ot_id=? ORDER BY id"); $fotos->execute([$id]); $fotos=$fotos->fetchAll();

$pageTitle  = 'Editar OT '.$ot['codigo_ot'].' — '.APP_NAME;
$breadcrumb = [
    ['label'=>'Órdenes de trabajo','url'=>BASE_URL.'modules/ot/index.php'],
    ['label'=>$ot['codigo_ot'],'url'=>BASE_URL.'modules/ot/ver.php?id='.$id],
    ['label'=>'Editar','url'=>null],
];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">Editar OT</h4>
    <div class="text-muted small mt-1"><?= sanitize($ot['codigo_ot']) ?> — <?= sanitize($ot['cliente_nombre']) ?></div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>modules/ot/ver.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm">← Volver al detalle</a>
    <a href="<?= BASE_URL ?>modules/ot/pdf.php?id=<?= $id ?>" target="_blank" class="btn btn-outline-danger btn-sm">PDF</a>
  </div>
</div>

<form method="POST" enctype="multipart/form-data">
<div class="row g-3">

  <!-- Columna principal -->
  <div class="col-lg-8">

    <!-- Datos del equipo -->
    <div class="tr-card mb-3">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold"><i data-feather="cpu" class="me-2" style="width:15px;height:15px"></i>EQUIPO</h6></div>
      <div class="tr-card-body">
        <div class="row g-2">
          <div class="col-md-4">
            <label class="tr-form-label">Tipo de equipo</label>
          <select name="tipo_equipo_id" class="form-select">
              <?php foreach($tiposEquipo as $t): ?>
              <option value="<?= $t['id'] ?>" <?= $t['id'] == $ot['tipo_equipo_id'] ? 'selected' : '' ?>><?= sanitize($t['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="tr-form-label">Marca</label>
            <input type="text" name="equipo_marca" class="form-control" value="<?= sanitize($ot['marca']??'') ?>"/>
          </div>
          <div class="col-md-4">
            <label class="tr-form-label">Modelo</label>
            <input type="text" name="equipo_modelo" class="form-control" value="<?= sanitize($ot['modelo']??'') ?>"/>
          </div>
          <div class="col-md-4">
            <label class="tr-form-label">Serial</label>
            <input type="text" name="equipo_serial" class="form-control" value="<?= sanitize($ot['serial']??'') ?>"/>
          </div>
          <div class="col-md-2">
            <label class="tr-form-label">Color</label>
            <input type="text" name="equipo_color" class="form-control" value="<?= sanitize($ot['color']??'') ?>"/>
          </div>
          <div class="col-md-6">
            <label class="tr-form-label">Descripción</label>
            <input type="text" name="equipo_desc" class="form-control" value="<?= sanitize($ot['equipo_desc']??'') ?>"/>
          </div>
        </div>
      </div>
    </div>

    <!-- Diagnóstico -->
    <div class="tr-card mb-3">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold"><i data-feather="search" class="me-2" style="width:15px;height:15px"></i>DIAGNÓSTICO</h6></div>
      <div class="tr-card-body">
        <div class="mb-3">
          <label class="tr-form-label">Problema reportado por el cliente *</label>
          <textarea name="problema_reportado" class="form-control" rows="3" required><?= sanitize($ot['problema_reportado']) ?></textarea>
        </div>
        <div class="mb-3">
          <label class="tr-form-label">Diagnóstico inicial</label>
          <textarea name="diagnostico_inicial" class="form-control" rows="2"><?= sanitize($ot['diagnostico_inicial']??'') ?></textarea>
        </div>
        <div class="mb-3">
          <label class="tr-form-label">Diagnóstico técnico detallado <span class="badge bg-primary ms-1">Aparece en el comprobante</span></label>
          <textarea name="diagnostico_tecnico" class="form-control" rows="3"><?= sanitize($ot['diagnostico_tecnico']??'') ?></textarea>
        </div>
        <div>
          <label class="tr-form-label">Observaciones</label>
          <textarea name="observaciones" class="form-control" rows="2"><?= sanitize($ot['observaciones']??'') ?></textarea>
        </div>
      </div>
    </div>

    <!-- Repuestos y servicios -->
    <div class="tr-card mb-3">
      <div class="tr-card-header">
        <h6 class="mb-0 small fw-semibold"><i data-feather="tool" class="me-2" style="width:15px;height:15px"></i>REPUESTOS Y SERVICIOS</h6>
        <button type="button" class="btn btn-outline-success btn-sm" onclick="agregarRepuesto()">
          <i data-feather="plus" style="width:13px;height:13px"></i> Agregar ítem
        </button>
      </div>
      <div class="tr-card-body p-0">
        <table class="tr-table" id="tabla-repuestos">
          <thead><tr><th>Descripción *</th><th style="width:80px">Cant.</th><th style="width:100px">P. Unit (S/)</th><th style="width:90px">Subtotal</th><th style="width:36px"></th></tr></thead>
          <tbody id="tbody-repuestos">
            <?php if(empty($repuestos)): ?>
            <tr id="fila-vacia-rep"><td colspan="5" class="text-center text-muted py-3 small">Sin repuestos — usa el botón para agregar</td></tr>
            <?php else: ?>
            <?php foreach($repuestos as $r): ?>
            <tr class="rep-row">
              <td><input type="text" name="rep_desc[]" class="form-control form-control-sm" value="<?= sanitize($r['descripcion']) ?>" required/></td>
              <td><input type="number" name="rep_cant[]" class="form-control form-control-sm text-center rep-cant" value="<?= $r['cantidad'] ?>" min="0.01" step="0.01" onchange="recalcRep(this)"/></td>
              <td><input type="number" name="rep_precio[]" class="form-control form-control-sm text-end rep-precio" value="<?= $r['precio_unit'] ?>" min="0" step="0.01" onchange="recalcRep(this)"/></td>
              <td class="rep-subtotal fw-semibold text-end small pe-2"><?= formatMoney($r['subtotal']) ?></td>
              <td><button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" onclick="this.closest('tr').remove();calcTotalesRep()">✕</button></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Fotos adicionales -->
    <div class="tr-card mb-3">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold"><i data-feather="camera" class="me-2" style="width:15px;height:15px"></i>FOTOS EXISTENTES Y NUEVAS</h6></div>
      <div class="tr-card-body">
        <?php if($fotos): ?>
        <div class="foto-preview-grid mb-3">
          <?php foreach($fotos as $f): ?>
          <div class="foto-preview-item">
            <a href="<?= UPLOAD_URL.$f['ruta'] ?>" target="_blank">
              <img src="<?= UPLOAD_URL.$f['ruta'] ?>" alt="foto"/>
            </a>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="foto-drop-zone" id="foto-drop">
          <i data-feather="upload-cloud" style="width:28px;height:28px;color:#9ca3af"></i>
          <p class="text-muted small mb-0 mt-1">Agregar más fotos (proceso/reparación)</p>
          <input type="file" id="input-fotos" name="fotos[]" multiple accept="image/*" style="display:none"/>
        </div>
        <div class="foto-preview-grid mt-2" id="preview-fotos"></div>
      </div>
    </div>

  </div><!-- /col-8 -->

  <!-- Columna derecha -->
  <div class="col-lg-4">

    <!-- Cliente -->
    <div class="tr-card mb-3">
      <div class="tr-card-header">
        <h6 class="mb-0 small fw-semibold">CLIENTE</h6>
        <div class="form-check form-switch mb-0">
          <input class="form-check-input" type="checkbox" id="toggle-nuevo-cliente-edit" onchange="toggleNuevoClienteEdit(this.checked)">
          <label class="form-check-label small" for="toggle-nuevo-cliente-edit">Cliente nuevo</label>
        </div>
      </div>
      <div class="tr-card-body">
        <div id="bloque-cliente-existente-edit">
          <label class="tr-form-label">Buscar cliente *</label>
          <input type="text" id="input-buscar-cliente" class="form-control form-control-sm mb-1" placeholder="Escribe nombre, código o teléfono..." autocomplete="off"/>
          <div id="lista-clientes" style="max-height:220px;overflow-y:auto;border:1px solid #dee2e6;border-radius:6px;display:none;background:#fff;position:absolute;z-index:999;width:calc(100% - 2rem)"></div>
          <select name="cliente_id" id="sel-cliente" class="form-select form-select-sm" required style="display:none">
            <?php foreach($clientes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $c['id'] == $ot['cliente_id'] ? 'selected' : '' ?>>
              <?= sanitize($c['codigo'].' — '.$c['nombre']) ?> <?= $c['telefono']?'('.$c['telefono'].')':'' ?>
            </option>
            <?php endforeach; ?>
          </select>
          <div id="cliente-seleccionado" class="small text-success mt-1"></div>
        </div>
        <div id="bloque-cliente-nuevo-edit" style="display:none">
          <div class="row g-2">
            <div class="col-md-4"><label class="tr-form-label">Tipo</label><select name="cliente_tipo" id="edit-cliente-tipo" class="form-select form-select-sm"><option value="persona">Persona</option><option value="empresa">Empresa</option></select></div>
            <div class="col-md-8">
              <label class="tr-form-label">DNI / RUC</label>
              <div class="input-group input-group-sm">
                <input type="text" name="cliente_dni" id="edit-cliente-dni" class="form-control form-control-sm" maxlength="11" inputmode="numeric" placeholder="8 o 11 dígitos"/>
                <span class="input-group-text" id="edit-dni-spinner" style="display:none"><span class="spinner-border spinner-border-sm"></span></span>
              </div>
              <div id="edit-dni-msg" class="small mt-1"></div>
            </div>
            <div class="col-12"><label class="tr-form-label">Nombre *</label><input type="text" name="cliente_nombre" id="edit-cliente-nombre" class="form-control form-control-sm"/></div>
            <div class="col-md-6"><label class="tr-form-label">Teléfono</label><input type="text" name="cliente_tel" class="form-control form-control-sm"/></div>
            <div class="col-md-6"><label class="tr-form-label">WhatsApp</label><input type="text" name="cliente_wa" class="form-control form-control-sm" placeholder="51999..."/></div>
            <div class="col-12"><label class="tr-form-label">Correo</label><input type="email" name="cliente_email" class="form-control form-control-sm"/></div>
          </div>
        </div>
        <div class="small mt-2 mb-1"><strong>Código OT:</strong> <?= sanitize($ot['codigo_ot']) ?></div>
        <div class="small mb-1"><strong>Estado actual:</strong> <?= estadoOTBadge($ot['estado']) ?></div>
        <div class="small mb-1"><strong>Ingreso:</strong> <?= formatDate($ot['fecha_ingreso']) ?></div>
        <div class="alert alert-info py-1 mt-2 small mb-0">
          Para cambiar el <strong>estado</strong> usa el botón en el detalle de la OT.
        </div>
      </div>
    </div>

    <!-- Asignación -->
    <div class="tr-card mb-3">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold"><i data-feather="settings" class="me-2" style="width:15px;height:15px"></i>ASIGNACIÓN</h6></div>
      <div class="tr-card-body">
        <div class="mb-2">
          <label class="tr-form-label">Técnico asignado</label>
          <select name="tecnico_id" class="form-select form-select-sm">
            <option value="">Sin asignar</option>
            <?php foreach($tecnicos as $t): ?>
            <option value="<?= $t['id'] ?>" <?= $ot['tecnico_id']==$t['id']?'selected':'' ?>><?= sanitize($t['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="tr-form-label">Fecha estimada de entrega</label>
          <input type="date" name="fecha_estimada" class="form-control form-control-sm" value="<?= $ot['fecha_estimada']??'' ?>"/>
        </div>
        <div class="mb-2">
          <label class="tr-form-label">Garantía (días)</label>
          <input type="number" name="garantia_dias" class="form-control form-control-sm" value="<?= $ot['garantia_dias']??30 ?>" min="0"/>
        </div>
      </div>
    </div>

    <!-- Presupuesto -->
    <div class="tr-card mb-3">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold"><i data-feather="dollar-sign" class="me-2" style="width:15px;height:15px"></i>PRESUPUESTO</h6></div>
      <div class="tr-card-body">
        <div class="mb-2">
          <label class="tr-form-label">Costo repuestos (S/)</label>
          <input type="number" id="costo_repuestos" name="costo_repuestos" class="form-control form-control-sm currency-input" step="0.01" value="<?= $ot['costo_repuestos'] ?>"/>
        </div>
        <div class="mb-2">
          <label class="tr-form-label">Mano de obra (S/)</label>
          <input type="number" id="costo_mano_obra" name="costo_mano_obra" class="form-control form-control-sm currency-input" step="0.01" value="<?= $ot['costo_mano_obra'] ?>"/>
        </div>
        <div class="mb-2">
          <label class="tr-form-label">Descuento (S/)</label>
          <input type="number" id="descuento" name="descuento" class="form-control form-control-sm currency-input" step="0.01" value="<?= $ot['descuento']??0 ?>"/>
        </div>
        <div class="p-2 bg-light rounded text-end">
          <span class="small text-muted">Total:</span>
          <span class="fw-bold fs-5 ms-2" id="total_display"><?= formatMoney($ot['precio_final']) ?></span>
          <input type="hidden" name="precio_final" id="precio_final" value="<?= $ot['precio_final'] ?>"/>
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary w-100 btn-lg mt-3">
      <i data-feather="save" style="width:16px;height:16px"></i> Guardar cambios
    </button>
    <a href="<?= BASE_URL ?>modules/ot/ver.php?id=<?= $id ?>" class="btn btn-outline-secondary w-100 mt-2">Cancelar</a>

  </div>
</div>
</form>

<?php
$pageScripts = <<<'JS'
<script>
initFotoDrop('foto-drop','preview-fotos','input-fotos');

// Buscador de cliente
(function() {
  const input    = document.getElementById('input-buscar-cliente');
  const lista    = document.getElementById('lista-clientes');
  const select   = document.getElementById('sel-cliente');
  const info     = document.getElementById('cliente-seleccionado');
  const opciones = Array.from(select.options);

  const selActual = select.options[select.selectedIndex];
  if (selActual) { input.value = selActual.text; info.textContent = '✓ ' + selActual.text; }

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

function toggleNuevoClienteEdit(nuevo) {
  document.getElementById('bloque-cliente-existente-edit').style.display = nuevo ? 'none' : '';
  document.getElementById('bloque-cliente-nuevo-edit').style.display     = nuevo ? ''     : 'none';
  const sel = document.getElementById('sel-cliente');
  sel.required = !nuevo;
  if (nuevo) sel.value = '';
}

// API DNI/RUC cliente nuevo en editar
(function() {
  const inputDoc    = document.getElementById('edit-cliente-dni');
  const inputNombre = document.getElementById('edit-cliente-nombre');
  const inputTipo   = document.getElementById('edit-cliente-tipo');
  const spinner     = document.getElementById('edit-dni-spinner');
  const msg         = document.getElementById('edit-dni-msg');
  if (!inputDoc) return;
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
function agregarRepuesto() {
  const tbody = document.getElementById('tbody-repuestos');
  const vacia = document.getElementById('fila-vacia-rep');
  if (vacia) vacia.remove();
  const tr = document.createElement('tr');
  tr.className = 'rep-row';
  tr.innerHTML = `
    <td><input type="text" name="rep_desc[]" class="form-control form-control-sm" placeholder="Descripción del servicio o repuesto" required/></td>
    <td><input type="number" name="rep_cant[]" class="form-control form-control-sm text-center rep-cant" value="1" min="0.01" step="0.01" onchange="recalcRep(this)"/></td>
    <td><input type="number" name="rep_precio[]" class="form-control form-control-sm text-end rep-precio" value="0" min="0" step="0.01" onchange="recalcRep(this)"/></td>
    <td class="rep-subtotal fw-semibold text-end small pe-2">S/ 0.00</td>
    <td><button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" onclick="this.closest('tr').remove();calcTotalesRep()">✕</button></td>`;
  tbody.appendChild(tr);
}

// Recalcular subtotal de fila
function recalcRep(inp) {
  const tr  = inp.closest('tr');
  const c   = parseFloat(tr.querySelector('.rep-cant').value)   || 0;
  const p   = parseFloat(tr.querySelector('.rep-precio').value) || 0;
  const sub = c * p;
  tr.querySelector('.rep-subtotal').textContent = 'S/ ' + sub.toFixed(2);
  calcTotalesRep();
}

// Sumar todos los subtotales al campo costo_repuestos
function calcTotalesRep() {
  let total = 0;
  document.querySelectorAll('.rep-row').forEach(tr => {
    const c = parseFloat(tr.querySelector('.rep-cant')?.value)   || 0;
    const p = parseFloat(tr.querySelector('.rep-precio')?.value) || 0;
    total += c * p;
  });
  const crep = document.getElementById('costo_repuestos');
  if (crep) crep.value = total.toFixed(2);
  calcularTotalOT();
}
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
