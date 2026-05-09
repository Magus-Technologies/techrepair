<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
requireLogin();
requireRole([ROL_ADMIN, ROL_TECNICO]);
$db   = getDB();
$user = currentUser();

// Procesar nueva compra
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'registrar_compra') {
    $items      = json_decode($_POST['items'] ?? '[]', true);
    $proveedor  = trim($_POST['proveedor']   ?? '');
    $nroDoc     = trim($_POST['nro_doc']     ?? '');
    $tipoDoc    = $_POST['tipo_doc']         ?? 'factura';
    $metPago    = $_POST['metodo_pago']      ?? 'efectivo';
    $notas      = trim($_POST['notas']       ?? '');

    if (!empty($items)) {
        $total = array_sum(array_map(fn($i) => (float)$i['cantidad'] * (float)$i['precio_unit'], $items));

        // Registrar en kardex + actualizar stock
        foreach ($items as $item) {
            $pid   = (int)$item['producto_id'];
            $cant  = (float)$item['cantidad'];
            $pu    = (float)$item['precio_unit'];

            $s = $db->prepare("SELECT stock_actual FROM productos WHERE id=?");
            $s->execute([$pid]);
            $antes   = (float)$s->fetchColumn();
            $despues = $antes + $cant;

            $db->prepare("UPDATE productos SET stock_actual=?, precio_costo=? WHERE id=?")->execute([$despues, $pu, $pid]);
            $db->prepare("INSERT INTO kardex (producto_id,tipo,cantidad,stock_antes,stock_despues,precio_unit,motivo,referencia,usuario_id) VALUES (?,?,?,?,?,?,?,?,?)")
               ->execute([$pid,'entrada',$cant,$antes,$despues,$pu,'Compra a proveedor',$nroDoc ?: 'COMPRA',$user['id']]);
        }

        // Registrar egreso en caja si hay caja abierta
        $caja = $db->prepare("SELECT id FROM cajas WHERE fecha=CURDATE() AND estado='abierta' ORDER BY id DESC LIMIT 1");
        $caja->execute();
        $cajaId = $caja->fetchColumn();
        if ($cajaId) {
            $concepto = "Compra $tipoDoc".($nroDoc?" #$nroDoc":"").($proveedor?" — $proveedor":"");
            $db->prepare("INSERT INTO movimientos_caja (caja_id,tipo,concepto,monto,referencia,usuario_id) VALUES (?,?,?,?,?,?)")
               ->execute([$cajaId,'egreso',$concepto,$total,$nroDoc,$user['id']]);
        }

        // Guardar compra en tabla
        $db->prepare("INSERT INTO compras (proveedor,tipo_doc,nro_doc,total,metodo_pago,notas,usuario_id) VALUES (?,?,?,?,?,?,?)")
           ->execute([$proveedor,$tipoDoc,$nroDoc,$total,$metPago,$notas,$user['id']]);
        $compraId = $db->lastInsertId();

        foreach ($items as $item) {
            $db->prepare("INSERT INTO compra_detalle (compra_id,producto_id,cantidad,precio_unit,subtotal) VALUES (?,?,?,?,?)")
               ->execute([$compraId,(int)$item['producto_id'],(float)$item['cantidad'],(float)$item['precio_unit'],(float)$item['cantidad']*(float)$item['precio_unit']]);
        }

        header('Content-Type: application/json');
        echo json_encode(['success'=>true,'compra_id'=>$compraId,'total'=>$total]);
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['error'=>'Sin ítems']);
    exit;
}

// API buscar productos
if (isset($_GET['api']) && $_GET['api']==='buscar') {
    header('Content-Type: application/json');
    $q = '%'.trim($_GET['q']??'').'%';
    $r = $db->prepare("SELECT id,codigo,nombre,precio_costo,stock_actual,unidad FROM productos WHERE activo=1 AND (nombre LIKE ? OR codigo LIKE ?) LIMIT 20");
    $r->execute([$q,$q]);
    echo json_encode($r->fetchAll());
    exit;
}

// Historial de compras
$compras = $db->query("
    SELECT c.*, CONCAT(u.nombre,' ',u.apellido) as usuario_nombre,
           (SELECT COUNT(*) FROM compra_detalle WHERE compra_id=c.id) as items
    FROM compras c JOIN usuarios u ON u.id=c.usuario_id
    ORDER BY c.created_at DESC LIMIT 100
")->fetchAll();

$productos = $db->query("SELECT id,codigo,nombre,precio_costo,stock_actual,unidad FROM productos WHERE activo=1 ORDER BY nombre")->fetchAll();

$pageTitle  = 'Compras — '.APP_NAME;
$breadcrumb = [['label'=>'Inventario','url'=>BASE_URL.'modules/inventario/index.php'],['label'=>'Compras','url'=>null]];
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="fw-bold mb-0">Registro de compras</h5>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-nueva-compra">
    <i data-feather="plus" style="width:14px;height:14px"></i> Nueva compra
  </button>
</div>

<!-- Historial -->
<div class="tr-card">
  <div class="tr-card-body p-0" style="overflow:hidden"><div class="table-responsive-wrapper" style="overflow-x:auto;-webkit-overflow-scrolling:touch">
    <table class="tr-table" id="tabla-compras">
      <thead>
        <tr><th>Fecha</th><th>Proveedor</th><th>Doc.</th><th>N° Doc</th><th>Ítems</th><th>Total</th><th>Pago</th><th>Usuario</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach($compras as $c): ?>
        <tr>
          <td class="small text-muted"><?= formatDateTime($c['created_at']) ?></td>
          <td class="fw-semibold"><?= sanitize($c['proveedor'] ?: '— Sin proveedor —') ?></td>
          <td><span class="badge bg-secondary"><?= ucfirst($c['tipo_doc']) ?></span></td>
          <td class="small"><code><?= sanitize($c['nro_doc'] ?: '—') ?></code></td>
          <td class="text-center"><?= $c['items'] ?></td>
          <td class="fw-bold text-danger"><?= formatMoney($c['total']) ?></td>
          <td class="small"><?= ucfirst($c['metodo_pago']) ?></td>
          <td class="small text-muted"><?= sanitize($c['usuario_nombre']) ?></td>
          <td>
            <button class="btn btn-sm btn-outline-primary" onclick="verDetalleCompra(<?= $c['id'] ?>)">
              <i data-feather="eye" style="width:13px;height:13px"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($compras)): ?><tr><td colspan="9" class="text-center text-muted py-4">Sin compras registradas</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal nueva compra -->
<div class="modal fade" id="modal-nueva-compra" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold">📦 Nueva compra / Entrada de stock</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <!-- Columna izquierda: Datos compra -->
          <div class="col-md-4">
            <h6 class="tr-section-title">Datos del proveedor</h6>
            <div class="mb-2"><label class="tr-form-label">Proveedor</label><input type="text" id="inp-proveedor" class="form-control form-control-sm" placeholder="Nombre del proveedor"/></div>
            <div class="row g-2">
              <div class="col-6">
                <label class="tr-form-label">Tipo doc.</label>
                <select id="inp-tipo-doc" class="form-select form-select-sm">
                  <option value="factura">Factura</option>
                  <option value="boleta">Boleta</option>
                  <option value="guia">Guía remisión</option>
                  <option value="sin_doc">Sin documento</option>
                </select>
              </div>
              <div class="col-6"><label class="tr-form-label">N° Documento</label><input type="text" id="inp-nro-doc" class="form-control form-control-sm" placeholder="F001-00123"/></div>
            </div>
            <div class="mb-2 mt-2">
              <label class="tr-form-label">Método de pago</label>
              <select id="inp-metpago" class="form-select form-select-sm">
                <option value="efectivo">Efectivo</option>
                <option value="transferencia">Transferencia</option>
                <option value="tarjeta">Tarjeta</option>
                <option value="credito">Crédito (pendiente)</option>
              </select>
            </div>
            <div class="mb-2"><label class="tr-form-label">Notas</label><textarea id="inp-notas" class="form-control form-control-sm" rows="2"></textarea></div>

            <!-- Resumen total -->
            <div class="p-3 bg-light rounded text-center mt-3">
              <div class="text-muted small">Total de compra</div>
              <div class="fw-bold fs-4 text-danger" id="total-compra">S/ 0.00</div>
            </div>
          </div>

          <!-- Columna derecha: Productos -->
          <div class="col-md-8">
            <h6 class="tr-section-title">Productos comprados</h6>
            <!-- Buscador -->
            <div class="input-group mb-2">
              <span class="input-group-text"><i data-feather="search" style="width:14px;height:14px"></i></span>
              <input type="text" id="buscar-prod-compra" class="form-control form-control-sm" placeholder="Buscar producto por nombre o código..." autocomplete="off"/>
            </div>
            <div id="res-busq-compra" class="list-group mb-2" style="max-height:150px;overflow-y:auto"></div>

            <!-- Tabla de ítems -->
            <table class="tr-table" id="tabla-items-compra">
              <thead><tr><th>Producto</th><th style="width:100px">Cantidad</th><th style="width:110px">P. Costo (S/)</th><th style="width:100px">Subtotal</th><th style="width:40px"></th></tr></thead>
              <tbody id="body-items-compra">
                <tr id="fila-vacia-compra"><td colspan="5" class="text-center text-muted py-3 small">Busca y agrega productos</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="confirmarCompra()">
          <i data-feather="save" style="width:14px;height:14px"></i> Registrar compra
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal detalle compra -->
<div class="modal fade" id="modal-detalle-compra" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold" id="titulo-detalle-compra">Detalle de compra</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="body-detalle-compra">
        <div class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></div>
      </div>
    </div>
  </div>
</div>

<?php
$pageScripts = <<<'JS'
<script>
let itemsCompra = [];

// ── Buscador productos ──────────────────────────────────
let tBusq;
document.getElementById('buscar-prod-compra').addEventListener('input', function(){
  clearTimeout(tBusq);
  const q = this.value.trim();
  if (q.length < 2) { document.getElementById('res-busq-compra').innerHTML=''; return; }
  tBusq = setTimeout(() => {
    fetch('index.php?api=buscar&q='+encodeURIComponent(q))
      .then(r=>r.json()).then(data=>{
        const div = document.getElementById('res-busq-compra');
        if(!data.length){div.innerHTML='<a class="list-group-item small text-muted">Sin resultados</a>';return;}
        div.innerHTML=data.map(p=>`
          <button type="button" class="list-group-item list-group-item-action small py-1 d-flex justify-content-between"
                  onclick="agregarItemCompra(${JSON.stringify(p).replace(/"/g,'&quot;')})">
            <span><strong>${p.nombre}</strong> <span class="text-muted">${p.codigo}</span></span>
            <span class="text-muted">Stock: ${p.stock_actual} | Costo: S/ ${parseFloat(p.precio_costo).toFixed(2)}</span>
          </button>`).join('');
      });
  },300);
});

function agregarItemCompra(p) {
  const idx = itemsCompra.findIndex(i=>i.producto_id==p.id);
  if(idx>=0){itemsCompra[idx].cantidad++;renderItemsCompra();return;}
  itemsCompra.push({producto_id:p.id, nombre:p.nombre, codigo:p.codigo, cantidad:1, precio_unit:parseFloat(p.precio_costo)||0});
  renderItemsCompra();
  document.getElementById('buscar-prod-compra').value='';
  document.getElementById('res-busq-compra').innerHTML='';
}

function renderItemsCompra(){
  const tbody = document.getElementById('body-items-compra');
  if(!itemsCompra.length){
    tbody.innerHTML='<tr id="fila-vacia-compra"><td colspan="5" class="text-center text-muted py-3 small">Busca y agrega productos</td></tr>';
    document.getElementById('total-compra').textContent='S/ 0.00';
    return;
  }
  tbody.innerHTML = itemsCompra.map((it,i)=>`
    <tr>
      <td class="small"><strong>${it.nombre}</strong><br><span class="text-muted">${it.codigo}</span></td>
      <td><input type="number" class="form-control form-control-sm" value="${it.cantidad}" min="0.01" step="0.01"
                 onchange="itemsCompra[${i}].cantidad=parseFloat(this.value)||1;renderItemsCompra()"/></td>
      <td><input type="number" class="form-control form-control-sm" value="${it.precio_unit.toFixed(2)}" min="0" step="0.01"
                 onchange="itemsCompra[${i}].precio_unit=parseFloat(this.value)||0;renderItemsCompra()"/></td>
      <td class="fw-semibold small">S/ ${(it.cantidad*it.precio_unit).toFixed(2)}</td>
      <td><button class="btn btn-sm btn-outline-danger py-0" onclick="itemsCompra.splice(${i},1);renderItemsCompra()">✕</button></td>
    </tr>`).join('');
  const total = itemsCompra.reduce((s,i)=>s+(i.cantidad*i.precio_unit),0);
  document.getElementById('total-compra').textContent='S/ '+total.toFixed(2);
}

async function confirmarCompra(){
  if(!itemsCompra.length){alert('Agrega al menos un producto.');return;}
  const fd = new FormData();
  fd.append('action','registrar_compra');
  fd.append('items',JSON.stringify(itemsCompra));
  fd.append('proveedor', document.getElementById('inp-proveedor').value);
  fd.append('tipo_doc',  document.getElementById('inp-tipo-doc').value);
  fd.append('nro_doc',   document.getElementById('inp-nro-doc').value);
  fd.append('metodo_pago',document.getElementById('inp-metpago').value);
  fd.append('notas',     document.getElementById('inp-notas').value);
  const r = await fetch('index.php',{method:'POST',body:fd});
  const d = await r.json();
  if(d.success){
    bootstrap.Modal.getInstance(document.getElementById('modal-nueva-compra')).hide();
    alert('✅ Compra registrada. Total: S/ '+parseFloat(d.total).toFixed(2));
    location.reload();
  } else {
    alert('Error: '+(d.error||'Intenta de nuevo'));
  }
}

async function verDetalleCompra(id){
  document.getElementById('body-detalle-compra').innerHTML='<div class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></div>';
  new bootstrap.Modal(document.getElementById('modal-detalle-compra')).show();
  const r = await fetch('detalle_ajax.php?id='+id);
  const html = await r.text();
  document.getElementById('body-detalle-compra').innerHTML=html;
  feather.replace();
}
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
