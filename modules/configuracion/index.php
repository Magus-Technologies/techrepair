<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
requireLogin();
requireRole([ROL_ADMIN]);
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $campos = ['empresa_nombre','empresa_ruc','empresa_direccion','empresa_telefono','empresa_email',
               'igv_porcentaje','garantia_defecto_dias','whatsapp_api_token','whatsapp_phone_id',
               'smtp_host','smtp_user','smtp_pass','smtp_port','moneda','moneda_simbolo'];
    foreach ($campos as $c) {
        if (isset($_POST[$c])) {
            $db->prepare("UPDATE configuracion SET valor=? WHERE clave=?")->execute([trim($_POST[$c]),$c]);
        }
    }
    setFlash('success','Configuración guardada correctamente.');
    redirect(BASE_URL.'modules/configuracion/index.php');
}

$config = [];
$rows = $db->query("SELECT clave,valor FROM configuracion")->fetchAll();
foreach ($rows as $r) $config[$r['clave']] = $r['valor'];

$pageTitle  = 'Configuración — '.APP_NAME;
$breadcrumb = [['label'=>'Configuración','url'=>null]];
require_once __DIR__ . '/../../includes/header.php';

function cf($key, $config) { return sanitize($config[$key] ?? ''); }
?>
<h5 class="fw-bold mb-4">Configuración del sistema</h5>
<form method="POST">
<div class="row g-3">
  <div class="col-lg-6">
    <!-- Datos empresa -->
    <div class="tr-card mb-3">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">DATOS DE LA EMPRESA</h6></div>
      <div class="tr-card-body">
        <div class="row g-2">
          <div class="col-12"><label class="tr-form-label">Nombre de la empresa</label><input type="text" name="empresa_nombre" class="form-control" value="<?= cf('empresa_nombre',$config) ?>"/></div>
          <div class="col-md-6"><label class="tr-form-label">RUC</label><input type="text" name="empresa_ruc" class="form-control" value="<?= cf('empresa_ruc',$config) ?>"/></div>
          <div class="col-md-6"><label class="tr-form-label">Teléfono</label><input type="text" name="empresa_telefono" class="form-control" value="<?= cf('empresa_telefono',$config) ?>"/></div>
          <div class="col-12"><label class="tr-form-label">Dirección</label><input type="text" name="empresa_direccion" class="form-control" value="<?= cf('empresa_direccion',$config) ?>"/></div>
          <div class="col-12"><label class="tr-form-label">Email</label><input type="email" name="empresa_email" class="form-control" value="<?= cf('empresa_email',$config) ?>"/></div>
        </div>
      </div>
    </div>
    <!-- Facturación -->
    <div class="tr-card mb-3">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">FACTURACIÓN</h6></div>
      <div class="tr-card-body">
        <div class="row g-2">
          <div class="col-md-4"><label class="tr-form-label">IGV (%)</label><input type="number" name="igv_porcentaje" class="form-control" value="<?= cf('igv_porcentaje',$config) ?>"/></div>
          <div class="col-md-4"><label class="tr-form-label">Moneda</label><input type="text" name="moneda" class="form-control" value="<?= cf('moneda',$config) ?>"/></div>
          <div class="col-md-4"><label class="tr-form-label">Símbolo</label><input type="text" name="moneda_simbolo" class="form-control" value="<?= cf('moneda_simbolo',$config) ?>"/></div>
          <div class="col-md-6"><label class="tr-form-label">Garantía por defecto (días)</label><input type="number" name="garantia_defecto_dias" class="form-control" value="<?= cf('garantia_defecto_dias',$config) ?>"/></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <!-- WhatsApp -->
    <div class="tr-card mb-3">
      <div class="tr-card-header">
        <h6 class="mb-0 small fw-semibold">WHATSAPP BUSINESS API</h6>
        <span class="badge bg-warning text-dark">Meta / 360Dialog</span>
      </div>
      <div class="tr-card-body">
        <div class="alert alert-info small py-2">Configura tu token de WhatsApp Business API para enviar notificaciones automáticas.</div>
        <div class="mb-2"><label class="tr-form-label">API Token</label><input type="text" name="whatsapp_api_token" class="form-control form-control-sm" value="<?= cf('whatsapp_api_token',$config) ?>" placeholder="EAAxxxxxx..."/></div>
        <div class="mb-2"><label class="tr-form-label">Phone Number ID</label><input type="text" name="whatsapp_phone_id" class="form-control form-control-sm" value="<?= cf('whatsapp_phone_id',$config) ?>"/></div>
      </div>
    </div>
    <!-- SMTP -->
    <div class="tr-card mb-3">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">CORREO SMTP</h6></div>
      <div class="tr-card-body">
        <div class="row g-2">
          <div class="col-md-8"><label class="tr-form-label">Host SMTP</label><input type="text" name="smtp_host" class="form-control form-control-sm" value="<?= cf('smtp_host',$config) ?>" placeholder="smtp.gmail.com"/></div>
          <div class="col-md-4"><label class="tr-form-label">Puerto</label><input type="text" name="smtp_port" class="form-control form-control-sm" value="<?= cf('smtp_port',$config) ?>"/></div>
          <div class="col-md-6"><label class="tr-form-label">Usuario</label><input type="text" name="smtp_user" class="form-control form-control-sm" value="<?= cf('smtp_user',$config) ?>"/></div>
          <div class="col-md-6"><label class="tr-form-label">Contraseña</label><input type="password" name="smtp_pass" class="form-control form-control-sm" placeholder="••••••••"/></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12">
    <button type="submit" class="btn btn-primary">
      <i data-feather="save" style="width:15px;height:15px"></i> Guardar configuración
    </button>
  </div>
</div>
</form>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
