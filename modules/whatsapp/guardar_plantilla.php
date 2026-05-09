<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
requireLogin();
$db = getDB();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->prepare("INSERT INTO wa_plantillas (nombre, categoria, texto, usuario_id) VALUES (?,?,?,?)")
       ->execute([
           trim($_POST['nombre']),
           $_POST['categoria'] ?? 'general',
           trim($_POST['texto']),
           currentUser()['id'],
       ]);
    setFlash('success', 'Plantilla guardada correctamente.');
}
redirect(BASE_URL . 'modules/whatsapp/index.php');
