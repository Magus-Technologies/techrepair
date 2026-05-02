<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
requireLogin();
requireRole([ROL_ADMIN, ROL_VENDEDOR]);
$db = getDB();

$desde = $_GET['desde'] ?? date('Y-m-d');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$q     = trim($_GET['q'] ?? '');

$where  = ['DATE(v.created_at) BETWEEN ? AND ?'];
$params = [$desde, $hasta];
if ($q) { $where[] = '(v.codigo LIKE ? OR c.nombre LIKE ?)'; $like='%'.$q.'%'; $params=array_merge($params,[$like,$like]); }

$ventas = $db->prepare("
  SELECT v.*, c.nombre as cliente_nombre, CONCAT(u.nombre,' ',u.apellido) as vendedor
  FROM ventas v
  LEFT JOIN clientes c ON c.id=v.cliente_id
  JOIN usuarios u ON u.id=v.usuario_id
  WHERE ".implode(' AND ',$where)."
  ORDER BY v.created_at DESC LIMIT 300");
$ventas->execute($params);
$ventas = $ventas->fetchAll();

$totalDia = array_sum(array_map(fn($v)=>$v['estado']==='completada'?$v['total']:0, $ventas));

$pageTitle  = 'Historial de ventas — '.APP_NAME;
$breadcrumb = [['label'=>'Ventas','url'=>null]];
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="fw-bold mb-0">Historial de ventas</h5>
  <a href="<?= BASE_URL ?>modules/ventas/pos.php" class="btn btn-primary btn-sm">
    <i data-feather="shopping-cart" style="width:14px;height:14px"></i> Ir al POS
  </a>
</div>
<div class="tr-card mb-3">
  <div class="tr-card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-3"><input type="text" name="q" class="form-control form-control-sm" placeholder="Código o cliente..." value="<?= sanitize($q) ?>"/></div>
      <div class="col-md-2"><input type="date" name="desde" class="form-control form-control-sm" value="<?= $desde ?>"/></div>
      <div class="col-md-2"><input type="date" name="hasta" class="form-control form-control-sm" value="<?= $hasta ?>"/></div>
      <div class="col-md-1"><button type="submit" class="btn btn-primary btn-sm w-100">Filtrar</button></div>
      <div class="col-md-4 text-end">
        <span class="text-muted small">Total período: </span>
        <span class="fw-bold text-success"><?= formatMoney($totalDia) ?></span>
      </div>
    </form>
  </div>
</div>
<div class="tr-card">
  <div class="tr-card-body p-0" style="overflow:hidden"><div class="table-responsive-wrapper" style="overflow-x:auto;-webkit-overflow-scrolling:touch">
    <table class="tr-table">
      <thead><tr><th>Código</th><th>Cliente</th><th>Tipo doc.</th><th>Subtotal</th><th>IGV</th><th>Total</th><th>Método</th><th>Vendedor</th><th>Estado</th><th>Fecha</th><th></th></tr></thead>
      <tbody>
        <?php foreach($ventas as $v): ?>
        <tr>
          <td><span class="fw-semibold small text-primary"><?= sanitize($v['codigo']) ?></span></td>
          <td class="small"><?= sanitize($v['cliente_nombre'] ?? '— Consumidor final —') ?></td>
          <td><span class="badge bg-secondary"><?= ucfirst($v['tipo_doc']) ?></span></td>
          <td class="small"><?= formatMoney($v['subtotal']) ?></td>
          <td class="small text-muted"><?= formatMoney($v['igv']) ?></td>
          <td class="fw-bold"><?= formatMoney($v['total']) ?></td>
          <td class="small"><?= ucfirst($v['metodo_pago']) ?></td>
          <td class="small text-muted"><?= sanitize($v['vendedor']) ?></td>
          <td><span class="badge bg-<?= $v['estado']==='completada'?'success':($v['estado']==='anulada'?'danger':'warning') ?>"><?= ucfirst($v['estado']) ?></span></td>
          <td class="small text-muted"><?= formatDateTime($v['created_at']) ?></td>
              <td>
              <div class="btn-group btn-group-sm">
                <a href="<?= BASE_URL ?>modules/ventas/detalle.php?id=<?= $v['id'] ?>" class="btn btn-outline-primary" title="Ver detalle"><i data-feather="eye" style="width:13px;height:13px"></i></a>
                <a href="<?= BASE_URL ?>modules/ventas/ticket.php?id=<?= $v['id'] ?>" target="_blank" class="btn btn-outline-secondary" title="Imprimir comprobante"><i data-feather="printer" style="width:13px;height:13px"></i></a>
              </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($ventas)): ?><tr><td colspan="10" class="text-center text-muted py-4">Sin ventas en el período</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
