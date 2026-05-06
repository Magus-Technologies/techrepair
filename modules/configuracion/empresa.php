<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
requireLogin();
requireRole([ROL_ADMIN]);

$db = getDB();

// Obtener datos de empresa
$emp = $db->query("SELECT * FROM empresa WHERE id=1")->fetch();
if (!$emp) {
    $db->exec("INSERT INTO empresa (id,ruc,razon_social) VALUES (1,'00000000000','MI EMPRESA')");
    $emp = $db->query("SELECT * FROM empresa WHERE id=1")->fetch();
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ap = $_POST['accion'] ?? '';

    // Guardar datos de empresa
    if ($ap === 'guardar') {
        $d = [
            'ruc'               => preg_replace('/\D/', '', $_POST['ruc'] ?? ''),
            'razon_social'      => trim($_POST['razon_social'] ?? ''),
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

        if (strlen($d['ruc']) !== 11) {
            setFlash('danger', 'El RUC debe tener exactamente 11 dígitos.');
            redirect(BASE_URL.'modules/configuracion/empresa.php');
        }

        if (empty($d['razon_social'])) {
            setFlash('danger', 'La razón social es obligatoria.');
            redirect(BASE_URL.'modules/configuracion/empresa.php');
        }

        // Upload logo
        if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp','svg'])) {
                setFlash('danger', 'Solo se permiten imágenes JPG, PNG, WEBP o SVG.');
                redirect(BASE_URL.'modules/configuracion/empresa.php');
            }

            $dir = UPLOAD_PATH . 'empresa/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            $nombre = 'logo_' . uniqid() . '.' . $ext;
            $ruta = $dir . $nombre;

            if (move_uploaded_file($_FILES['logo']['tmp_name'], $ruta)) {
                // Borrar logo anterior
                if (!empty($emp['logo']) && file_exists(UPLOAD_PATH.$emp['logo'])) {
                    @unlink(UPLOAD_PATH.$emp['logo']);
                }
                $d['logo'] = 'empresa/' . $nombre;
            }
        }

        $cols = array_keys($d);
        $sets = implode(',', array_map(fn($c) => "$c=?", $cols));
        $sql  = "UPDATE empresa SET $sets WHERE id=1";
        $db->prepare($sql)->execute(array_values($d));

        setFlash('success', 'Datos de la empresa actualizados correctamente.');
        redirect(BASE_URL.'modules/configuracion/empresa.php');
    }

    // Quitar logo
    if ($ap === 'quitar_logo') {
        if (!empty($emp['logo']) && file_exists(UPLOAD_PATH.$emp['logo'])) {
            @unlink(UPLOAD_PATH.$emp['logo']);
        }
        $db->prepare("UPDATE empresa SET logo=NULL WHERE id=1")->execute();
        setFlash('success', 'Logo eliminado correctamente.');
        redirect(BASE_URL.'modules/configuracion/empresa.php');
    }

    // Subir certificado PEM
    if ($ap === 'subir_pem') {
        if (strlen($emp['ruc']) !== 11) {
            setFlash('danger', 'Primero debes configurar un RUC válido de 11 dígitos.');
            redirect(BASE_URL.'modules/configuracion/empresa.php');
        }

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

        $tmp = $_FILES['pem']['tmp_name'];
        $apiUrl = SUNAT_API_URL;
        $endpoint = rtrim($apiUrl, '/').'/guardar/certificado/'.$emp['ruc'];

        $cfile = curl_file_create($tmp, 'application/x-pem-file', $_FILES['pem']['name']);
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => ['certificado' => $cfile],
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            setFlash('danger', 'Error al conectar con el API SUNAT. Código: '.$httpCode);
            redirect(BASE_URL.'modules/configuracion/empresa.php');
        }

        $json = json_decode($resp, true);
        if (empty($json['estado'])) {
            $msg = $json['mensaje'] ?? 'Error desconocido al subir certificado.';
            setFlash('danger', $msg);
            redirect(BASE_URL.'modules/configuracion/empresa.php');
        }

        $db->prepare("UPDATE empresa SET certificado_subido=1, certificado_fecha=NOW() WHERE id=1")->execute();
        setFlash('success', 'Certificado digital subido correctamente al API SUNAT.');
        redirect(BASE_URL.'modules/configuracion/empresa.php');
    }
}

$pageTitle = 'Configuración de Empresa — '.APP_NAME;
$breadcrumb = [
    ['label'=>'Configuración','url'=>BASE_URL.'modules/configuracion/index.php'],
    ['label'=>'Empresa','url'=>null],
];
require_once __DIR__ . '/../../includes/header.php';
?>

<h5 class="fw-bold mb-3">Configuración de Empresa</h5>

<form method="POST" enctype="multipart/form-data">
  <input type="hidden" name="accion" value="guardar">

  <div class="row g-3">
    <!-- COLUMNA IZQUIERDA -->
    <div class="col-lg-4">
      
      <!-- Logo -->
      <div class="tr-card mb-3">
        <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">LOGO</h6></div>
        <div class="tr-card-body text-center">
          <?php if ($emp['logo']): ?>
          <img src="<?= UPLOAD_URL.$emp['logo'] ?>" alt="Logo" style="max-width:100%;max-height:120px;margin-bottom:12px">
          <form method="POST" style="margin-bottom:12px">
            <input type="hidden" name="accion" value="quitar_logo">
            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Eliminar el logo actual?')">Quitar logo actual</button>
          </form>
          <?php else: ?>
          <div style="padding:40px 20px;background:#f9fafb;border-radius:8px;margin-bottom:12px">
            <i data-feather="image" style="width:48px;height:48px;color:#d1d5db"></i>
            <div class="text-muted small mt-2">Sin logo</div>
          </div>
          <?php endif; ?>
          
          <label class="btn btn-outline-primary btn-sm w-100" for="logoFile">
            <i data-feather="upload" style="width:14px;height:14px"></i> Subir logo
          </label>
          <input type="file" name="logo" id="logoFile" accept="image/*" style="display:none">
          <div id="logoPreview" class="mt-2"></div>
          <div class="text-muted small mt-2">JPG, PNG, WEBP o SVG. Máx 20MB</div>
        </div>
      </div>

      <!-- Apariencia -->
      <div class="tr-card">
        <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">APARIENCIA</h6></div>
        <div class="tr-card-body">
          <div class="mb-3">
            <label class="tr-form-label">Color primario</label>
            <div class="d-flex gap-2">
              <input type="color" name="color_primario" class="form-control form-control-color" value="<?= sanitize($emp['color_primario']) ?>" style="width:60px">
              <input type="text" id="colorTxt" class="form-control form-control-sm" value="<?= sanitize($emp['color_primario']) ?>" readonly>
            </div>
          </div>
          <div class="mb-3">
            <label class="tr-form-label">Moneda</label>
            <input type="text" name="moneda" class="form-control form-control-sm" value="<?= sanitize($emp['moneda']) ?>" placeholder="S/">
          </div>
          <div class="mb-0">
            <label class="tr-form-label">IGV (%)</label>
            <input type="number" name="igv" class="form-control form-control-sm" value="<?= $emp['igv'] ?>" step="0.01" min="0" max="100">
          </div>
        </div>
      </div>

    </div>

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
                <input type="text" name="ruc" id="rucInp" class="form-control" value="<?= sanitize($emp['ruc']) ?>" maxlength="11" required>
                <button type="button" class="btn btn-outline-secondary" id="btnBuscarRuc">
                  <i data-feather="search" style="width:14px;height:14px"></i> Buscar SUNAT
                </button>
              </div>
              <div id="msgRuc" class="small mt-1"></div>
            </div>
            <div class="col-md-6">
              <label class="tr-form-label">Modo SUNAT</label>
              <select name="modo" class="form-select form-select-sm">
                <option value="beta" <?= $emp['modo']==='beta'?'selected':'' ?>>Beta (Pruebas)</option>
                <option value="produccion" <?= $emp['modo']==='produccion'?'selected':'' ?>>Producción (Real)</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="tr-form-label">Razón Social <span class="text-danger">*</span></label>
              <input type="text" name="razon_social" class="form-control form-control-sm" value="<?= sanitize($emp['razon_social']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="tr-form-label">Nombre Comercial</label>
              <input type="text" name="nombre_comercial" class="form-control form-control-sm" value="<?= sanitize($emp['nombre_comercial'] ?? '') ?>">
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
              <input type="text" name="direccion" class="form-control form-control-sm" value="<?= sanitize($emp['direccion'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="tr-form-label">Ubigeo</label>
              <input type="text" name="ubigeo" class="form-control form-control-sm" value="<?= sanitize($emp['ubigeo'] ?? '') ?>" maxlength="6">
            </div>
            <div class="col-md-3">
              <label class="tr-form-label">Departamento</label>
              <input type="text" name="departamento" class="form-control form-control-sm" value="<?= sanitize($emp['departamento'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="tr-form-label">Provincia</label>
              <input type="text" name="provincia" class="form-control form-control-sm" value="<?= sanitize($emp['provincia'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="tr-form-label">Distrito</label>
              <input type="text" name="distrito" class="form-control form-control-sm" value="<?= sanitize($emp['distrito'] ?? '') ?>">
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
              <input type="text" name="telefono" class="form-control form-control-sm" value="<?= sanitize($emp['telefono'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="tr-form-label">Teléfono 2</label>
              <input type="text" name="telefono2" class="form-control form-control-sm" value="<?= sanitize($emp['telefono2'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="tr-form-label">Email</label>
              <input type="email" name="email" class="form-control form-control-sm" value="<?= sanitize($emp['email'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="tr-form-label">Sitio web</label>
              <input type="text" name="web" class="form-control form-control-sm" value="<?= sanitize($emp['web'] ?? '') ?>">
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
              <input type="text" name="sunat_usuario_sol" class="form-control form-control-sm" value="<?= sanitize($emp['sunat_usuario_sol'] ?? '') ?>">
              <div class="small text-muted">Para beta: MODDATOS</div>
            </div>
            <div class="col-md-6">
              <label class="tr-form-label">Clave SOL</label>
              <input type="password" name="sunat_clave_sol" class="form-control form-control-sm" value="<?= sanitize($emp['sunat_clave_sol'] ?? '') ?>">
              <div class="small text-muted">Para beta: MODDATOS</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Certificado Digital -->
      <div class="tr-card mb-3">
        <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">CERTIFICADO DIGITAL (.PEM)</h6></div>
        <div class="tr-card-body">
          <?php if ($emp['certificado_subido']): ?>
          <div class="alert alert-success py-2 mb-3">
            ✅ <strong>CERTIFICADO CARGADO</strong><br>
            <small>Subido el <?= formatDateTime($emp['certificado_fecha']) ?></small>
          </div>
          <?php else: ?>
          <div class="alert alert-warning py-2 mb-3">
            ❌ <strong>SIN CERTIFICADO</strong><br>
            <small>Debes subir el certificado digital para emitir comprobantes</small>
          </div>
          <?php endif; ?>
          
          <input type="file" id="pemFile" accept=".pem" style="display:none">
          <label for="pemFile" class="btn btn-outline-primary btn-sm">
            <i data-feather="upload" style="width:14px;height:14px"></i> <?= $emp['certificado_subido']?'Reemplazar':'Subir' ?> certificado
          </label>
          <div id="pemName" class="small text-muted mt-2"></div>

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
            <input type="text" name="propaganda" class="form-control form-control-sm" value="<?= sanitize($emp['propaganda'] ?? '') ?>" placeholder="Reparamos tu equipo con garantía">
          </div>
          <div class="mb-0">
            <label class="tr-form-label">Pie de página</label>
            <textarea name="pie_pagina" class="form-control form-control-sm" rows="2"><?= sanitize($emp['pie_pagina'] ?? '') ?></textarea>
            <div class="small text-muted">Texto legal que aparece al final del PDF</div>
          </div>
        </div>
      </div>

      <!-- Botón Guardar -->
      <button type="submit" class="btn btn-primary">
        <i data-feather="save" style="width:16px;height:16px"></i> Guardar cambios
      </button>

    </div>
  </div>
</form>

<?php
$pageScripts = <<<'JS'
<script>
console.log('=== SCRIPT CARGADO ===');

const rucInp = document.getElementById('rucInp');
const btnBuscarRuc = document.getElementById('btnBuscarRuc');
const msgRuc = document.getElementById('msgRuc');

console.log('Elementos:', {rucInp, btnBuscarRuc, msgRuc});

if (btnBuscarRuc) {
  btnBuscarRuc.addEventListener('click', async () => {
    console.log('¡CLICK!');
    const doc = rucInp.value.trim();
    
    if (doc.length !== 11) {
      msgRuc.innerHTML = '<small style="color:#e05252">El RUC debe tener 11 dígitos.</small>';
      return;
    }
    
    btnBuscarRuc.disabled = true;
    btnBuscarRuc.textContent = 'Buscando...';
    
    try {
      const url = window.BASE_URL + 'modules/clientes/api_documento.php?doc=' + doc;
      console.log('URL:', url);
      const r = await fetch(url);
      const j = await r.json();
      console.log('Respuesta:', j);
      
      if (j.ok) {
        document.querySelector('input[name="razon_social"]').value = j.data.razon_social || '';
        document.querySelector('input[name="direccion"]').value = j.data.direccion || '';
        document.querySelector('input[name="distrito"]').value = j.data.distrito || '';
        document.querySelector('input[name="provincia"]').value = j.data.provincia || '';
        document.querySelector('input[name="departamento"]').value = j.data.departamento || '';
        msgRuc.innerHTML = '<small style="color:#10b981">✓ ' + j.data.razon_social + '</small>';
      } else {
        msgRuc.innerHTML = '<small style="color:#e05252">No encontrado</small>';
      }
    } catch (err) {
      console.error('ERROR:', err);
      msgRuc.innerHTML = '<small style="color:#e05252">Error: ' + err.message + '</small>';
    } finally {
      btnBuscarRuc.disabled = false;
      btnBuscarRuc.textContent = 'Buscar SUNAT';
    }
  });
}

if (rucInp) {
  rucInp.addEventListener('input', () => {
    rucInp.value = rucInp.value.replace(/\D/g, '');
  });
}

const colorInp = document.querySelector('input[name="color_primario"]');
const colorTxt = document.getElementById('colorTxt');
if (colorInp && colorTxt) {
  colorInp.addEventListener('input', () => colorTxt.value = colorInp.value);
}

const logoFile = document.getElementById('logoFile');
const logoPreview = document.getElementById('logoPreview');
if (logoFile && logoPreview) {
  logoFile.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = (ev) => {
        logoPreview.innerHTML = '<img src="' + ev.target.result + '" style="max-width:100%;max-height:120px;margin-top:8px;border-radius:8px">';
      };
      reader.readAsDataURL(file);
    }
  });
}

const pemFile = document.getElementById('pemFile');
const pemName = document.getElementById('pemName');
if (pemFile && pemName) {
  pemFile.addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    
    pemName.innerHTML = '<span class="text-info">Subiendo ' + file.name + '...</span>';
    
    const formData = new FormData();
    formData.append('accion', 'subir_pem');
    formData.append('pem', file);
    
    try {
      const r = await fetch(window.location.href, {
        method: 'POST',
        body: formData
      });
      
      if (r.ok) {
        pemName.innerHTML = '<span class="text-success">✓ Certificado subido correctamente</span>';
        setTimeout(() => location.reload(), 1500);
      } else {
        pemName.innerHTML = '<span class="text-danger">Error al subir certificado</span>';
      }
    } catch (err) {
      pemName.innerHTML = '<span class="text-danger">Error: ' + err.message + '</span>';
    }
  });
}

feather.replace();
console.log('=== SCRIPT COMPLETO ===');
</script>
JS;
echo "<!-- DEBUG EMPRESA: pageScripts definido, longitud: " . strlen($pageScripts) . " -->\n";
require_once __DIR__ . '/../../includes/footer.php'; ?>

