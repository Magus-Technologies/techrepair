<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
requireLogin();
requireRole([ROL_ADMIN]);
$db = getDB();

// POST: crear, editar, toggle activo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'guardar') {
        $id   = (int)($_POST['id'] ?? 0);
        $tipo = $_POST['tipo']  ?? '';
        $serie= strtoupper(trim($_POST['serie'] ?? ''));
        $desc = trim($_POST['descripcion'] ?? '');
        $num  = (int)($_POST['numero'] ?? 0);
        if ($id) {
            $db->prepare("UPDATE documentos_empresa SET tipo=?,serie=?,descripcion=?,numero=? WHERE id=?")
               ->execute([$tipo,$serie,$desc,$num,$id]);
            setFlash('success','Serie actualizada.');
        } else {
            try {
                $db->prepare("INSERT INTO documentos_empresa (tipo,serie,descripcion,numero,activo) VALUES (?,?,?,?,1)")
                   ->execute([$tipo,$serie,$desc,$num]);
                setFlash('success','Serie creada.');
            } catch (PDOException $e) {
                setFlash('danger','Ya existe esa serie para ese tipo.');
            }
        }
    }

    if ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $db->prepare("UPDATE documentos_empresa SET activo = 1 - activo WHERE id=?")->execute([$id]);
        setFlash('success','Estado actualizado.');
    }

    redirect(BASE_URL.'modules/facturacion/series.php');
}

$series = $db->query("SELECT * FROM documentos_empresa ORDER BY tipo, serie")->fetchAll();
$edit   = null;
if (isset($_GET['editar'])) {
    $st = $db->prepare("SELECT * FROM documentos_empresa WHERE id=?");
    $st->execute([(int)$_GET['editar']]);
    $edit = $st->fetch();
}

$pageTitle  = 'Admin Series — '.APP_NAME;
$breadcrumb = [
    ['label'=>'Facturación','url'=>BASE_URL.'modules/facturacion/index.php'],
    ['label'=>'Series','url'=>null],
];
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="fw-bold mb-0">Administrar Series</h5>
  <a href="<?= BASE_URL ?>modules/facturacion/index.php" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="tr-card">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold"><?= $edit ? 'EDITAR SERIE' : 'NUEVA SERIE' ?></h6></div>
      <div class="tr-card-body">
        <form method="POST">
          <input type="hidden" name="action" value="guardar">
          <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
          <div class="mb-2">
            <label class="tr-form-label">Tipo *</label>
            <select name="tipo" class="form-select form-select-sm" required>
              <?php foreach(['boleta','factura','nota_venta','nota_credito','ticket'] as $t): ?>
              <option value="<?= $t ?>" <?= ($edit['tipo']??'')===$t?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$t)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2">
            <label class="tr-form-label">Serie * (4 caracteres, ej: B001)</label>
            <input type="text" name="serie" class="form-control form-control-sm" maxlength="4" required value="<?= sanitize($edit['serie']??'') ?>"/>
          </div>
          <div class="mb-2">
            <label class="tr-form-label">Descripción</label>
            <input type="text" name="descripcion" class="form-control form-control-sm" value="<?= sanitize($edit['descripcion']??'') ?>"/>
          </div>
          <div class="mb-3">
            <label class="tr-form-label">Último número emitido</label>
            <input type="number" name="numero" class="form-control form-control-sm" min="0" value="<?= (int)($edit['numero']??0) ?>"/>
            <div class="small text-muted mt-1">El próximo comprobante usará este número + 1.</div>
          </div>
          <button type="submit" class="btn btn-primary btn-sm w-100"><?= $edit ? 'Guardar cambios' : 'Crear serie' ?></button>
          <?php if ($edit): ?>
          <a href="<?= BASE_URL ?>modules/facturacion/series.php" class="btn btn-outline-secondary btn-sm w-100 mt-2">Cancelar</a>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="tr-card">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">SERIES REGISTRADAS</h6></div>
      <div class="tr-card-body p-0">
        <table class="tr-table">
          <thead><tr><th>Tipo</th><th>Serie</th><th>Último N°</th><th>Próximo</th><th>Descripción</th><th>Estado</th><th></th></tr></thead>
          <tbody>
            <?php foreach($series as $s): ?>
            <tr>
              <td><span class="badge bg-secondary"><?= sanitize($s['tipo']) ?></span></td>
              <td class="fw-semibold"><code><?= sanitize($s['serie']) ?></code></td>
              <td><?= number_format($s['numero']) ?></td>
              <td class="text-success fw-semibold"><?= number_format($s['numero'] + 1) ?></td>
              <td class="small text-muted"><?= sanitize($s['descripcion']??'') ?></td>
              <td>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= $s['id'] ?>">
                  <button class="btn btn-xs btn-sm btn-outline-<?= $s['activo']?'success':'secondary' ?> py-0">
                    <?= $s['activo'] ? '✅ Activa' : '⏸ Inactiva' ?>
                  </button>
                </form>
              </td>
              <td>
                <a href="?editar=<?= $s['id'] ?>" class="btn btn-outline-secondary btn-sm py-0">
                  <i data-feather="edit-2" style="width:13px;height:13px"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($series)): ?>
            <tr><td colspan="7" class="text-center text-muted py-3">Sin series registradas</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
