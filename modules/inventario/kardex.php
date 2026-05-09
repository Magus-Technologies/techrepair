<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
requireLogin();
$db = getDB();

$pid     = (int)($_GET['producto_id'] ?? 0);
$desde   = $_GET['desde'] ?? date('Y-m-01');
$hasta   = $_GET['hasta'] ?? date('Y-m-d');

$where  = ['DATE(k.created_at) BETWEEN ? AND ?'];
$params = [$desde, $hasta];
if ($pid) { $where[] = 'k.producto_id=?'; $params[] = $pid; }

$movs = $db->prepare("
  SELECT k.*, p.nombre as prod_nombre, p.codigo as prod_codigo,
         CONCAT(u.nombre,' ',u.apellido) as usuario_nombre
  FROM kardex k
  JOIN productos p ON p.id=k.producto_id
  JOIN usuarios u ON u.id=k.usuario_id
  WHERE ".implode(' AND ',$where)."
  ORDER BY k.created_at DESC LIMIT 500");
$movs->execute($params);
$movs = $movs->fetchAll();

$productos = $db->query("SELECT id,nombre,codigo FROM productos WHERE activo=1 ORDER BY nombre")->fetchAll();

$pageTitle  = 'Kardex — '.APP_NAME;
$breadcrumb = [['label'=>'Inventario','url'=>BASE_URL.'modules/inventario/index.php'],['label'=>'Kardex','url'=>null]];
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="fw-bold mb-0">Kardex de inventario</h5>
</div>
<div class="tr-card mb-3">
  <div class="tr-card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-4">
        <select name="producto_id" class="form-select form-select-sm">
          <option value="">Todos los productos</option>
          <?php foreach($productos as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $pid==$p['id']?'selected':'' ?>><?= sanitize($p['codigo'].' — '.$p['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2"><input type="date" name="desde" class="form-control form-control-sm" value="<?= $desde ?>"/></div>
      <div class="col-md-2"><input type="date" name="hasta" class="form-control form-control-sm" value="<?= $hasta ?>"/></div>
      <div class="col-md-1"><button type="submit" class="btn btn-primary btn-sm w-100">Filtrar</button></div>
    </form>
  </div>
</div>
<div class="tr-card">
  <div class="tr-card-body p-0">
    <table class="tr-table">
      <thead>
        <tr><th>Fecha</th><th>Producto</th><th>Tipo</th><th>Cantidad</th><th>Stock antes</th><th>Stock después</th><th>Precio unit.</th><th>Motivo</th><th>Referencia</th><th>Usuario</th></tr>
      </thead>
      <tbody>
        <?php foreach($movs as $m): ?>
        <tr>
          <td class="small text-muted"><?= formatDateTime($m['created_at']) ?></td>
          <td>
            <div class="small fw-semibold"><?= sanitize($m['prod_nombre']) ?></div>
            <div style="font-size:10px" class="text-muted"><?= sanitize($m['prod_codigo']) ?></div>
          </td>
          <td>
            <?php $tc=['entrada'=>'success','salida'=>'danger','ajuste'=>'warning','devolucion'=>'info'];
            echo '<span class="badge bg-'.($tc[$m['tipo']]??'secondary').'">'.ucfirst($m['tipo']).'</span>'; ?>
          </td>
          <td class="fw-bold text-<?= $m['tipo']==='entrada'?'success':'danger' ?>"><?= $m['tipo']==='entrada'?'+':'-' ?><?= number_format($m['cantidad'],2) ?></td>
          <td class="text-muted small"><?= number_format($m['stock_antes'],2) ?></td>
          <td class="fw-semibold small"><?= number_format($m['stock_despues'],2) ?></td>
          <td class="small"><?= $m['precio_unit']>0?formatMoney($m['precio_unit']):'—' ?></td>
          <td class="small"><?= sanitize($m['motivo']??'—') ?></td>
          <td class="small text-muted"><?= sanitize($m['referencia']??'—') ?></td>
          <td class="small"><?= sanitize($m['usuario_nombre']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($movs)): ?>
        <tr><td colspan="10" class="text-center text-muted py-4">Sin movimientos en el período</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
