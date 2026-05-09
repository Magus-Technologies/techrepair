<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
requireLogin();
requireRole([ROL_ADMIN, ROL_TECNICO]);
$db  = getDB();
$id  = (int)($_GET['id'] ?? 0);

$prod = $db->prepare("SELECT * FROM productos WHERE id=?");
$prod->execute([$id]);
$prod = $prod->fetch();
if (!$prod) { setFlash('danger','Producto no encontrado'); redirect(BASE_URL.'modules/inventario/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->prepare("UPDATE productos SET nombre=?,descripcion=?,categoria_id=?,marca=?,modelo=?,serial=?,ubicacion=?,precio_costo=?,precio_venta=?,stock_minimo=?,stock_maximo=?,unidad=?,activo=? WHERE id=?")
       ->execute([
           trim($_POST['nombre']),
           trim($_POST['descripcion'] ?? ''),
           (int)$_POST['categoria_id'],
           trim($_POST['marca']     ?? ''),
           trim($_POST['modelo']    ?? ''),
           trim($_POST['serial']    ?? ''),
           trim($_POST['ubicacion'] ?? ''),
           (float)$_POST['precio_costo'],
           (float)$_POST['precio_venta'],
           (float)$_POST['stock_minimo'],
           (float)$_POST['stock_maximo'],
           trim($_POST['unidad']    ?? 'unidad'),
           isset($_POST['activo']) ? 1 : 0,
           $id,
       ]);
    setFlash('success','Producto actualizado correctamente.');
    redirect(BASE_URL.'modules/inventario/index.php');
}

$categorias = $db->query("SELECT * FROM categorias WHERE activo=1 ORDER BY tipo,nombre")->fetchAll();

$pageTitle  = 'Editar producto — '.APP_NAME;
$breadcrumb = [
    ['label'=>'Inventario','url'=>BASE_URL.'modules/inventario/index.php'],
    ['label'=>sanitize($prod['nombre']),'url'=>null],
];
require_once __DIR__ . '/../../includes/header.php';
?>
<h5 class="fw-bold mb-4">Editar producto</h5>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="tr-card">
      <div class="tr-card-body">
        <form method="POST">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="tr-form-label">Código</label>
              <input type="text" class="form-control bg-light" value="<?= sanitize($prod['codigo']) ?>" readonly/>
            </div>
            <div class="col-md-8">
              <label class="tr-form-label">Nombre *</label>
              <input type="text" name="nombre" class="form-control" value="<?= sanitize($prod['nombre']) ?>" required autofocus/>
            </div>
            <div class="col-md-6">
              <label class="tr-form-label">Categoría *</label>
              <select name="categoria_id" class="form-select" required>
                <?php $lt=''; foreach($categorias as $c): if($c['tipo']!==$lt){echo '<option disabled>— '.strtoupper($c['tipo']).' —</option>'; $lt=$c['tipo'];} ?>
                <option value="<?= $c['id'] ?>" <?= $prod['categoria_id']==$c['id']?'selected':'' ?>><?= sanitize($c['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="tr-form-label">Marca</label>
              <input type="text" name="marca" class="form-control" value="<?= sanitize($prod['marca'] ?? '') ?>"/>
            </div>
            <div class="col-md-3">
              <label class="tr-form-label">Modelo</label>
              <input type="text" name="modelo" class="form-control" value="<?= sanitize($prod['modelo'] ?? '') ?>"/>
            </div>
            <div class="col-md-4">
              <label class="tr-form-label">Serial</label>
              <input type="text" name="serial" class="form-control" value="<?= sanitize($prod['serial'] ?? '') ?>"/>
            </div>
            <div class="col-md-4">
              <label class="tr-form-label">Ubicación en almacén</label>
              <input type="text" name="ubicacion" class="form-control" value="<?= sanitize($prod['ubicacion'] ?? '') ?>"/>
            </div>
            <div class="col-md-4">
              <label class="tr-form-label">Unidad</label>
              <select name="unidad" class="form-select">
                <?php foreach(['unidad','par','kit','metro','gramo'] as $u): ?>
                <option value="<?= $u ?>" <?= $prod['unidad']===$u?'selected':'' ?>><?= ucfirst($u) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="tr-form-label">Descripción</label>
              <textarea name="descripcion" class="form-control" rows="2"><?= sanitize($prod['descripcion'] ?? '') ?></textarea>
            </div>
            <div class="col-md-4">
              <label class="tr-form-label">Precio costo (S/) *</label>
              <input type="number" name="precio_costo" class="form-control currency-input" step="0.01" required value="<?= $prod['precio_costo'] ?>"/>
            </div>
            <div class="col-md-4">
              <label class="tr-form-label">Precio venta (S/) *</label>
              <input type="number" name="precio_venta" class="form-control currency-input" step="0.01" required value="<?= $prod['precio_venta'] ?>"/>
            </div>
            <div class="col-md-4">
              <label class="tr-form-label">Margen</label>
              <div class="form-control bg-light" id="txt-margen">—</div>
            </div>
            <div class="col-md-3">
              <label class="tr-form-label">Stock actual</label>
              <input type="text" class="form-control bg-light fw-bold" value="<?= number_format($prod['stock_actual'],2) ?>" readonly/>
              <div class="text-muted" style="font-size:11px">Usar entrada/salida para cambiar</div>
            </div>
            <div class="col-md-3">
              <label class="tr-form-label">Stock mínimo *</label>
              <input type="number" name="stock_minimo" class="form-control" step="0.01" value="<?= $prod['stock_minimo'] ?>" required/>
            </div>
            <div class="col-md-3">
              <label class="tr-form-label">Stock máximo</label>
              <input type="number" name="stock_maximo" class="form-control" step="0.01" value="<?= $prod['stock_maximo'] ?>"/>
            </div>
            <div class="col-md-3 d-flex align-items-end pb-1">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="activo" id="chk-activo" <?= $prod['activo']?'checked':'' ?>>
                <label class="form-check-label" for="chk-activo">Producto activo</label>
              </div>
            </div>
            <div class="col-12 d-flex gap-2">
              <button type="submit" class="btn btn-primary">Guardar cambios</button>
              <a href="<?= BASE_URL ?>modules/inventario/movimiento.php?id=<?= $id ?>" class="btn btn-outline-primary">
                <i data-feather="refresh-cw" style="width:14px;height:14px"></i> Entrada / Salida
              </a>
              <a href="<?= BASE_URL ?>modules/inventario/index.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php
$pageScripts = <<<'JS'
<script>
function calcMargen() {
  const c = parseFloat(document.querySelector('[name=precio_costo]').value)||0;
  const v = parseFloat(document.querySelector('[name=precio_venta]').value)||0;
  const m = c>0 ? ((v-c)/c*100).toFixed(1) : 0;
  const col = m>=20?'text-success':(m>=0?'text-warning':'text-danger');
  document.getElementById('txt-margen').innerHTML = `<span class="${col} fw-bold">${m}%</span>`;
}
document.querySelector('[name=precio_costo]').addEventListener('input',calcMargen);
document.querySelector('[name=precio_venta]').addEventListener('input',calcMargen);
calcMargen();
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
