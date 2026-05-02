<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
if (!isLoggedIn()) { echo '<p class="text-danger">No autorizado</p>'; exit; }
$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$c = $db->prepare("SELECT co.*, CONCAT(u.nombre,' ',u.apellido) as usuario_nombre FROM compras co JOIN usuarios u ON u.id=co.usuario_id WHERE co.id=?");
$c->execute([$id]);
$c = $c->fetch();
if (!$c) { echo '<p class="text-danger">Compra no encontrada</p>'; exit; }

$items = $db->prepare("SELECT cd.*,p.nombre as prod_nombre,p.codigo FROM compra_detalle cd JOIN productos p ON p.id=cd.producto_id WHERE cd.compra_id=?");
$items->execute([$id]);
$items = $items->fetchAll();
?>
<div class="row g-2 mb-3">
  <div class="col-6"><small class="text-muted">Proveedor</small><div class="fw-semibold"><?= sanitize($c['proveedor']?:'—') ?></div></div>
  <div class="col-3"><small class="text-muted">Doc.</small><div><?= ucfirst($c['tipo_doc']) ?> <?= sanitize($c['nro_doc']?:'') ?></div></div>
  <div class="col-3"><small class="text-muted">Fecha</small><div><?= formatDateTime($c['created_at']) ?></div></div>
  <div class="col-6"><small class="text-muted">Pago</small><div><?= ucfirst($c['metodo_pago']) ?></div></div>
  <div class="col-6"><small class="text-muted">Registrado por</small><div><?= sanitize($c['usuario_nombre']) ?></div></div>
</div>
<table class="table table-sm table-bordered">
  <thead class="table-light"><tr><th>Código</th><th>Producto</th><th>Cantidad</th><th>P. Unitario</th><th>Subtotal</th></tr></thead>
  <tbody>
    <?php foreach($items as $it): ?>
    <tr>
      <td><code class="small"><?= sanitize($it['codigo']) ?></code></td>
      <td><?= sanitize($it['prod_nombre']) ?></td>
      <td><?= number_format($it['cantidad'],2) ?></td>
      <td><?= formatMoney($it['precio_unit']) ?></td>
      <td class="fw-semibold"><?= formatMoney($it['subtotal']) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr class="table-warning"><td colspan="4" class="fw-bold text-end">TOTAL:</td><td class="fw-bold"><?= formatMoney($c['total']) ?></td></tr>
  </tfoot>
</table>
<?php if($c['notas']): ?><div class="bg-light rounded p-2 small"><strong>Notas:</strong> <?= sanitize($c['notas']) ?></div><?php endif; ?>
