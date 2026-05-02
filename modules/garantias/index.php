<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
requireLogin();
$db  = getDB();
$hoy = date('Y-m-d');

// Actualizar estado vencidas
$db->prepare("UPDATE garantias SET estado='vencida' WHERE fecha_vence < ? AND estado='vigente'")->execute([$hoy]);

$garantias = $db->query("
  SELECT g.*, c.nombre as cliente_nombre, c.telefono, c.whatsapp
  FROM garantias g JOIN clientes c ON c.id=g.cliente_id
  ORDER BY g.estado, g.fecha_vence
")->fetchAll();

$pageTitle  = 'Garantías — '.APP_NAME;
$breadcrumb = [['label'=>'Garantías','url'=>null]];
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="fw-bold mb-0">Control de garantías</h5>
</div>
<div class="tr-card">
  <div class="tr-card-body p-0" style="overflow:hidden"><div class="table-responsive-wrapper" style="overflow-x:auto;-webkit-overflow-scrolling:touch">
    <table class="tr-table">
      <thead><tr><th>Tipo</th><th>Cliente</th><th>Descripción</th><th>Inicio</th><th>Vence</th><th>Estado</th><th>Días restantes</th></tr></thead>
      <tbody>
        <?php foreach($garantias as $g):
          $diasRest = (int)ceil((strtotime($g['fecha_vence'])-time())/86400);
          $bc = $g['estado']==='vigente'?'success':($g['estado']==='reclamada'?'warning':'secondary');
        ?>
        <tr>
          <td><span class="badge bg-<?= $g['tipo']==='reparacion'?'primary':'info' ?>"><?= ucfirst($g['tipo']) ?></span></td>
          <td>
            <div class="fw-semibold small"><?= sanitize($g['cliente_nombre']) ?></div>
            <?php if($g['telefono']): ?><div class="text-muted" style="font-size:11px">📞 <?= sanitize($g['telefono']) ?></div><?php endif; ?>
          </td>
          <td class="small"><?= sanitize($g['descripcion']) ?></td>
          <td class="small text-muted"><?= formatDate($g['fecha_inicio']) ?></td>
          <td class="small <?= $g['estado']==='vencida'?'text-danger':'' ?>"><?= formatDate($g['fecha_vence']) ?></td>
          <td><span class="badge bg-<?= $bc ?>"><?= ucfirst($g['estado']) ?></span></td>
          <td class="fw-bold <?= $diasRest<=0?'text-danger':($diasRest<=7?'text-warning':'text-success') ?>">
            <?= $diasRest>0?$diasRest.' días':'Vencida' ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($garantias)): ?><tr><td colspan="7" class="text-center text-muted py-4">Sin garantías registradas</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
