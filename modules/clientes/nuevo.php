<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
requireLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = generarCodigoCliente($db);
    $db->prepare("INSERT INTO clientes (codigo,tipo,nombre,ruc_dni,email,telefono,whatsapp,direccion,distrito,segmento,notas) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
       ->execute([
           $codigo,
           $_POST['tipo'] ?? 'persona',
           trim($_POST['nombre']),
           trim($_POST['ruc_dni']    ?? ''),
           trim($_POST['email']      ?? ''),
           trim($_POST['telefono']   ?? ''),
           trim($_POST['whatsapp']   ?? ''),
           trim($_POST['direccion']  ?? ''),
           trim($_POST['distrito']   ?? ''),
           $_POST['segmento']        ?? 'nuevo',
           trim($_POST['notas']      ?? ''),
       ]);
    setFlash('success', 'Cliente registrado con código '.$codigo);
    redirect(BASE_URL . 'modules/clientes/index.php');
}

$pageTitle  = 'Nuevo cliente — '.APP_NAME;
$breadcrumb = [['label'=>'Clientes','url'=>BASE_URL.'modules/clientes/index.php'],['label'=>'Nuevo','url'=>null]];
require_once __DIR__ . '/../../includes/header.php';
?>
<h5 class="fw-bold mb-4">Nuevo cliente</h5>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="tr-card">
      <div class="tr-card-body">
        <form method="POST">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="tr-form-label">Tipo *</label>
              <select name="tipo" class="form-select">
                <option value="persona">Persona</option>
                <option value="empresa">Empresa</option>
              </select>
            </div>
            <div class="col-md-9">
              <label class="tr-form-label">Nombre / Razón social *</label>
              <input type="text" name="nombre" class="form-control" required autofocus/>
            </div>
            <div class="col-md-4">
              <label class="tr-form-label">DNI / RUC</label>
              <input type="text" name="ruc_dni" class="form-control" maxlength="20"/>
            </div>
            <div class="col-md-4">
              <label class="tr-form-label">Teléfono</label>
              <input type="text" name="telefono" class="form-control"/>
            </div>
            <div class="col-md-4">
              <label class="tr-form-label">WhatsApp</label>
              <input type="text" name="whatsapp" class="form-control" placeholder="51999..."/>
            </div>
            <div class="col-md-6">
              <label class="tr-form-label">Correo electrónico</label>
              <input type="email" name="email" class="form-control"/>
            </div>
            <div class="col-md-6">
              <label class="tr-form-label">Segmento</label>
              <select name="segmento" class="form-select">
                <option value="nuevo">Nuevo</option>
                <option value="frecuente">Frecuente</option>
                <option value="empresa">Empresa</option>
                <option value="vip">VIP</option>
              </select>
            </div>
            <div class="col-md-8">
              <label class="tr-form-label">Dirección</label>
              <input type="text" name="direccion" class="form-control"/>
            </div>
            <div class="col-md-4">
              <label class="tr-form-label">Distrito</label>
              <input type="text" name="distrito" class="form-control"/>
            </div>
            <div class="col-12">
              <label class="tr-form-label">Notas internas</label>
              <textarea name="notas" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-12 d-flex gap-2">
              <button type="submit" class="btn btn-primary">Guardar cliente</button>
              <a href="<?= BASE_URL ?>modules/clientes/index.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
