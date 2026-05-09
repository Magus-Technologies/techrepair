<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
if (!isLoggedIn()) { die('Not logged in'); }
$db = getDB();

// Get last caja id
$caja = $db->query("SELECT id, fecha FROM cajas ORDER BY id DESC LIMIT 1")->fetch();
if (!$caja) { die('No cajas found'); }

// Get sample movimientos for that caja
$movs = $db->prepare("SELECT referencia, tipo, concepto FROM movimientos_caja WHERE caja_id=? LIMIT 5");
$movs->execute([$caja['id']]);
$movs = $movs->fetchAll();

echo "<pre>";
echo "Caja ID: " . $caja['id'] . " fecha: " . $caja['fecha'] . "\n";
echo "Movimientos:\n";
foreach ($movs as $m) {
    echo "  tipo={$m['tipo']} ref={$m['referencia']} concepto={$m['concepto']}\n";
}
echo "</pre>";
echo '<a href="detalle_ajax.php?id='.$caja['id'].'">Ver detalle_ajax directamente</a>';
