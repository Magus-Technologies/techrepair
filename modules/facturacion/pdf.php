<?php
/**
 * PDF público de comprobante — sin login, acceso por token.
 * URL: /modules/facturacion/pdf.php?token=XYZ&formato=a4|ticket
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/sunat.php';

$token   = trim($_GET['token'] ?? '');
$formato = $_GET['formato'] ?? 'a4'; // 'a4' | 'ticket'

if (!$token) { http_response_code(400); exit('Token requerido.'); }

$db = getDB();

$st = $db->prepare("
    SELECT v.*, c.nombre AS cliente_nombre, c.ruc_dni, c.tipo AS cliente_tipo,
           c.direccion AS cliente_dir
    FROM ventas v
    LEFT JOIN clientes c ON c.id = v.cliente_id
    WHERE v.codigo = ? OR v.sunat_hash = ?
    LIMIT 1
");
$st->execute([$token, $token]);
$venta = $st->fetch();

if (!$venta || empty($venta['serie_doc'])) {
    http_response_code(404);
    exit('Comprobante no encontrado.');
}

$detalle = $db->prepare("
    SELECT vd.*, p.nombre AS prod_nombre, p.codigo AS prod_codigo
    FROM venta_detalle vd
    JOIN productos p ON p.id = vd.producto_id
    WHERE vd.venta_id = ? ORDER BY vd.id
");
$detalle->execute([$venta['id']]);
$items = $detalle->fetchAll();

$serieNum = $venta['serie_doc'] . '-' . str_pad((string)$venta['num_doc'], 8, '0', STR_PAD_LEFT);
$tipoLabel = strtoupper($venta['tipo_doc']);

// QR SUNAT: RUC|TIPO|SERIE|NUMERO|IGV|TOTAL|FECHA|TIPO_DOC_CLIENTE|NUM_DOC_CLIENTE|HASH
$tipoDocNum = strlen($venta['ruc_dni'] ?? '') === 11 ? '6' : (strlen($venta['ruc_dni'] ?? '') === 8 ? '1' : '0');
$qrData = implode('|', [
    SUNAT_RUC,
    $venta['tipo_doc'] === 'factura' ? '01' : '03',
    $venta['serie_doc'],
    $venta['num_doc'],
    number_format((float)$venta['igv'], 2, '.', ''),
    number_format((float)$venta['total'], 2, '.', ''),
    date('Y-m-d', strtotime($venta['created_at'])),
    $tipoDocNum,
    $venta['ruc_dni'] ?? '00000000',
    $venta['sunat_hash'] ?? '',
]);

$isTicket = $formato === 'ticket';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/>
<title><?= htmlspecialchars($tipoLabel . ' ' . $serieNum) ?></title>
<style>
<?php if ($isTicket): ?>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: monospace; font-size: 11px; width: 80mm; padding: 4px; }
.center { text-align: center; }
.bold { font-weight: bold; }
.line { border-top: 1px dashed #000; margin: 4px 0; }
table { width: 100%; border-collapse: collapse; font-size: 10px; }
td { padding: 1px 2px; vertical-align: top; }
.right { text-align: right; }
.total-row td { font-weight: bold; border-top: 1px solid #000; }
<?php else: ?>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: Arial, sans-serif; font-size: 12px; padding: 20px; color: #333; }
.header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
.empresa { flex: 1; }
.empresa h2 { font-size: 18px; margin-bottom: 4px; }
.comprobante-box { border: 2px solid #333; padding: 10px 15px; text-align: center; min-width: 180px; }
.comprobante-box .tipo { font-size: 14px; font-weight: bold; }
.comprobante-box .serie { font-size: 16px; font-weight: bold; margin-top: 4px; }
.section { margin-bottom: 15px; }
.section-title { font-weight: bold; border-bottom: 1px solid #ccc; padding-bottom: 3px; margin-bottom: 8px; font-size: 11px; text-transform: uppercase; }
table { width: 100%; border-collapse: collapse; }
th { background: #f5f5f5; padding: 6px 8px; text-align: left; font-size: 11px; border: 1px solid #ddd; }
td { padding: 5px 8px; border: 1px solid #ddd; font-size: 11px; }
.right { text-align: right; }
.totales { margin-left: auto; width: 250px; }
.totales td { border: none; padding: 3px 8px; }
.totales .total-final { font-weight: bold; font-size: 14px; border-top: 2px solid #333; }
.footer { margin-top: 20px; text-align: center; font-size: 10px; color: #666; }
.qr-section { text-align: center; margin-top: 15px; }
<?php endif; ?>
</style>
</head>
<body>
<?php if ($isTicket): ?>
<div class="center bold"><?= htmlspecialchars(SUNAT_NOMBRE_COMERCIAL) ?></div>
<div class="center"><?= htmlspecialchars(SUNAT_RAZON_SOCIAL) ?></div>
<div class="center">RUC: <?= SUNAT_RUC ?></div>
<div class="center"><?= htmlspecialchars(SUNAT_DIRECCION) ?></div>
<div class="line"></div>
<div class="center bold"><?= $tipoLabel ?></div>
<div class="center bold"><?= htmlspecialchars($serieNum) ?></div>
<div class="line"></div>
<div>Cliente: <?= htmlspecialchars($venta['cliente_nombre'] ?? 'CLIENTE VARIOS') ?></div>
<div><?= strlen($venta['ruc_dni']??'')===11?'RUC':'DNI' ?>: <?= htmlspecialchars($venta['ruc_dni'] ?? '—') ?></div>
<div>Fecha: <?= date('d/m/Y H:i', strtotime($venta['created_at'])) ?></div>
<div class="line"></div>
<table>
  <tr><td class="bold">Descripción</td><td class="right bold">Cant</td><td class="right bold">P.U.</td><td class="right bold">Total</td></tr>
  <?php foreach($items as $it): ?>
  <tr>
    <td><?= htmlspecialchars($it['prod_nombre']) ?></td>
    <td class="right"><?= number_format($it['cantidad'],2) ?></td>
    <td class="right"><?= number_format($it['precio_unit'],2) ?></td>
    <td class="right"><?= number_format($it['subtotal'],2) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<div class="line"></div>
<div class="right">Subtotal: S/ <?= number_format($venta['subtotal'],2) ?></div>
<div class="right">IGV (18%): S/ <?= number_format($venta['igv'],2) ?></div>
<div class="right bold">TOTAL: S/ <?= number_format($venta['total'],2) ?></div>
<div class="line"></div>
<div class="center">Representación impresa del comprobante electrónico</div>
<div class="center">Consulte en: <?= SUNAT_RUC ?></div>

<?php else: ?>
<div class="header">
  <div class="empresa">
    <h2><?= htmlspecialchars(SUNAT_NOMBRE_COMERCIAL) ?></h2>
    <div><?= htmlspecialchars(SUNAT_RAZON_SOCIAL) ?></div>
    <div>RUC: <?= SUNAT_RUC ?></div>
    <div><?= htmlspecialchars(SUNAT_DIRECCION) ?></div>
  </div>
  <div class="comprobante-box">
    <div class="tipo"><?= $tipoLabel ?></div>
    <div class="serie"><?= htmlspecialchars($serieNum) ?></div>
    <div style="font-size:10px;margin-top:4px"><?= date('d/m/Y', strtotime($venta['created_at'])) ?></div>
  </div>
</div>

<div class="section">
  <div class="section-title">Datos del cliente</div>
  <table style="border:none">
    <tr><td style="border:none;width:120px;font-weight:bold">Cliente:</td><td style="border:none"><?= htmlspecialchars($venta['cliente_nombre'] ?? 'CLIENTE VARIOS') ?></td></tr>
    <tr><td style="border:none;font-weight:bold"><?= strlen($venta['ruc_dni']??'')===11?'RUC':'DNI' ?>:</td><td style="border:none"><?= htmlspecialchars($venta['ruc_dni'] ?? '—') ?></td></tr>
    <tr><td style="border:none;font-weight:bold">Dirección:</td><td style="border:none"><?= htmlspecialchars($venta['cliente_dir'] ?? '—') ?></td></tr>
    <tr><td style="border:none;font-weight:bold">Fecha:</td><td style="border:none"><?= date('d/m/Y H:i', strtotime($venta['created_at'])) ?></td></tr>
    <tr><td style="border:none;font-weight:bold">Moneda:</td><td style="border:none">SOLES (PEN)</td></tr>
  </table>
</div>

<div class="section">
  <div class="section-title">Detalle</div>
  <table>
    <thead><tr><th>Código</th><th>Descripción</th><th class="right">Cant.</th><th class="right">P. Unit.</th><th class="right">Subtotal</th></tr></thead>
    <tbody>
      <?php foreach($items as $it): ?>
      <tr>
        <td><?= htmlspecialchars($it['prod_codigo']) ?></td>
        <td><?= htmlspecialchars($it['prod_nombre']) ?></td>
        <td class="right"><?= number_format($it['cantidad'],2) ?></td>
        <td class="right">S/ <?= number_format($it['precio_unit'],2) ?></td>
        <td class="right">S/ <?= number_format($it['subtotal'],2) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<table class="totales">
  <tr><td>Subtotal:</td><td class="right">S/ <?= number_format($venta['subtotal'],2) ?></td></tr>
  <tr><td>IGV (18%):</td><td class="right">S/ <?= number_format($venta['igv'],2) ?></td></tr>
  <tr class="total-final"><td>TOTAL:</td><td class="right">S/ <?= number_format($venta['total'],2) ?></td></tr>
</table>

<div class="footer">
  <p>Representación impresa del comprobante electrónico. Consulte en www.sunat.gob.pe</p>
  <?php if ($venta['sunat_hash']): ?>
  <p style="font-size:9px;word-break:break-all">Hash: <?= htmlspecialchars($venta['sunat_hash']) ?></p>
  <?php endif; ?>
</div>
<?php endif; ?>
</body>
</html>
