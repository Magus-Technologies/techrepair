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

// Procesar guardado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar') {
    $ruc = preg_replace('/\D/', '', $_POST['ruc'] ?? '');
    $razon = trim($_POST['razon_social'] ?? '');
    
    if (strlen($ruc) === 11 && !empty($razon)) {
        $db->prepare("UPDATE empresa SET ruc=?, razon_social=?, nombre_comercial=?, direccion=?, distrito=?, provincia=?, departamento=? WHERE id=1")
           ->execute([$ruc, $razon, $_POST['nombre_comercial'], $_POST['direccion'], $_POST['distrito'], $_POST['provincia'], $_POST['departamento']]);
        header('Location: empresa_simple.php?success=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Empresa - TechRepair</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <div class="container mt-5">
    <h3>Configuración de Empresa</h3>
    
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Datos guardados correctamente</div>
    <?php endif; ?>
    
    <form method="POST">
      <input type="hidden" name="accion" value="guardar">
      
      <div class="row">
        <div class="col-md-6 mb-3">
          <label>RUC *</label>
          <div class="input-group">
            <input type="text" name="ruc" id="rucInp" class="form-control" value="<?= htmlspecialchars($emp['ruc']) ?>" maxlength="11" required>
            <button type="button" class="btn btn-primary" id="btnBuscarRuc">Buscar SUNAT</button>
          </div>
          <div id="msgRuc" class="mt-1"></div>
        </div>
        
        <div class="col-md-6 mb-3">
          <label>Razón Social *</label>
          <input type="text" name="razon_social" class="form-control" value="<?= htmlspecialchars($emp['razon_social']) ?>" required>
        </div>
        
        <div class="col-md-6 mb-3">
          <label>Nombre Comercial</label>
          <input type="text" name="nombre_comercial" class="form-control" value="<?= htmlspecialchars($emp['nombre_comercial'] ?? '') ?>">
        </div>
        
        <div class="col-md-6 mb-3">
          <label>Dirección</label>
          <input type="text" name="direccion" class="form-control" value="<?= htmlspecialchars($emp['direccion'] ?? '') ?>">
        </div>
        
        <div class="col-md-4 mb-3">
          <label>Distrito</label>
          <input type="text" name="distrito" class="form-control" value="<?= htmlspecialchars($emp['distrito'] ?? '') ?>">
        </div>
        
        <div class="col-md-4 mb-3">
          <label>Provincia</label>
          <input type="text" name="provincia" class="form-control" value="<?= htmlspecialchars($emp['provincia'] ?? '') ?>">
        </div>
        
        <div class="col-md-4 mb-3">
          <label>Departamento</label>
          <input type="text" name="departamento" class="form-control" value="<?= htmlspecialchars($emp['departamento'] ?? '') ?>">
        </div>
      </div>
      
      <button type="submit" class="btn btn-success">Guardar</button>
      <a href="<?= BASE_URL ?>modules/dashboard/index.php" class="btn btn-secondary">Volver</a>
    </form>
  </div>

  <script>
    const rucInp = document.getElementById('rucInp');
    const btnBuscarRuc = document.getElementById('btnBuscarRuc');
    const msgRuc = document.getElementById('msgRuc');

    console.log('Elementos:', {rucInp, btnBuscarRuc, msgRuc});

    btnBuscarRuc.addEventListener('click', async () => {
      console.log('Click!');
      const doc = rucInp.value.trim();
      
      if (doc.length !== 11) {
        msgRuc.innerHTML = '<small class="text-danger">El RUC debe tener 11 dígitos.</small>';
        return;
      }

      btnBuscarRuc.disabled = true;
      btnBuscarRuc.textContent = 'Buscando...';
      msgRuc.innerHTML = '';

      try {
        const url = '<?= BASE_URL ?>modules/clientes/api_documento.php?doc=' + doc;
        console.log('URL:', url);
        const r = await fetch(url);
        const j = await r.json();
        console.log('Respuesta:', j);

        if (!j.ok) {
          msgRuc.innerHTML = '<small class="text-danger">' + (j.error || 'No encontrado') + '</small>';
          return;
        }

        document.querySelector('input[name="razon_social"]').value = j.data.razon_social || '';
        document.querySelector('input[name="direccion"]').value = j.data.direccion || '';
        document.querySelector('input[name="distrito"]').value = j.data.distrito || '';
        document.querySelector('input[name="provincia"]').value = j.data.provincia || '';
        document.querySelector('input[name="departamento"]').value = j.data.departamento || '';
        msgRuc.innerHTML = '<small class="text-success">✓ ' + j.data.razon_social + '</small>';
      } catch (err) {
        console.error('Error:', err);
        msgRuc.innerHTML = '<small class="text-danger">Error: ' + err.message + '</small>';
      } finally {
        btnBuscarRuc.disabled = false;
        btnBuscarRuc.textContent = 'Buscar SUNAT';
      }
    });
  </script>
</body>
</html>
