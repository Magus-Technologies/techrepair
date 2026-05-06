<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/sunat.php';
require_once __DIR__ . '/../../includes/sunat/SunatService.php';
requireLogin();
requireRole([ROL_ADMIN, ROL_VENDEDOR]);
$db = getDB();

$accion = $_GET['accion'] ?? 'lista';
$id     = (int)($_GET['id'] ?? 0);

// ─── DESCARGA / VISTA XML ──────────────────────────────────────────
if ($accion === 'xml' && $id) {
    $st = $db->prepare("SELECT sunat_xml, serie_doc, num_doc, tipo_doc FROM ventas WHERE id=?");
    $st->execute([$id]); $r = $st->fetch();
    if (!$r || empty($r['sunat_xml'])) { http_response_code(404); exit('Sin XML'); }
    $name = SunatService::nombreArchivo($r) . '.xml';
    if (isset($_GET['ver'])) {
        header('Content-Type: application/xml; charset=utf-8');
    } else {
        header('Content-Type: application/xml');
        header('Content-Disposition: attachment; filename="'.$name.'"');
    }
    echo $r['sunat_xml']; exit;
}

if ($accion === 'cdr' && $id) {
    $st = $db->prepare("SELECT sunat_cdr, serie_doc, num_doc, tipo_doc FROM ventas WHERE id=?");
    $st->execute([$id]); $r = $st->fetch();
    if (!$r || empty($r['sunat_cdr'])) { http_response_code(404); exit('Sin CDR'); }
    $bin = base64_decode($r['sunat_cdr'], true);
    if ($bin === false) { http_response_code(500); exit('CDR corrupto'); }
    $name = 'R-' . SunatService::nombreArchivo($r) . '.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="'.$name.'"');
    echo $bin; exit;
}

// ─── ACCIONES POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ap = $_POST['action'] ?? '';

    if ($ap === 'emitir') {
        $ventaId = (int)$_POST['venta_id'];
        $tipo    = $_POST['tipo_doc'] ?? '';

        if (!in_array($tipo, ['factura','boleta'], true)) {
            setFlash('danger', 'Tipo de comprobante inválido.');
            redirect(BASE_URL.'modules/facturacion/index.php');
        }

        $venta = $db->prepare("SELECT v.*, c.ruc_dni, c.nombre AS cliente_nombre FROM ventas v LEFT JOIN clientes c ON c.id=v.cliente_id WHERE v.id=?");
        $venta->execute([$ventaId]); $venta = $venta->fetch();
        if (!$venta) { setFlash('danger','Venta no encontrada.'); redirect(BASE_URL.'modules/facturacion/index.php'); }

        if ($venta['cliente_id'] === null) {
            setFlash('danger', 'La venta no tiene cliente asignado. Para emitir comprobante asigna un cliente.');
            redirect(BASE_URL.'modules/ventas/detalle.php?id='.$ventaId);
        }

        $doc = trim($venta['ruc_dni'] ?? '');
        if ($tipo === 'factura' && strlen($doc) !== 11) {
            setFlash('danger', 'El cliente no tiene RUC válido (11 dígitos). Para facturas se requiere RUC.');
            redirect(BASE_URL.'modules/clientes/index.php');
        }
        if ($tipo === 'boleta' && strlen($doc) !== 8 && strlen($doc) !== 11 && $doc !== '') {
            // permitir vacío (CLIENTE VARIOS), DNI 8 o RUC 11; otros formatos se rechazan
            setFlash('danger', 'El DNI del cliente debe tener 8 dígitos.');
            redirect(BASE_URL.'modules/clientes/index.php');
        }

        $serie = $tipo === 'factura' ? SUNAT_SERIE_FACTURA : SUNAT_SERIE_BOLETA;
        $correlativo = SunatService::siguienteNumero($db, $tipo);
        if (!$correlativo) {
            setFlash('danger', "No hay serie activa para '$tipo'. Ve a Admin → Series y activa una.");
            redirect(BASE_URL.'modules/facturacion/index.php');
        }
        $serie  = $correlativo['serie'];
        $numero = $correlativo['numero'];

        $db->prepare("UPDATE ventas SET tipo_doc=?, serie_doc=?, num_doc=?, sunat_estado=NULL, sunat_xml=NULL, sunat_cdr=NULL, sunat_hash=NULL, sunat_qr=NULL, sunat_mensaje=NULL WHERE id=?")
           ->execute([$tipo, $serie, (string)$numero, $ventaId]);

        $r = (new SunatService($db))->generarXml($ventaId);
        if ($r['ok']) setFlash('success', 'Comprobante emitido. ' . $r['mensaje']);
        else          setFlash('danger',  'Falló la emisión: ' . $r['mensaje']);

        redirect(BASE_URL.'modules/facturacion/index.php?accion=ver&id='.$ventaId);
    }

    if ($ap === 'enviar_sunat') {
        $r = (new SunatService($db))->enviarSunat($id);
        if ($r['ok']) setFlash('success', $r['mensaje']);
        else          setFlash('danger',  $r['mensaje']);
        redirect(BASE_URL.'modules/facturacion/index.php?accion=ver&id='.$id);
    }

    if ($ap === 'regenerar') {
        $db->prepare("UPDATE ventas SET sunat_xml=NULL, sunat_cdr=NULL, sunat_hash=NULL, sunat_qr=NULL, sunat_estado=NULL, sunat_mensaje=NULL WHERE id=?")
           ->execute([$id]);
        $r = (new SunatService($db))->generarXml($id);
        if ($r['ok']) setFlash('success', 'XML regenerado. ' . $r['mensaje']);
        else          setFlash('danger',  'Error al regenerar: ' . $r['mensaje']);
        redirect(BASE_URL.'modules/facturacion/index.php?accion=ver&id='.$id);
    }
}

// ─── VISTA: DETALLE ────────────────────────────────────────────────
if ($accion === 'ver' && $id) {
    $venta = $db->prepare("
        SELECT v.*, c.nombre AS cliente_nombre, c.ruc_dni, c.tipo AS cliente_tipo,
               CONCAT(u.nombre,' ',u.apellido) AS vendedor
        FROM ventas v
        LEFT JOIN clientes c ON c.id=v.cliente_id
        JOIN usuarios u ON u.id=v.usuario_id
        WHERE v.id=?");
    $venta->execute([$id]); $venta = $venta->fetch();
    if (!$venta) { setFlash('danger','Venta no encontrada.'); redirect(BASE_URL.'modules/facturacion/index.php'); }

    $detalle = $db->prepare("
        SELECT vd.*, 
               COALESCE(p.nombre, 'Servicio de reparación') AS prod_nombre, 
               COALESCE(p.codigo, CONCAT('SRV-', LPAD(vd.id, 3, '0'))) AS prod_codigo
        FROM venta_detalle vd 
        LEFT JOIN productos p ON p.id=vd.producto_id
        WHERE vd.venta_id=? ORDER BY vd.id");
    $detalle->execute([$id]);
    $detalle = $detalle->fetchAll();

    $serieNum = $venta['serie_doc'] ? $venta['serie_doc'].'-'.str_pad((string)$venta['num_doc'],8,'0',STR_PAD_LEFT) : '—';
    $estBadge = ['aceptado'=>'success','pendiente'=>'warning','rechazado'=>'danger'][$venta['sunat_estado']] ?? 'secondary';
    $estTxt   = $venta['sunat_estado'] ? strtoupper($venta['sunat_estado']) : 'SIN ENVIAR';

    $pageTitle = 'Comprobante '.$serieNum.' — '.APP_NAME;
    $breadcrumb = [
        ['label'=>'Facturación','url'=>BASE_URL.'modules/facturacion/index.php'],
        ['label'=>$serieNum,'url'=>null],
    ];
    require_once __DIR__ . '/../../includes/header.php';
    ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="fw-bold mb-0"><?= sanitize($serieNum) ?></h4>
        <span class="badge bg-<?= $venta['tipo_doc']==='factura'?'primary':'secondary' ?>"><?= strtoupper($venta['tipo_doc']) ?></span>
        <span class="badge bg-<?= $estBadge ?>"><?= $estTxt ?></span>
        <span class="text-muted small ms-2"><?= formatDateTime($venta['created_at']) ?></span>
      </div>
      <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>modules/facturacion/pdf.php?token=<?= urlencode($venta['sunat_hash'] ?: $venta['codigo']) ?>&formato=a4" target="_blank" class="btn btn-outline-danger btn-sm"><i data-feather="file-text" style="width:14px;height:14px"></i> PDF A4</a>
        <a href="<?= BASE_URL ?>modules/facturacion/pdf.php?token=<?= urlencode($venta['sunat_hash'] ?: $venta['codigo']) ?>&formato=ticket" target="_blank" class="btn btn-outline-secondary btn-sm"><i data-feather="printer" style="width:14px;height:14px"></i> Ticket</a>
        <a href="<?= BASE_URL ?>modules/ventas/detalle.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm">Ver venta</a>
        <a href="<?= BASE_URL ?>modules/facturacion/index.php" class="btn btn-outline-secondary btn-sm">← Volver</a>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-lg-8">
        <div class="tr-card mb-3">
          <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">PRODUCTOS</h6></div>
          <div class="tr-card-body p-0">
            <table class="tr-table">
              <thead><tr><th>Código</th><th>Producto</th><th>Cant.</th><th>P. Unit.</th><th>Subtotal</th></tr></thead>
              <tbody>
                <?php foreach($detalle as $d): ?>
                <tr>
                  <td><code class="small"><?= sanitize($d['prod_codigo']) ?></code></td>
                  <td class="fw-semibold"><?= sanitize($d['prod_nombre']) ?></td>
                  <td><?= number_format($d['cantidad'],2) ?></td>
                  <td><?= formatMoney($d['precio_unit']) ?></td>
                  <td class="fw-semibold"><?= formatMoney($d['subtotal']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr class="table-light"><td colspan="3"></td><td class="small text-muted">Subtotal:</td><td class="fw-semibold"><?= formatMoney($venta['subtotal']) ?></td></tr>
                <tr class="table-light"><td colspan="3"></td><td class="small text-muted">IGV:</td><td class="fw-semibold"><?= formatMoney($venta['igv']) ?></td></tr>
                <tr class="table-warning"><td colspan="3"></td><td class="fw-bold">TOTAL:</td><td class="fw-bold fs-5"><?= formatMoney($venta['total']) ?></td></tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="tr-card mb-3">
          <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">CLIENTE</h6></div>
          <div class="tr-card-body">
            <div class="fw-semibold"><?= sanitize($venta['cliente_nombre'] ?? '— sin cliente —') ?></div>
            <div class="small text-muted">
              <?= $venta['cliente_tipo']==='empresa' ? 'RUC' : 'DNI' ?>: <?= sanitize($venta['ruc_dni'] ?? '—') ?>
            </div>
          </div>
        </div>

        <div class="tr-card mb-3">
          <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">SUNAT</h6></div>
          <div class="tr-card-body">
            <?php if ($venta['sunat_mensaje']): ?>
            <div class="alert alert-<?= $venta['sunat_estado']==='aceptado'?'success':($venta['sunat_estado']==='rechazado'?'danger':'warning') ?> py-2 small mb-3">
              <?= sanitize($venta['sunat_mensaje']) ?>
            </div>
            <?php endif; ?>
            <?php if ($venta['sunat_hash']): ?>
            <div class="small text-muted mb-2">Hash:<br><code style="font-size:10px;word-break:break-all"><?= sanitize($venta['sunat_hash']) ?></code></div>
            <?php endif; ?>

            <div class="d-grid gap-2">
              <?php if ($venta['sunat_xml']): ?>
              <a href="?accion=xml&id=<?= $id ?>&ver=1" target="_blank" class="btn btn-outline-secondary btn-sm"><i data-feather="code" style="width:14px;height:14px"></i> Ver XML</a>
              <a href="?accion=xml&id=<?= $id ?>" class="btn btn-outline-secondary btn-sm"><i data-feather="download" style="width:14px;height:14px"></i> Descargar XML</a>
              <?php endif; ?>

              <?php if ($venta['sunat_xml'] && $venta['sunat_estado'] !== 'aceptado'): ?>
              <form method="POST" onsubmit="return confirm('¿Enviar a SUNAT?')">
                <input type="hidden" name="action" value="enviar_sunat">
                <button class="btn btn-primary btn-sm w-100"><i data-feather="send" style="width:14px;height:14px"></i> Enviar a SUNAT</button>
              </form>
              <?php endif; ?>

              <?php if ($venta['sunat_cdr']): ?>
              <a href="?accion=cdr&id=<?= $id ?>" class="btn btn-outline-success btn-sm"><i data-feather="archive" style="width:14px;height:14px"></i> Descargar CDR</a>
              <?php endif; ?>

              <?php if ($venta['serie_doc'] && $venta['sunat_estado'] !== 'aceptado'): ?>
              <form method="POST" onsubmit="return confirm('¿Regenerar XML? Se perderá el actual.')">
                <input type="hidden" name="action" value="regenerar">
                <button class="btn btn-outline-warning btn-sm w-100"><i data-feather="refresh-cw" style="width:14px;height:14px"></i> Regenerar XML</button>
              </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php require_once __DIR__ . '/../../includes/footer.php'; exit; }

// ─── VISTA: LISTA ─────────────────────────────────────────────────
$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$tipo  = $_GET['tipo']  ?? '';
$estSU = $_GET['estado'] ?? '';

// Comprobantes ya emitidos (con serie + tipo factura/boleta)
$where  = "WHERE v.estado='completada' AND v.tipo_doc IN ('factura','boleta') AND v.serie_doc IS NOT NULL AND DATE(v.created_at) BETWEEN ? AND ?";
$params = [$desde, $hasta];
if ($tipo)  { $where .= " AND v.tipo_doc=?"; $params[] = $tipo; }
if ($estSU) { $where .= " AND v.sunat_estado=?"; $params[] = $estSU; }

$emitidos = $db->prepare("
    SELECT v.*, c.nombre AS cliente_nombre, c.ruc_dni
    FROM ventas v LEFT JOIN clientes c ON c.id=v.cliente_id
    $where ORDER BY v.created_at DESC LIMIT 300");
$emitidos->execute($params);
$emitidos = $emitidos->fetchAll();

// Ventas elegibles para emitir (boleta/factura sin serie todavía, o sin_comprobante)
$emisibles = $db->prepare("
    SELECT v.*, c.nombre AS cliente_nombre, c.ruc_dni, c.tipo AS cliente_tipo
    FROM ventas v LEFT JOIN clientes c ON c.id=v.cliente_id
    WHERE v.estado='completada'
      AND DATE(v.created_at) BETWEEN ? AND ?
      AND v.serie_doc IS NULL
      AND v.cliente_id IS NOT NULL
    ORDER BY v.created_at DESC LIMIT 100");
$emisibles->execute([$desde, $hasta]);
$emisibles = $emisibles->fetchAll();

// KPIs
$totalEmitidos   = count($emitidos);
$totalAceptados  = 0; $totalPendientes = 0; $totalRechazados = 0;
$montoTotal = 0;
foreach ($emitidos as $r) {
    $montoTotal += $r['total'];
    if ($r['sunat_estado']==='aceptado')      $totalAceptados++;
    elseif ($r['sunat_estado']==='pendiente') $totalPendientes++;
    elseif ($r['sunat_estado']==='rechazado') $totalRechazados++;
}

$pageTitle  = 'Facturación electrónica — '.APP_NAME;
$breadcrumb = [['label'=>'Facturación','url'=>null]];
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="fw-bold mb-0">Facturación electrónica SUNAT</h5>
  <a href="<?= BASE_URL ?>modules/facturacion/series.php" class="btn btn-outline-secondary btn-sm">⚙️ Admin Series</a>
</div>

<div class="tr-card mb-3">
  <div class="tr-card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-2"><label class="small text-muted">Desde</label><input type="date" name="desde" class="form-control form-control-sm" value="<?= $desde ?>"/></div>
      <div class="col-md-2"><label class="small text-muted">Hasta</label><input type="date" name="hasta" class="form-control form-control-sm" value="<?= $hasta ?>"/></div>
      <div class="col-md-2"><label class="small text-muted">Tipo</label>
        <select name="tipo" class="form-select form-select-sm">
          <option value="">Todos</option>
          <option value="factura" <?= $tipo==='factura'?'selected':'' ?>>Factura</option>
          <option value="boleta"  <?= $tipo==='boleta' ?'selected':'' ?>>Boleta</option>
        </select>
      </div>
      <div class="col-md-2"><label class="small text-muted">Estado SUNAT</label>
        <select name="estado" class="form-select form-select-sm">
          <option value="">Todos</option>
          <option value="pendiente" <?= $estSU==='pendiente'?'selected':'' ?>>Pendiente</option>
          <option value="aceptado"  <?= $estSU==='aceptado' ?'selected':'' ?>>Aceptado</option>
          <option value="rechazado" <?= $estSU==='rechazado'?'selected':'' ?>>Rechazado</option>
        </select>
      </div>
      <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">Filtrar</button></div>
    </form>
  </div>
</div>

<!-- KPIs -->
<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="tr-card"><div class="tr-card-body"><div class="small text-muted">Comprobantes</div><div class="h4 fw-bold mb-0"><?= $totalEmitidos ?></div></div></div></div>
  <div class="col-md-3"><div class="tr-card"><div class="tr-card-body"><div class="small text-muted">Aceptados</div><div class="h4 fw-bold mb-0 text-success"><?= $totalAceptados ?></div></div></div></div>
  <div class="col-md-3"><div class="tr-card"><div class="tr-card-body"><div class="small text-muted">Pendientes</div><div class="h4 fw-bold mb-0 text-warning"><?= $totalPendientes ?></div></div></div></div>
  <div class="col-md-3"><div class="tr-card"><div class="tr-card-body"><div class="small text-muted">Total facturado</div><div class="h5 fw-bold mb-0"><?= formatMoney($montoTotal) ?></div></div></div></div>
</div>

<!-- Ventas elegibles para emitir -->
<?php if ($emisibles): ?>
<div class="tr-card mb-3">
  <div class="tr-card-header d-flex justify-content-between align-items-center">
    <h6 class="mb-0 small fw-semibold">VENTAS DISPONIBLES PARA EMITIR COMPROBANTE</h6>
    <span class="badge bg-secondary"><?= count($emisibles) ?></span>
  </div>
  <div class="tr-card-body p-0"><div style="overflow-x:auto">
    <table class="tr-table">
      <thead><tr><th>Código</th><th>Cliente</th><th>Doc.</th><th>Total</th><th>Fecha</th><th class="text-end">Emitir</th></tr></thead>
      <tbody>
        <?php foreach($emisibles as $v):
          $doc = trim($v['ruc_dni'] ?? '');
          $puedeBoleta = strlen($doc) === 8 || $doc === '';
          $puedeFactura = strlen($doc) === 11;
        ?>
        <tr>
          <td><a href="<?= BASE_URL ?>modules/ventas/detalle.php?id=<?= $v['id'] ?>" class="fw-semibold small text-primary"><?= sanitize($v['codigo']) ?></a></td>
          <td class="small"><?= sanitize($v['cliente_nombre'] ?? '—') ?></td>
          <td class="small text-muted">
            <?php if (strlen($doc)===11): ?>RUC: <?= sanitize($doc) ?>
            <?php elseif (strlen($doc)===8): ?>DNI: <?= sanitize($doc) ?>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td class="fw-bold"><?= formatMoney($v['total']) ?></td>
          <td class="small text-muted"><?= formatDateTime($v['created_at']) ?></td>
          <td class="text-end">
            <form method="POST" class="d-inline" onsubmit="return confirm('¿Emitir comprobante?')">
              <input type="hidden" name="action" value="emitir">
              <input type="hidden" name="venta_id" value="<?= $v['id'] ?>">
              <div class="btn-group btn-group-sm">
                <button type="submit" name="tipo_doc" value="boleta" class="btn btn-outline-secondary" <?= !$puedeBoleta?'disabled title="Sin DNI válido"':'' ?>>Boleta</button>
                <button type="submit" name="tipo_doc" value="factura" class="btn btn-outline-primary" <?= !$puedeFactura?'disabled title="Sin RUC válido"':'' ?>>Factura</button>
              </div>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div></div>
</div>
<?php endif; ?>

<!-- Comprobantes emitidos -->
<div class="tr-card">
  <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">COMPROBANTES EMITIDOS</h6></div>
  <div class="tr-card-body p-0"><div style="overflow-x:auto">
    <table class="tr-table">
      <thead><tr><th>Comprobante</th><th>Cliente</th><th>Doc.</th><th>Total</th><th>SUNAT</th><th>Fecha</th><th></th></tr></thead>
      <tbody>
        <?php foreach($emitidos as $r):
          $estB = ['aceptado'=>'success','pendiente'=>'warning','rechazado'=>'danger'][$r['sunat_estado']] ?? 'secondary';
          $estT = $r['sunat_estado'] ? strtoupper($r['sunat_estado']) : 'SIN ENVIAR';
        ?>
        <tr>
          <td>
            <span class="badge bg-<?= $r['tipo_doc']==='factura'?'primary':'secondary' ?>"><?= strtoupper($r['tipo_doc']) ?></span>
            <span class="fw-semibold ms-1"><?= sanitize($r['serie_doc']) ?>-<?= str_pad((string)$r['num_doc'],8,'0',STR_PAD_LEFT) ?></span>
          </td>
          <td class="small"><?= sanitize($r['cliente_nombre'] ?? '—') ?></td>
          <td class="small text-muted"><?= sanitize($r['ruc_dni'] ?? '—') ?></td>
          <td class="fw-bold"><?= formatMoney($r['total']) ?></td>
          <td><span class="badge bg-<?= $estB ?>"><?= $estT ?></span></td>
          <td class="small text-muted"><?= formatDateTime($r['created_at']) ?></td>
          <td><a href="?accion=ver&id=<?= $r['id'] ?>" class="btn btn-outline-primary btn-sm"><i data-feather="eye" style="width:13px;height:13px"></i></a></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($emitidos)): ?><tr><td colspan="7" class="text-center text-muted py-4">Sin comprobantes en el período</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div></div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
