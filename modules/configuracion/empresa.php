<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
requireLogin();
requireRole([ROL_ADMIN]);

$db = getDB();

$emp = $db->query("SELECT * FROM empresa WHERE id=1")->fetch();
if (!$emp) {
    $db->exec("INSERT INTO empresa (id,ruc,razon_social) VALUES (1,'00000000000','MI EMPRESA')");
    $emp = $db->query("SELECT * FROM empresa WHERE id=1")->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ap = $_POST['accion'] ?? '';

    // ── Guardar datos ────────────────────────────────────────
    if ($ap === 'guardar') {
        $ruc          = preg_replace('/\D/', '', $_POST['ruc'] ?? '');
        $razon_social = trim($_POST['razon_social'] ?? '');

        if (strlen($ruc) !== 11) {
            setFlash('danger', 'El RUC debe tener exactamente 11 dígitos.');
            redirect(BASE_URL.'modules/configuracion/empresa.php');
        }
        if (empty($razon_social)) {
            setFlash('danger', 'La razón social es obligatoria.');
            redirect(BASE_URL.'modules/configuracion/empresa.php');
        }

        $d = [
            'ruc'               => $ruc,
            'razon_social'      => $razon_social,
            'nombre_comercial'  => trim($_POST['nombre_comercial'] ?? ''),
            'direccion'         => trim($_POST['direccion'] ?? ''),
            'ubigeo'            => trim($_POST['ubigeo'] ?? ''),
            'distrito'          => trim($_POST['distrito'] ?? ''),
            'provincia'         => trim($_POST['provincia'] ?? ''),
            'departamento'      => trim($_POST['departamento'] ?? ''),
            'telefono'          => trim($_POST['telefono'] ?? ''),
            'telefono2'         => trim($_POST['telefono2'] ?? ''),
            'email'             => trim($_POST['email'] ?? ''),
            'web'               => trim($_POST['web'] ?? ''),
            'igv'               => (float)($_POST['igv'] ?? 18),
            'moneda'            => trim($_POST['moneda'] ?? 'S/'),
            'color_primario'    => trim($_POST['color_primario'] ?? '#4f46e5'),
            'propaganda'        => trim($_POST['propaganda'] ?? ''),
            'pie_pagina'        => trim($_POST['pie_pagina'] ?? ''),
            'modo'              => $_POST['modo'] ?? 'beta',
            'sunat_usuario_sol' => trim($_POST['sunat_usuario_sol'] ?? ''),
            'sunat_clave_sol'   => trim($_POST['sunat_clave_sol'] ?? ''),
        ];

        // Solo actualiza logo si se sube uno nuevo
        if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp','svg'])) {
                setFlash('danger', 'Solo se permiten imágenes JPG, PNG, WEBP o SVG.');
                redirect(BASE_URL.'modules/configuracion/empresa.php');
            }
            $uploadDir = UPLOAD_PATH . 'empresa/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $nombre = 'logo_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $nombre)) {
                if (!empty($emp['logo']) && file_exists(UPLOAD_PATH . $emp['logo'])) {
                    @unlink(UPLOAD_PATH . $emp['logo']);
                }
                $d['logo'] = 'empresa/' . $nombre;
            }
        }
        // Si no se subió logo, no se incluye en $d → no se toca en el UPDATE

        $sets = implode(',', array_map(fn($c) => "$c=?", array_keys($d)));
        $db->prepare("UPDATE empresa SET $sets WHERE id=1")->execute(array_values($d));

        setFlash('success', 'Datos de la empresa guardados correctamente.');
        redirect(BASE_URL.'modules/configuracion/empresa.php');
    }

    // ── Quitar logo ──────────────────────────────────────────
    if ($ap === 'quitar_logo') {
        if (!empty($emp['logo']) && file_exists(UPLOAD_PATH . $emp['logo'])) {
            @unlink(UPLOAD_PATH . $emp['logo']);
        }
        $db->prepare("UPDATE empresa SET logo=NULL WHERE id=1")->execute();
        setFlash('success', 'Logo eliminado.');
        redirect(BASE_URL.'modules/configuracion/empresa.php');
    }

    // ── Subir certificado PEM ────────────────────────────────
    if ($ap === 'subir_pem') {
        if (empty($_FILES['pem']['name']) || $_FILES['pem']['error'] !== UPLOAD_ERR_OK) {
            setFlash('danger', 'No se recibió el archivo .pem');
            redirect(BASE_URL.'modules/configuracion/empresa.php');
        }
        $ext = strtolower(pathinfo($_FILES['pem']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pem') {
            setFlash('danger', 'Solo se acepta archivo .pem');
            redirect(BASE_URL.'modules/configuracion/empresa.php');
        }
        if ($_FILES['pem']['size'] > 512 * 1024) {
            setFlash('danger', 'El certificado no debe superar 512 KB.');
            redirect(BASE_URL.'modules/configuracion/empresa.php');
        }
        $tmp      = $_FILES['pem']['tmp_name'];
        $apiUrl   = defined('SUNAT_API_URL') ? SUNAT_API_URL : '';
        $endpoint = rtrim($apiUrl, '/').'/guardar/certificado/'.$emp['ruc'];
        $cfile    = curl_file_create($tmp, 'application/x-pem-file', $_FILES['pem']['name']);
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => ['certificado' => $cfile],
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) {
            setFlash('danger', 'Error al conectar con el API SUNAT. Código: '.$httpCode);
            redirect(BASE_URL.'modules/configuracion/empresa.php');
        }
        $json = json_decode($resp, true);
        if (empty($json['estado'])) {
            setFlash('danger', $json['mensaje'] ?? 'Error desconocido al subir certificado.');
            redirect(BASE_URL.'modules/configuracion/empresa.php');
        }
        $db->prepare("UPDATE empresa SET certificado_subido=1, certificado_fecha=NOW() WHERE id=1")->execute();
        setFlash('success', 'Certificado digital subido correctamente.');
        redirect(BASE_URL.'modules/configuracion/empresa.php');
    }
}

$pageTitle  = 'Configuración de Empresa — '.APP_NAME;
$breadcrumb = [
    ['label'=>'Configuración','url'=>BASE_URL.'modules/configuracion/index.php'],
    ['label'=>'Empresa','url'=>null],
];
require_once __DIR__ . '/../../includes/header.php';
?>

<h5 class="fw-bold mb-3">Configuración de Empresa</h5>

<?php
/*
 * IMPORTANTE: Los forms secundarios (quitar_logo, subir_pem) están FUERA del form principal.
 * HTML no permite forms anidados — si se anidan, el browser los ignora
 * y todo va al form padre causando el error "No se recibió el archivo .pem".
 */
?>

<!-- ══ FORM SECUNDARIO: Quitar logo (fuera del form principal) ══ -->
<?php if (!empty($emp['logo'])): ?>
<form method="POST" id="form-quitar-logo" style="display:none">
  <input type="hidden" name="accion" value="quitar_logo">
</form>
<?php endif; ?>

<!-- ══ FORM SECUNDARIO: Subir PEM (fuera del form principal) ══ -->
<form method="POST" enctype="multipart/form-data" id="form-pem" style="display:none">
  <input type="hidden" name="accion" value="subir_pem">
  <input type="file" name="pem" id="pemFileInput" accept=".pem"
         onchange="document.getElementById('form-pem').submit()">
</form>

<!-- ══ FORM PRINCIPAL: Guardar datos + logo ══ -->
<form method="POST" enctype="multipart/form-data" id="form-empresa">
  <input type="hidden" name="accion" value="guardar">

  <div class="row g-3">

    <!-- COLUMNA IZQUIERDA -->
    <div class="col-lg-4">

      <!-- Logo -->
      <div class="tr-card mb-3">
        <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">LOGO</h6></div>
        <div class="tr-card-body text-center">

          <?php if (!empty($emp['logo'])): ?>
          <img src="<?= UPLOAD_URL . htmlspecialchars($emp['logo']) ?>" alt="Logo actual"
               id="logo-preview-img"
               style="max-width:100%;max-height:120px;margin-bottom:12px;border-radius:6px;display:block;margin-left:auto;margin-right:auto">
          <?php else: ?>
          <div id="logo-placeholder" style="padding:30px 20px;background:#f9fafb;border-radius:8px;margin-bottom:12px">
            <i data-feather="image" style="width:40px;height:40px;color:#d1d5db"></i>
            <div class="text-muted small mt-2">Sin logo</div>
          </div>
          <?php endif; ?>

          <div id="logo-new-preview" class="mb-2"></div>

          <label class="btn btn-outline-primary btn-sm w-100 mb-2" for="logoFile">
            <i data-feather="upload" style="width:14px;height:14px"></i>
            <?= !empty($emp['logo']) ? 'Cambiar logo' : 'Subir logo' ?>
          </label>
          <!-- Este input SÍ está dentro del form principal -->
          <input type="file" name="logo" id="logoFile" accept="image/*" style="display:none">
          <div class="text-muted small">JPG, PNG, WEBP o SVG</div>

          <?php if (!empty($emp['logo'])): ?>
          <hr style="margin:12px 0">
          <button type="button" class="btn btn-outline-danger btn-sm w-100"
                  onclick="if(confirm('¿Eliminar el logo actual?')) document.getElementById('form-quitar-logo').submit()">
            <i data-feather="trash-2" style="width:13px;height:13px"></i> Quitar logo
          </button>
          <?php endif; ?>

        </div>
      </div>

      <!-- Apariencia -->
      <div class="tr-card">
        <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">APARIENCIA</h6></div>
        <div class="tr-card-body">
          <div class="mb-3">
            <label class="tr-form-label">Color primario</label>
            <div class="d-flex gap-2 align-items-center">
              <input type="color" name="color_primario" id="colorPicker"
                     class="form-control form-control-color"
                     value="<?= sanitize($emp['color_primario'] ?? '#4f46e5') ?>"
                     style="width:50px;height:34px;padding:2px">
              <input type="text" id="colorTxt" class="form-control form-control-sm"
                     value="<?= sanitize($emp['color_primario'] ?? '#4f46e5') ?>" readonly>
            </div>
          </div>
          <div class="mb-3">
            <label class="tr-form-label">Moneda</label>
            <input type="text" name="moneda" class="form-control form-control-sm"
                   value="<?= sanitize($emp['moneda'] ?? 'S/') ?>" placeholder="S/">
          </div>
          <div>
            <label class="tr-form-label">IGV (%)</label>
            <input type="number" name="igv" class="form-control form-control-sm"
                   value="<?= (float)($emp['igv'] ?? 18) ?>" step="0.01" min="0" max="100">
          </div>
        </div>
      </div>

    </div><!-- /col-lg-4 -->

    <!-- COLUMNA DERECHA -->
    <div class="col-lg-8">

      <!-- Datos Fiscales -->
      <div class="tr-card mb-3">
        <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">DATOS FISCALES</h6></div>
        <div class="tr-card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="tr-form-label">RUC <span class="text-danger">*</span></label>
              <div class="input-group input-group-sm">
                <input type="text" name="ruc" id="rucInp" class="form-control"
                       value="<?= sanitize($emp['ruc'] ?? '') ?>" maxlength="11" required>
                <button type="button" class="btn btn-outline-secondary" id="btnBuscarRuc">
                  <i data-feather="search" style="width:14px;height:14px"></i> Buscar SUNAT
                </button>
              </div>
              <div id="msgRuc" class="small mt-1"></div>
            </div>
            <div class="col-md-6">
              <label class="tr-form-label">Modo SUNAT</label>
              <select name="modo" class="form-select form-select-sm">
                <option value="beta"       <?= ($emp['modo']??'beta')==='beta'?'selected':'' ?>>Beta (Pruebas)</option>
                <option value="produccion" <?= ($emp['modo']??'')==='produccion'?'selected':'' ?>>Producción (Real)</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="tr-form-label">Razón Social <span class="text-danger">*</span></label>
              <input type="text" name="razon_social" class="form-control form-control-sm"
                     value="<?= sanitize($emp['razon_social'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="tr-form-label">Nombre Comercial</label>
              <input type="text" name="nombre_comercial" class="form-control form-control-sm"
                     value="<?= sanitize($emp['nombre_comercial'] ?? '') ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- Dirección -->
      <div class="tr-card mb-3">
        <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">DIRECCIÓN FISCAL</h6></div>
        <div class="tr-card-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="tr-form-label">Dirección completa</label>
              <input type="text" name="direccion" class="form-control form-control-sm"
                     value="<?= sanitize($emp['direccion'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="tr-form-label">Ubigeo</label>
              <input type="text" name="ubigeo" class="form-control form-control-sm"
                     value="<?= sanitize($emp['ubigeo'] ?? '') ?>" maxlength="6">
            </div>
            <div class="col-md-3">
              <label class="tr-form-label">Departamento</label>
              <input type="text" name="departamento" class="form-control form-control-sm"
                     value="<?= sanitize($emp['departamento'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="tr-form-label">Provincia</label>
              <input type="text" name="provincia" class="form-control form-control-sm"
                     value="<?= sanitize($emp['provincia'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="tr-form-label">Distrito</label>
              <input type="text" name="distrito" class="form-control form-control-sm"
                     value="<?= sanitize($emp['distrito'] ?? '') ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- Contacto -->
      <div class="tr-card mb-3">
        <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">CONTACTO</h6></div>
        <div class="tr-card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="tr-form-label">Teléfono principal</label>
              <input type="text" name="telefono" class="form-control form-control-sm"
                     value="<?= sanitize($emp['telefono'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="tr-form-label">Teléfono 2</label>
              <input type="text" name="telefono2" class="form-control form-control-sm"
                     value="<?= sanitize($emp['telefono2'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="tr-form-label">Email</label>
              <input type="email" name="email" class="form-control form-control-sm"
                     value="<?= sanitize($emp['email'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="tr-form-label">Sitio web</label>
              <input type="text" name="web" class="form-control form-control-sm"
                     value="<?= sanitize($emp['web'] ?? '') ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- Configuración SUNAT -->
      <div class="tr-card mb-3">
        <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">CONFIGURACIÓN SUNAT</h6></div>
        <div class="tr-card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="tr-form-label">Usuario SOL</label>
              <input type="text" name="sunat_usuario_sol" class="form-control form-control-sm"
                     value="<?= sanitize($emp['sunat_usuario_sol'] ?? '') ?>">
              <div class="small text-muted">Para beta: MODDATOS</div>
            </div>
            <div class="col-md-6">
              <label class="tr-form-label">Clave SOL</label>
              <input type="password" name="sunat_clave_sol" class="form-control form-control-sm"
                     value="<?= sanitize($emp['sunat_clave_sol'] ?? '') ?>">
              <div class="small text-muted">Para beta: MODDATOS</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Certificado Digital -->
      <div class="tr-card mb-3">
        <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">CERTIFICADO DIGITAL (.PEM)</h6></div>
        <div class="tr-card-body">
          <?php if (!empty($emp['certificado_subido'])): ?>
          <div class="alert alert-success py-2 mb-3">
            ✅ <strong>CERTIFICADO CARGADO</strong> — Subido el <?= formatDateTime($emp['certificado_fecha']) ?>
          </div>
          <?php else: ?>
          <div class="alert alert-warning py-2 mb-3">
            ❌ <strong>SIN CERTIFICADO</strong> — Necesario para emitir comprobantes SUNAT
          </div>
          <?php endif; ?>

          <label class="btn btn-outline-primary btn-sm" for="pemFileInput">
            <i data-feather="upload" style="width:14px;height:14px"></i>
            <?= !empty($emp['certificado_subido']) ? 'Reemplazar' : 'Subir' ?> certificado .pem
          </label>
          <span id="pemName" class="small text-muted ms-2"></span>

          <div class="small text-muted mt-3">
            <strong>Convertir .pfx a .pem:</strong><br>
            <code style="font-size:11px">openssl pkcs12 -in cert.pfx -out cert.pem -nodes</code>
          </div>
        </div>
      </div>

      <!-- Textos del Comprobante -->
      <div class="tr-card mb-3">
        <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">TEXTOS DEL COMPROBANTE</h6></div>
        <div class="tr-card-body">
          <div class="mb-3">
            <label class="tr-form-label">Propaganda / Slogan</label>
            <input type="text" name="propaganda" class="form-control form-control-sm"
                   value="<?= sanitize($emp['propaganda'] ?? '') ?>"
                   placeholder="Reparamos tu equipo con garantía">
          </div>
          <div>
            <label class="tr-form-label">Pie de página</label>
            <textarea name="pie_pagina" class="form-control form-control-sm" rows="2"><?= sanitize($emp['pie_pagina'] ?? '') ?></textarea>
            <div class="small text-muted">Aparece al final del PDF de la OT</div>
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary">
        <i data-feather="save" style="width:16px;height:16px"></i> Guardar cambios
      </button>

    </div><!-- /col-lg-8 -->
  </div>
</form><!-- /form-empresa -->

<script>
// Preview logo
document.getElementById('logoFile').addEventListener('change', function(e) {
  const file = e.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = ev => {
    document.getElementById('logo-new-preview').innerHTML =
      '<div class="small text-muted mb-1">Vista previa:</div>' +
      '<img src="' + ev.target.result + '" style="max-width:100%;max-height:120px;border-radius:6px">';
  };
  reader.readAsDataURL(file);
});

// Color picker
document.getElementById('colorPicker').addEventListener('input', function() {
  document.getElementById('colorTxt').value = this.value;
});

// RUC — solo números
document.getElementById('rucInp').addEventListener('input', function() {
  this.value = this.value.replace(/\D/g, '');
});

// Buscar RUC SUNAT
document.getElementById('btnBuscarRuc').addEventListener('click', async function() {
  const doc = document.getElementById('rucInp').value.trim();
  const msg = document.getElementById('msgRuc');
  if (doc.length !== 11) { msg.innerHTML = '<span class="text-danger small">El RUC debe tener 11 dígitos.</span>'; return; }
  this.disabled = true; this.textContent = 'Buscando...';
  try {
    const r = await fetch(window.BASE_URL + 'modules/clientes/api_documento.php?doc=' + doc);
    const j = await r.json();
    if (j.ok) {
      document.querySelector('[name="razon_social"]').value = j.data.razon_social || '';
      document.querySelector('[name="direccion"]').value    = j.data.direccion    || '';
      document.querySelector('[name="distrito"]').value     = j.data.distrito     || '';
      document.querySelector('[name="provincia"]').value    = j.data.provincia    || '';
      document.querySelector('[name="departamento"]').value = j.data.departamento || '';
      msg.innerHTML = '<span class="text-success small">✓ ' + (j.data.razon_social||'') + '</span>';
    } else {
      msg.innerHTML = '<span class="text-danger small">No encontrado en SUNAT</span>';
    }
  } catch(err) {
    msg.innerHTML = '<span class="text-danger small">Error: ' + err.message + '</span>';
  } finally {
    this.disabled = false;
    this.innerHTML = '<i data-feather="search" style="width:14px;height:14px"></i> Buscar SUNAT';
    feather.replace();
  }
});

// PEM — mostrar nombre y hacer submit del form correcto
document.getElementById('pemFileInput').addEventListener('change', function() {
  if (this.files[0]) {
    document.getElementById('pemName').textContent = 'Subiendo: ' + this.files[0].name + '...';
    document.getElementById('form-pem').submit();
  }
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
