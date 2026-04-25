<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
requireLogin();
$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$cliente = $db->prepare("SELECT * FROM clientes WHERE id=? AND activo=1");
$cliente->execute([$id]);
$cliente = $cliente->fetch();
if (!$cliente) { setFlash('danger','Cliente no encontrado'); redirect(BASE_URL.'modules/clientes/index.php'); }

$ots = $db->prepare("
  SELECT ot.*, te.nombre as tipo_equipo, e.marca, e.modelo
  FROM ordenes_trabajo ot
  JOIN equipos e ON e.id=ot.equipo_id
  JOIN tipos_equipo te ON te.id=e.tipo_equipo_id
  WHERE ot.cliente_id=? ORDER BY ot.created_at DESC");
$ots->execute([$id]);
$ots = $ots->fetchAll();

$ventas = $db->prepare("SELECT v.*, COUNT(vd.id) as items FROM ventas v LEFT JOIN venta_detalle vd ON vd.venta_id=v.id WHERE v.cliente_id=? GROUP BY v.id ORDER BY v.created_at DESC LIMIT 20");
$ventas->execute([$id]);
$ventas = $ventas->fetchAll();

$totalGastado = array_sum(array_column(array_filter($ots, fn($o)=>$o['pagado']), 'precio_final'))
              + array_sum(array_column(array_filter($ventas, fn($v)=>$v['estado']==='completada'), 'total'));

$pageTitle  = $cliente['nombre'].' — '.APP_NAME;
$breadcrumb = [['label'=>'Clientes','url'=>BASE_URL.'modules/clientes/index.php'],['label'=>$cliente['nombre'],'url'=>null]];
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0"><?= sanitize($cliente['nombre']) ?></h4>
    <span class="badge bg-secondary"><?= sanitize($cliente['codigo']) ?></span>
    <?php $sbadge=['nuevo'=>'secondary','frecuente'=>'primary','empresa'=>'info','vip'=>'warning'];
    echo '<span class="badge bg-'.($sbadge[$cliente['segmento']]??'secondary').' ms-1">'.ucfirst($cliente['segmento']).'</span>'; ?>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>modules/ot/nueva.php?cliente_id=<?= $id ?>" class="btn btn-primary btn-sm">
      <i data-feather="plus" style="width:14px;height:14px"></i> Nueva OT
    </a>
    <a href="<?= BASE_URL ?>modules/whatsapp/index.php" class="btn btn-success btn-sm"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" class="me-1"><path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.898 7.898 0 0 0 13.6 2.326zM7.994 14.521a6.573 6.573 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.557 6.557 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592zm3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.729.729 0 0 0-.529.247c-.182.198-.691.677-.691 1.654 0 .977.71 1.916.81 2.049.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232z"/></svg> WhatsApp</a>
    <a href="<?= BASE_URL ?>modules/clientes/editar.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm">
      <i data-feather="edit-2" style="width:14px;height:14px"></i> Editar
    </a>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="kpi-card">
      <div class="kpi-value"><?= count($ots) ?></div>
      <div class="kpi-label">Reparaciones totales</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="kpi-card">
      <div class="kpi-value"><?= count($ventas) ?></div>
      <div class="kpi-label">Compras realizadas</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="kpi-card">
      <div class="kpi-value" style="font-size:20px"><?= formatMoney($totalGastado) ?></div>
      <div class="kpi-label">Gasto total</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="kpi-card">
      <div class="kpi-value text-muted small mt-1">
        <?php if($cliente['telefono']): ?><div>📞 <?= sanitize($cliente['telefono']) ?></div><?php endif; ?>
        <?php if($cliente['whatsapp']): ?><div>💬 <?= sanitize($cliente['whatsapp']) ?></div><?php endif; ?>
        <?php if($cliente['email']): ?><div style="font-size:11px">✉️ <?= sanitize($cliente['email']) ?></div><?php endif; ?>
      </div>
      <div class="kpi-label">Contacto</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <!-- Historial reparaciones -->
    <div class="tr-card mb-3">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">HISTORIAL DE REPARACIONES</h6></div>
      <div class="tr-card-body p-0">
        <?php if(empty($ots)): ?>
        <p class="text-center text-muted py-3">Sin reparaciones registradas</p>
        <?php else: ?>
        <table class="tr-table">
          <thead><tr><th>OT</th><th>Equipo</th><th>Estado</th><th>Total</th><th>Fecha</th><th></th></tr></thead>
          <tbody>
            <?php foreach($ots as $ot): ?>
            <tr>
              <td><a href="<?= BASE_URL ?>modules/ot/ver.php?id=<?= $ot['id'] ?>" class="fw-semibold text-primary text-decoration-none small"><?= sanitize($ot['codigo_ot']) ?></a></td>
              <td class="small"><?= sanitize($ot['tipo_equipo'].' '.$ot['marca'].' '.$ot['modelo']) ?></td>
              <td><?= estadoOTBadge($ot['estado']) ?></td>
              <td><?= $ot['precio_final']>0?formatMoney($ot['precio_final']):'—' ?></td>
              <td class="text-muted small"><?= formatDate($ot['created_at']) ?></td>
              <td><a href="<?= BASE_URL ?>modules/ot/ver.php?id=<?= $ot['id'] ?>" class="btn btn-xs btn-outline-primary btn-sm py-0">Ver</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
    <!-- Historial compras -->
    <div class="tr-card">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">HISTORIAL DE COMPRAS</h6></div>
      <div class="tr-card-body p-0">
        <?php if(empty($ventas)): ?>
        <p class="text-center text-muted py-3">Sin compras registradas</p>
        <?php else: ?>
        <table class="tr-table">
          <thead><tr><th>Código</th><th>Items</th><th>Total</th><th>Método</th><th>Fecha</th></tr></thead>
          <tbody>
            <?php foreach($ventas as $v): ?>
            <tr>
              <td class="small fw-semibold"><?= sanitize($v['codigo']) ?></td>
              <td class="text-center small"><?= $v['items'] ?></td>
              <td class="fw-semibold"><?= formatMoney($v['total']) ?></td>
              <td class="small"><?= ucfirst($v['metodo_pago']) ?></td>
              <td class="text-muted small"><?= formatDate($v['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="tr-card">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">DATOS DEL CLIENTE</h6></div>
      <div class="tr-card-body">
        <?php $fields = ['nombre'=>'Nombre','ruc_dni'=>'DNI/RUC','tipo'=>'Tipo','email'=>'Email','telefono'=>'Teléfono','whatsapp'=>'WhatsApp','direccion'=>'Dirección','distrito'=>'Distrito','created_at'=>'Registrado'];
        foreach($fields as $k=>$l): if(!$cliente[$k]) continue; ?>
        <div class="d-flex justify-content-between small mb-2 pb-2 border-bottom">
          <span class="text-muted"><?= $l ?></span>
          <span class="fw-semibold text-end"><?= $k==='created_at'?formatDate($cliente[$k]):sanitize($cliente[$k]) ?></span>
        </div>
        <?php endforeach; ?>
        <?php if($cliente['notas']): ?>
        <div class="bg-light rounded p-2 small mt-2"><?= nl2br(sanitize($cliente['notas'])) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
