<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
requireLogin();
requireRole([ROL_ADMIN, ROL_TECNICO]);
$db   = getDB();
$id   = (int)($_GET['id'] ?? 0);
$user = currentUser();

$prod = $db->prepare("SELECT p.*, c.nombre as cat_nombre FROM productos p JOIN categorias c ON c.id=p.categoria_id WHERE p.id=?");
$prod->execute([$id]);
$prod = $prod->fetch();
if (!$prod) { setFlash('danger','Producto no encontrado'); redirect(BASE_URL.'modules/inventario/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo     = $_POST['tipo'];
    $cantidad = (float)$_POST['cantidad'];
    $precioU  = (float)($_POST['precio_unit'] ?? 0);
    $motivo   = trim($_POST['motivo'] ?? '');
    $ref      = trim($_POST['referencia'] ?? '');

    if ($cantidad <= 0) { setFlash('danger','La cantidad debe ser mayor a 0.'); redirect(BASE_URL.'modules/inventario/movimiento.php?id='.$id); }

    $antes   = (float)$prod['stock_actual'];
    $despues = $tipo === 'entrada' ? $antes + $cantidad : $antes - $cantidad;

    if ($despues < 0) { setFlash('danger','No hay suficiente stock para esta salida.'); redirect(BASE_URL.'modules/inventario/movimiento.php?id='.$id); }

    $db->prepare("UPDATE productos SET stock_actual=? WHERE id=?")->execute([$despues, $id]);
    $db->prepare("INSERT INTO kardex (producto_id,tipo,cantidad,stock_antes,stock_despues,precio_unit,motivo,referencia,usuario_id) VALUES (?,?,?,?,?,?,?,?,?)")
       ->execute([$id,$tipo,$cantidad,$antes,$despues,$precioU,$motivo,$ref,$user['id']]);

    setFlash('success', ucfirst($tipo).' registrada. Nuevo stock: '.$despues.' '.$prod['unidad']);
    redirect(BASE_URL.'modules/inventario/movimiento.php?id='.$id);
}

// Historial reciente del producto
$historial = $db->prepare("SELECT k.*, CONCAT(u.nombre,' ',u.apellido) as usr FROM kardex k JOIN usuarios u ON u.id=k.usuario_id WHERE k.producto_id=? ORDER BY k.created_at DESC LIMIT 15");
$historial->execute([$id]);
$historial = $historial->fetchAll();

$pageTitle  = 'Entrada/Salida — '.APP_NAME;
$breadcrumb = [
    ['label'=>'Inventario','url'=>BASE_URL.'modules/inventario/index.php'],
    ['label'=>sanitize($prod['nombre']),'url'=>BASE_URL.'modules/inventario/editar.php?id='.$id],
    ['label'=>'Movimiento','url'=>null],
];
require_once __DIR__ . '/../../includes/header.php';
?>
<h5 class="fw-bold mb-1">Entrada / Salida de stock</h5>
<p class="text-muted mb-4"><?= sanitize($prod['nombre']) ?> — <code><?= sanitize($prod['codigo']) ?></code></p>

<div class="row g-3">
  <div class="col-lg-5">

    <!-- Stock actual -->
    <div class="tr-card mb-3">
      <div class="tr-card-body">
        <div class="row g-2 text-center">
          <div class="col-4">
            <div class="fw-bold fs-3 <?= $prod['stock_actual'] <= $prod['stock_minimo'] ? 'text-danger' : 'text-success' ?>"><?= number_format($prod['stock_actual'],2) ?></div>
            <div class="text-muted small">Stock actual</div>
          </div>
          <div class="col-4">
            <div class="fw-bold fs-5 text-warning"><?= number_format($prod['stock_minimo'],2) ?></div>
            <div class="text-muted small">Mínimo</div>
          </div>
          <div class="col-4">
            <div class="fw-bold fs-5"><?= formatMoney($prod['precio_venta']) ?></div>
            <div class="text-muted small">P. Venta</div>
          </div>
        </div>
        <?php if($prod['stock_actual'] <= $prod['stock_minimo']): ?>
        <div class="alert alert-danger mt-2 mb-0 py-1 small text-center">⚠️ Stock en mínimo o por debajo</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Formulario -->
    <div class="tr-card">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">REGISTRAR MOVIMIENTO</h6></div>
      <div class="tr-card-body">
        <form method="POST">
          <div class="mb-3">
            <label class="tr-form-label">Tipo de movimiento *</label>
            <div class="d-flex gap-2">
              <div><input type="radio" class="btn-check" name="tipo" id="t_ent" value="entrada" checked><label class="btn btn-outline-success" for="t_ent">📦 Entrada</label></div>
              <div><input type="radio" class="btn-check" name="tipo" id="t_sal" value="salida"><label class="btn btn-outline-danger" for="t_sal">📤 Salida</label></div>
              <div><input type="radio" class="btn-check" name="tipo" id="t_aj" value="ajuste"><label class="btn btn-outline-warning" for="t_aj">⚖️ Ajuste</label></div>
            </div>
          </div>
          <div class="mb-2">
            <label class="tr-form-label">Cantidad *</label>
            <input type="number" name="cantidad" class="form-control" step="0.01" min="0.01" required autofocus placeholder="0.00"/>
          </div>
          <div class="mb-2">
            <label class="tr-form-label">Precio unitario (S/) <span class="text-muted small">(para kardex de costo)</span></label>
            <input type="number" name="precio_unit" class="form-control currency-input" step="0.01" value="<?= $prod['precio_costo'] ?>"/>
          </div>
          <div class="mb-2">
            <label class="tr-form-label">Motivo *</label>
            <select name="motivo" class="form-select">
              <option value="Compra de stock">Compra de stock</option>
              <option value="Devolución de cliente">Devolución de cliente</option>
              <option value="Ajuste de inventario">Ajuste de inventario</option>
              <option value="Uso en reparación">Uso en reparación</option>
              <option value="Venta directa">Venta directa</option>
              <option value="Pérdida / Merma">Pérdida / Merma</option>
              <option value="Otro">Otro</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="tr-form-label">Referencia <span class="text-muted small">(OT, factura proveedor...)</span></label>
            <input type="text" name="referencia" class="form-control form-control-sm" placeholder="OT-2024-0001 / FAC-001"/>
          </div>
          <button type="submit" class="btn btn-primary w-100">Registrar movimiento</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="tr-card">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">HISTORIAL RECIENTE</h6></div>
      <div class="tr-card-body p-0">
        <table class="tr-table">
          <thead><tr><th>Tipo</th><th>Cant.</th><th>Antes</th><th>Después</th><th>Motivo</th><th>Usuario</th><th>Fecha</th></tr></thead>
          <tbody>
            <?php foreach($historial as $h):
              $tc=['entrada'=>'success','salida'=>'danger','ajuste'=>'warning','devolucion'=>'info'];
            ?>
            <tr>
              <td><span class="badge bg-<?= $tc[$h['tipo']] ?? 'secondary' ?>"><?= ucfirst($h['tipo']) ?></span></td>
              <td class="fw-bold <?= $h['tipo']==='entrada'?'text-success':'text-danger' ?>"><?= $h['tipo']==='entrada'?'+':'-' ?><?= number_format($h['cantidad'],2) ?></td>
              <td class="text-muted small"><?= number_format($h['stock_antes'],2) ?></td>
              <td class="fw-semibold small"><?= number_format($h['stock_despues'],2) ?></td>
              <td class="small"><?= sanitize($h['motivo'] ?? '—') ?></td>
              <td class="small text-muted"><?= sanitize($h['usr']) ?></td>
              <td class="small text-muted"><?= formatDate($h['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($historial)): ?><tr><td colspan="7" class="text-center text-muted py-3">Sin movimientos</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
