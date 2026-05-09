<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
requireLogin();
requireRole([ROL_ADMIN]);

$db = getDB();

// POST: crear, editar, toggle activo, eliminar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'guardar') {
        $id     = (int)($_POST['id'] ?? 0);
        $codigo = strtolower(trim(preg_replace('/[^a-z0-9_]/', '_', $_POST['codigo'] ?? '')));
        $nombre = trim($_POST['nombre'] ?? '');
        $color  = trim($_POST['color'] ?? 'secondary');
        $icono  = trim($_POST['icono'] ?? 'circle');
        $orden  = (int)($_POST['orden'] ?? 0);

        if (empty($codigo) || empty($nombre)) {
            setFlash('danger', 'El código y nombre son obligatorios.');
            redirect(BASE_URL.'modules/configuracion/estados.php');
        }

        if ($id) {
            // Editar
            $db->prepare("UPDATE estados_orden SET nombre=?, color=?, icono=?, orden=? WHERE id=?")
               ->execute([$nombre, $color, $icono, $orden, $id]);
            setFlash('success', 'Estado actualizado.');
        } else {
            // Crear
            try {
                $db->prepare("INSERT INTO estados_orden (codigo, nombre, color, icono, orden, activo) VALUES (?,?,?,?,?,1)")
                   ->execute([$codigo, $nombre, $color, $icono, $orden]);
                setFlash('success', 'Estado creado.');
            } catch (PDOException $e) {
                setFlash('danger', 'Ya existe un estado con ese código.');
            }
        }
    }

    if ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $db->prepare("UPDATE estados_orden SET activo = 1 - activo WHERE id=?")->execute([$id]);
        setFlash('success', 'Estado actualizado.');
    }

    if ($action === 'eliminar') {
        $id = (int)$_POST['id'];
        
        // Verificar si es estado del sistema
        $est = $db->prepare("SELECT sistema, codigo FROM estados_orden WHERE id=?");
        $est->execute([$id]);
        $est = $est->fetch();
        
        if ($est && $est['sistema']) {
            setFlash('danger', 'No se puede eliminar un estado del sistema.');
            redirect(BASE_URL.'modules/configuracion/estados.php');
        }
        
        // Verificar si hay OTs con este estado
        $count = $db->prepare("SELECT COUNT(*) FROM ordenes_trabajo WHERE estado=? AND deleted_at IS NULL");
        $count->execute([$est['codigo']]);
        $count = $count->fetchColumn();
        
        if ($count > 0) {
            setFlash('danger', "No se puede eliminar: hay $count órdenes de trabajo con este estado.");
            redirect(BASE_URL.'modules/configuracion/estados.php');
        }
        
        // Eliminar
        $db->prepare("DELETE FROM estados_orden WHERE id=?")->execute([$id]);
        setFlash('success', 'Estado eliminado.');
    }

    redirect(BASE_URL.'modules/configuracion/estados.php');
}

$estados = $db->query("SELECT * FROM estados_orden ORDER BY orden, nombre")->fetchAll();
$edit = null;
if (isset($_GET['editar'])) {
    $st = $db->prepare("SELECT * FROM estados_orden WHERE id=?");
    $st->execute([(int)$_GET['editar']]);
    $edit = $st->fetch();
}

$pageTitle  = 'Estados de Orden — '.APP_NAME;
$breadcrumb = [
    ['label'=>'Configuración','url'=>BASE_URL.'modules/configuracion/index.php'],
    ['label'=>'Estados','url'=>null],
];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="fw-bold mb-0">Estados de Orden de Trabajo</h5>
  <a href="<?= BASE_URL ?>modules/configuracion/index.php" class="btn btn-outline-secondary btn-sm">← Volver</a>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="tr-card">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold"><?= $edit ? 'EDITAR ESTADO' : 'NUEVO ESTADO' ?></h6></div>
      <div class="tr-card-body">
        <form method="POST">
          <input type="hidden" name="action" value="guardar">
          <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
          
          <div class="mb-2">
            <label class="tr-form-label">Código * <?php if($edit): ?><span class="text-muted small">(no editable)</span><?php endif; ?></label>
            <?php if ($edit): ?>
            <input type="text" class="form-control form-control-sm" value="<?= sanitize($edit['codigo']) ?>" disabled/>
            <input type="hidden" name="codigo" value="<?= sanitize($edit['codigo']) ?>"/>
            <?php else: ?>
            <input type="text" name="codigo" class="form-control form-control-sm" required placeholder="ej: en_revision"/>
            <div class="small text-muted">Solo letras, números y guión bajo. Se convertirá a minúsculas.</div>
            <?php endif; ?>
          </div>

          <div class="mb-2">
            <label class="tr-form-label">Nombre visible *</label>
            <input type="text" name="nombre" class="form-control form-control-sm" required value="<?= sanitize($edit['nombre']??'') ?>" placeholder="ej: En revisión"/>
          </div>

          <div class="mb-2">
            <label class="tr-form-label">Color (Bootstrap)</label>
            <select name="color" class="form-select form-select-sm">
              <?php foreach(['primary'=>'Azul','secondary'=>'Gris','success'=>'Verde','danger'=>'Rojo','warning'=>'Amarillo','info'=>'Celeste','dark'=>'Negro'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= ($edit['color']??'')===$k?'selected':'' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-2">
            <label class="tr-form-label">Ícono (Feather)</label>
            <input type="text" name="icono" class="form-control form-control-sm" value="<?= sanitize($edit['icono']??'circle') ?>" placeholder="ej: tool, package, check-circle"/>
            <div class="small text-muted">Ver íconos en: <a href="https://feathericons.com" target="_blank">feathericons.com</a></div>
          </div>

          <div class="mb-3">
            <label class="tr-form-label">Orden de visualización</label>
            <input type="number" name="orden" class="form-control form-control-sm" value="<?= (int)($edit['orden']??0) ?>" min="0"/>
          </div>

          <button type="submit" class="btn btn-primary btn-sm w-100"><?= $edit ? 'Guardar cambios' : 'Crear estado' ?></button>
          <?php if ($edit): ?>
          <a href="<?= BASE_URL ?>modules/configuracion/estados.php" class="btn btn-outline-secondary btn-sm w-100 mt-2">Cancelar</a>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="tr-card">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">ESTADOS REGISTRADOS</h6></div>
      <div class="tr-card-body p-0">
        <table class="tr-table">
          <thead><tr><th>Orden</th><th>Estado</th><th>Código</th><th>Color</th><th>Activo</th><th>Sistema</th><th></th></tr></thead>
          <tbody>
            <?php foreach($estados as $e): ?>
            <tr>
              <td class="text-center"><?= $e['orden'] ?></td>
              <td>
                <span class="badge bg-<?= sanitize($e['color']) ?>">
                  <i data-feather="<?= sanitize($e['icono']) ?>" style="width:12px;height:12px"></i>
                  <?= sanitize($e['nombre']) ?>
                </span>
              </td>
              <td><code class="small"><?= sanitize($e['codigo']) ?></code></td>
              <td class="small"><?= sanitize($e['color']) ?></td>
              <td>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= $e['id'] ?>">
                  <button class="btn btn-xs btn-sm btn-outline-<?= $e['activo']?'success':'secondary' ?> py-0">
                    <?= $e['activo'] ? '✅ Activo' : '⏸ Inactivo' ?>
                  </button>
                </form>
              </td>
              <td><?= $e['sistema'] ? '<span class="badge bg-dark">Sistema</span>' : '—' ?></td>
              <td>
                <div class="btn-group btn-group-sm">
                  <a href="?editar=<?= $e['id'] ?>" class="btn btn-outline-secondary btn-sm py-0">
                    <i data-feather="edit-2" style="width:13px;height:13px"></i>
                  </a>
                  <?php if (!$e['sistema']): ?>
                  <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este estado? Solo si no tiene OTs asociadas.')">
                    <input type="hidden" name="action" value="eliminar">
                    <input type="hidden" name="id" value="<?= $e['id'] ?>">
                    <button class="btn btn-outline-danger btn-sm py-0">
                      <i data-feather="trash-2" style="width:13px;height:13px"></i>
                    </button>
                  </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
