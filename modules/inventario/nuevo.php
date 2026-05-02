<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
requireLogin();
requireRole([ROL_ADMIN, ROL_TECNICO]);
$db   = getDB();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Generar código si no se proporciona
    $codigo = trim($_POST['codigo'] ?? '');
    if (!$codigo) {
        $n = $db->query("SELECT COUNT(*)+1 FROM productos")->fetchColumn();
        $codigo = 'PRD-' . str_pad($n, 5, '0', STR_PAD_LEFT);
    }
    $stockInicial = (float)($_POST['stock_inicial'] ?? 0);

    $db->prepare("INSERT INTO productos (codigo,nombre,descripcion,categoria_id,marca,modelo,serial,ubicacion,precio_costo,precio_venta,stock_actual,stock_minimo,stock_maximo,unidad) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
       ->execute([
           $codigo, trim($_POST['nombre']), trim($_POST['descripcion']??''),
           (int)$_POST['categoria_id'], trim($_POST['marca']??''), trim($_POST['modelo']??''),
           trim($_POST['serial']??''), trim($_POST['ubicacion']??''),
           (float)$_POST['precio_costo'], (float)$_POST['precio_venta'],
           $stockInicial, (float)$_POST['stock_minimo'],
           (float)($_POST['stock_maximo']??100), trim($_POST['unidad']??'unidad'),
       ]);
    $prodId = $db->lastInsertId();

    // Kardex entrada inicial
    if ($stockInicial > 0) {
        $db->prepare("INSERT INTO kardex (producto_id,tipo,cantidad,stock_antes,stock_despues,precio_unit,motivo,referencia,usuario_id) VALUES (?,?,?,?,?,?,?,?,?)")
           ->execute([$prodId,'entrada',$stockInicial,0,$stockInicial,(float)$_POST['precio_costo'],'Stock inicial','INICIO',$user['id']]);
    }

    setFlash('success','Producto creado con código '.$codigo);
    redirect(BASE_URL.'modules/inventario/index.php');
}

$categorias = $db->query("SELECT * FROM categorias WHERE activo=1 ORDER BY tipo,nombre")->fetchAll();

$pageTitle  = 'Nuevo producto — '.APP_NAME;
$breadcrumb = [['label'=>'Inventario','url'=>BASE_URL.'modules/inventario/index.php'],['label'=>'Nuevo','url'=>null]];
require_once __DIR__ . '/../../includes/header.php';
?>
<h5 class="fw-bold mb-4">Nuevo producto / repuesto</h5>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="tr-card">
      <div class="tr-card-body">
        <form method="POST">
          <div class="row g-3">
            <div class="col-md-4"><label class="tr-form-label">Código (autogenera si vacío)</label><input type="text" name="codigo" class="form-control" placeholder="PRD-00001"/></div>
            <div class="col-md-8"><label class="tr-form-label">Nombre *</label><input type="text" name="nombre" class="form-control" required autofocus/></div>
            <div class="col-md-6">
              <label class="tr-form-label">Categoría *</label>
              <select name="categoria_id" class="form-select" required>
                <option value="">— Seleccionar —</option>
                <?php $lastTipo=''; foreach($categorias as $c): if($c['tipo']!==$lastTipo){ echo '<option disabled>— '.strtoupper($c['tipo']).' —</option>'; $lastTipo=$c['tipo']; } ?>
                <option value="<?= $c['id'] ?>"><?= sanitize($c['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3"><label class="tr-form-label">Marca</label><input type="text" name="marca" class="form-control"/></div>
            <div class="col-md-3"><label class="tr-form-label">Modelo</label><input type="text" name="modelo" class="form-control"/></div>
            <div class="col-md-4"><label class="tr-form-label">Serial / Número de serie</label><input type="text" name="serial" class="form-control" placeholder="Importante para hardware"/></div>
            <div class="col-md-4"><label class="tr-form-label">Ubicación en almacén</label><input type="text" name="ubicacion" class="form-control" placeholder="Estante A / Fila 2"/></div>
            <div class="col-md-4"><label class="tr-form-label">Unidad</label>
              <select name="unidad" class="form-select">
                <option value="unidad">Unidad</option>
                <option value="par">Par</option>
                <option value="kit">Kit</option>
                <option value="metro">Metro</option>
                <option value="gramo">Gramo</option>
              </select>
            </div>
            <div class="col-12"><label class="tr-form-label">Descripción</label><textarea name="descripcion" class="form-control" rows="2"></textarea></div>
            <div class="col-md-4"><label class="tr-form-label">Precio costo (S/) *</label><input type="number" name="precio_costo" class="form-control currency-input" step="0.01" required value="0"/></div>
            <div class="col-md-4"><label class="tr-form-label">Precio venta (S/) *</label><input type="number" name="precio_venta" class="form-control currency-input" step="0.01" required value="0"/></div>
            <div class="col-md-4">
              <label class="tr-form-label">Margen</label>
              <div class="form-control bg-light" id="txt-margen">0%</div>
            </div>
            <div class="col-md-4"><label class="tr-form-label">Stock inicial</label><input type="number" name="stock_inicial" class="form-control" step="0.01" value="0" min="0"/></div>
            <div class="col-md-4"><label class="tr-form-label">Stock mínimo *</label><input type="number" name="stock_minimo" class="form-control" step="0.01" value="1" min="0" required/></div>
            <div class="col-md-4"><label class="tr-form-label">Stock máximo</label><input type="number" name="stock_maximo" class="form-control" step="0.01" value="100"/></div>
            <div class="col-12 d-flex gap-2">
              <button type="submit" class="btn btn-primary">Guardar producto</button>
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
  const costo = parseFloat(document.querySelector('[name=precio_costo]').value)||0;
  const venta = parseFloat(document.querySelector('[name=precio_venta]').value)||0;
  const m = costo>0 ? ((venta-costo)/costo*100).toFixed(1) : 0;
  const color = m>=20?'text-success':(m>=0?'text-warning':'text-danger');
  document.getElementById('txt-margen').innerHTML = `<span class="${color} fw-bold">${m}%</span>`;
}
document.querySelector('[name=precio_costo]').addEventListener('input',calcMargen);
document.querySelector('[name=precio_venta]').addEventListener('input',calcMargen);
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php'; ?>
