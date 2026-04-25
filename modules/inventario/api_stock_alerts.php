<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['count'=>0]); exit; }
$db = getDB();
$n  = $db->query("SELECT COUNT(*) FROM productos WHERE stock_actual <= stock_minimo AND activo=1")->fetchColumn();
echo json_encode(['count'=>(int)$n]);
