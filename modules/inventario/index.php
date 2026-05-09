<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
requireLogin();

$db = getDB();
$f_cat    = $_GET['categoria'] ?? '';
$f_buscar = trim($_GET['q'] ?? '');
$f_stock  = $_GET['stock'] ?? '';

$where = ['p.activo=1'];
$params = [];
if ($f_cat)   { $where[] = 'p.categoria_id=?'; $params[] = $f_cat; }
if ($f_buscar){ $where[] = '(p.nombre LIKE ? OR p.codigo LIKE ? OR p.serial LIKE ?)'; $like='%'.$f_buscar.'%'; $params=array_merge($params,[$like,$like,$like]); }
if ($f_stock === 'bajo') { $where[] = 'p.stock_actual <= p.stock_minimo'; }

$sql = "SELECT p.*, c.nombre as cat_nombre, c.tipo as cat_tipo
        FROM productos p JOIN categorias c ON c.id=p.categoria_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY p.stock_actual<=p.stock_minimo DESC, p.nombre
        LIMIT 300";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

$categorias = $db->query("SELECT * FROM categorias WHERE activo=1 ORDER BY tipo,nombre")->fetchAll();

$pageTitle  = 'Inventario — ' . APP_NAME;
$breadcrumb = [['label'=>'Inventario','url'=>null]];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="fw-bold mb-0">Inventario de productos</h5>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>modules/inventario/kardex.php" class="btn btn-outline-secondary btn-sm">
      <i data-feather="bar-chart-2" style="width:14px;height:14px"></i> Kardex
    </a>
    <a href="<?= BASE_URL ?>modules/inventario/nuevo.php" class="btn btn-primary btn-sm">
      <i data-feather="plus" style="width:14px;height:14px"></i> Nuevo producto
    </a>
  </div>
</div>

<!-- Filtros -->
<div class="tr-card mb-3">
  <div class="tr-card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-4">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar por nombre, código, serial..." value="<?= sanitize($f_buscar) ?>"/>
      </div>
      <div class="col-md-3">
        <select name="categoria" class="form-select form-select-sm">
          <option value="">Todas las categorías</option>
          <?php foreach ($categorias as $cat): ?>
          <option value="<?= $cat['id'] ?>" <?= $f_cat==$cat['id']?'selected':'' ?>><?= sanitize($cat['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="stock" class="form-select form-select-sm">
          <option value="">Todo el stock</option>
          <option value="bajo" <?= $f_stock==='bajo'?'selected':'' ?>>⚠️ Stock mínimo</option>
        </select>
      </div>
      <div class="col-md-1"><button type="submit" class="btn btn-primary btn-sm w-100">Filtrar</button></div>
    </form>
  </div>
</div>

<!-- Tabla -->
<div class="tr-card">
  <div class="tr-card-body p-0" style="overflow:hidden"><div class="table-responsive-wrapper" style="overflow-x:auto;-webkit-overflow-scrolling:touch">
    <table class="tr-table">
      <thead>
        <tr>
          <th>Código</th><th>Producto</th><th>Categoría</th>
          <th>Serial</th><th>Ubicación</th>
          <th>Costo</th><th>Precio venta</th>
          <th>Stock</th><th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($productos as $p): 
          $stockBajo = $p['stock_actual'] <= $p['stock_minimo'];
        ?>
        <tr class="<?= $stockBajo ? 'table-warning' : '' ?>">
          <td><code><?= sanitize($p['codigo']) ?></code></td>
          <td>
            <div class="fw-semibold"><?= sanitize($p['nombre']) ?></div>
            <?php if ($p['marca']): ?><div class="text-muted small"><?= sanitize($p['marca'].' '.($p['modelo']??'')) ?></div><?php endif; ?>
          </td>
          <td><span class="badge bg-secondary"><?= sanitize($p['cat_nombre']) ?></span></td>
          <td class="small"><?= $p['serial'] ? '<code>'.sanitize($p['serial']).'</code>' : '—' ?></td>
          <td class="small text-muted"><?= sanitize($p['ubicacion'] ?? '—') ?></td>
          <td><?= formatMoney($p['precio_costo']) ?></td>
          <td class="fw-semibold"><?= formatMoney($p['precio_venta']) ?></td>
          <td>
            <span class="fw-bold <?= $stockBajo ? 'text-danger' : 'text-success' ?>">
              <?= number_format($p['stock_actual'],0) ?>
            </span>
            <span class="text-muted small"> / min <?= number_format($p['stock_minimo'],0) ?></span>
            <?php if ($stockBajo): ?>
            <span class="badge bg-danger ms-1" style="font-size:10px">BAJO</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="btn-group btn-group-sm">
              <a href="<?= BASE_URL ?>modules/inventario/editar.php?id=<?= $p['id'] ?>"
                 class="btn btn-outline-secondary" title="Editar">
                <i data-feather="edit-2" style="width:13px;height:13px"></i>
              </a>
              <a href="<?= BASE_URL ?>modules/inventario/movimiento.php?id=<?= $p['id'] ?>"
                 class="btn btn-outline-primary" title="Entrada/Salida">
                <i data-feather="refresh-cw" style="width:13px;height:13px"></i>
              </a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
