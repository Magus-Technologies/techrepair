<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
requireLogin();

$db = getDB();

// Filtros
$f_estado   = $_GET['estado']   ?? '';
$f_tecnico  = $_GET['tecnico']  ?? '';
$f_buscar   = trim($_GET['q']   ?? '');
$f_desde    = $_GET['desde']    ?? '';
$f_hasta    = $_GET['hasta']    ?? '';

$where  = [];
$params = [];

if ($f_estado)  { $where[] = 'ot.estado = ?';          $params[] = $f_estado; }
if ($f_tecnico) { $where[] = 'ot.tecnico_id = ?';       $params[] = $f_tecnico; }
if ($f_desde)   { $where[] = 'DATE(ot.created_at) >= ?';$params[] = $f_desde; }
if ($f_hasta)   { $where[] = 'DATE(ot.created_at) <= ?';$params[] = $f_hasta; }
if ($f_buscar)  {
    $where[] = '(ot.codigo_ot LIKE ? OR c.nombre LIKE ? OR ot.codigo_publico LIKE ?)';
    $like = '%' . $f_buscar . '%';
    $params = array_merge($params, [$like, $like, $like]);
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$ots = $db->prepare("
  SELECT ot.*, c.nombre as cliente_nombre, c.telefono as cliente_tel,
         te.nombre as tipo_equipo, e.marca, e.modelo,
         CONCAT(u.nombre,' ',u.apellido) as tecnico_nombre
  FROM ordenes_trabajo ot
  JOIN clientes c    ON c.id  = ot.cliente_id
  JOIN equipos e     ON e.id  = ot.equipo_id
  JOIN tipos_equipo te ON te.id = e.tipo_equipo_id
  LEFT JOIN usuarios u ON u.id = ot.tecnico_id
  $whereSQL
  " . ($whereSQL ? 'AND' : 'WHERE') . " ot.deleted_at IS NULL
  ORDER BY ot.created_at DESC
  LIMIT 200
");
$ots->execute($params);
$lista = $ots->fetchAll();

// Para el filtro de técnicos
$tecnicos = $db->query("SELECT id,CONCAT(nombre,' ',apellido) as nombre FROM usuarios WHERE rol='tecnico' AND activo=1")->fetchAll();

// Cargar estados desde BD
$estadosOT = getEstadosOT($db, true);

// Inicializar cache de estadoOTBadge
estadoOTBadge('', $db);

$pageTitle  = 'Órdenes de trabajo — ' . APP_NAME;
$breadcrumb = [['label'=>'Órdenes de trabajo','url'=>null]];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="fw-bold mb-0">Órdenes de trabajo</h5>
  <a href="<?= BASE_URL ?>modules/ot/nueva.php" class="btn btn-primary">
    <i data-feather="plus" style="width:16px;height:16px"></i> Nueva OT
  </a>
</div>

<!-- Filtros -->
<div class="tr-card mb-3">
  <div class="tr-card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-3">
        <input type="text" name="q" class="form-control form-control-sm"
               placeholder="Buscar código, cliente..." value="<?= sanitize($f_buscar) ?>"/>
      </div>
      <div class="col-md-2">
        <select name="estado" class="form-select form-select-sm">
          <option value="">Todos los estados</option>
          <?php foreach ($estadosOT as $k => $v): ?>
          <option value="<?= $k ?>" <?= $f_estado===$k?'selected':'' ?>><?= $v['label'] ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="tecnico" class="form-select form-select-sm">
          <option value="">Todos los técnicos</option>
          <?php foreach ($tecnicos as $t): ?>
          <option value="<?= $t['id'] ?>" <?= $f_tecnico==$t['id']?'selected':'' ?>><?= sanitize($t['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <input type="date" name="desde" class="form-control form-control-sm" value="<?= sanitize($f_desde) ?>"/>
      </div>
      <div class="col-md-2">
        <input type="date" name="hasta" class="form-control form-control-sm" value="<?= sanitize($f_hasta) ?>"/>
      </div>
      <div class="col-md-1">
        <button type="submit" class="btn btn-primary btn-sm w-100">Filtrar</button>
      </div>
    </form>
  </div>
</div>

<!-- Tabla -->
<div class="tr-card">
  <div class="tr-card-body p-0" style="overflow:hidden"><div class="table-responsive-wrapper" style="overflow-x:auto;-webkit-overflow-scrolling:touch">
    <table class="tr-table" id="tabla-ots">
      <thead>
        <tr>
          <th>OT</th>
          <th>Cliente</th>
          <th>Equipo</th>
          <th>Técnico</th>
          <th>Estado</th>
          <th>F. Estimada</th>
          <th>Total</th>
          <th>Ingreso</th>
          <th style="width:90px">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($lista)): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">No se encontraron órdenes</td></tr>
        <?php else: ?>
        <?php foreach ($lista as $ot): ?>
        <tr>
          <td>
            <a href="<?= BASE_URL ?>modules/ot/ver.php?id=<?= $ot['id'] ?>" class="fw-semibold text-primary text-decoration-none">
              <?= sanitize($ot['codigo_ot']) ?>
            </a>
            <div class="text-muted" style="font-size:10px"><?= sanitize($ot['codigo_publico']) ?></div>
          </td>
          <td>
            <?= sanitize($ot['cliente_nombre']) ?>
            <?php if ($ot['cliente_tel']): ?>
            <div class="text-muted" style="font-size:11px"><?= sanitize($ot['cliente_tel']) ?></div>
            <?php endif; ?>
          </td>
          <td class="small">
            <?= sanitize($ot['tipo_equipo']) ?>
            <?php if ($ot['marca'] || $ot['modelo']): ?>
            <div class="text-muted"><?= sanitize(trim($ot['marca'].' '.$ot['modelo'])) ?></div>
            <?php endif; ?>
          </td>
          <td class="small"><?= sanitize($ot['tecnico_nombre'] ?? '—') ?></td>
          <td><?= estadoOTBadge($ot['estado']) ?></td>
          <td class="small <?= ($ot['fecha_estimada'] && $ot['fecha_estimada'] < date('Y-m-d') && !in_array($ot['estado'],['listo','entregado','cancelado'])) ? 'text-danger fw-semibold' : '' ?>">
            <?= $ot['fecha_estimada'] ? formatDate($ot['fecha_estimada']) : '—' ?>
          </td>
          <td class="fw-semibold"><?= $ot['precio_final'] > 0 ? formatMoney($ot['precio_final']) : '—' ?></td>
          <td class="text-muted small"><?= formatDate($ot['created_at']) ?></td>
          <td>
            <div class="btn-group btn-group-sm">
              <a href="<?= BASE_URL ?>modules/ot/ver.php?id=<?= $ot['id'] ?>"
                 class="btn btn-outline-primary" title="Ver detalle" data-bs-toggle="tooltip">
                <i data-feather="eye" style="width:13px;height:13px"></i>
              </a>
              <a href="<?= BASE_URL ?>modules/ot/editar.php?id=<?= $ot['id'] ?>"
                 class="btn btn-outline-secondary" title="Editar" data-bs-toggle="tooltip">
                <i data-feather="edit-2" style="width:13px;height:13px"></i>
              </a>
              <a href="<?= BASE_URL ?>modules/ot/pdf.php?id=<?= $ot['id'] ?>"
                 class="btn btn-outline-danger" title="PDF" target="_blank" data-bs-toggle="tooltip">
                <i data-feather="file-text" style="width:13px;height:13px"></i>
              </a>
              <button type="button" class="btn btn-outline-danger" title="Eliminar" data-bs-toggle="tooltip"
                      onclick="confirmarEliminar(<?= $ot['id'] ?>, '<?= sanitize($ot['codigo_ot']) ?>')">
                <i data-feather="trash-2" style="width:13px;height:13px"></i>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal de confirmación para eliminar -->
<div class="modal fade" id="modalEliminarOT" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Confirmar eliminación</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">¿Estás seguro de que deseas eliminar esta orden de trabajo?</p>
        <p class="fw-bold mb-0" id="otEliminarCodigo"></p>
        <p class="text-muted small mt-2 mb-0">Esta acción no se puede deshacer.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <form method="POST" action="<?= BASE_URL ?>modules/ot/eliminar.php" id="formEliminarOT" style="display:inline">
          <input type="hidden" name="id" id="otEliminarId">
          <button type="submit" class="btn btn-danger">Eliminar OT</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function confirmarEliminar(id, codigo) {
  document.getElementById('otEliminarId').value = id;
  document.getElementById('otEliminarCodigo').textContent = codigo;
  const modal = new bootstrap.Modal(document.getElementById('modalEliminarOT'));
  modal.show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
