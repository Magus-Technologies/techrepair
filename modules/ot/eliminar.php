<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
requireLogin();
// TODO: Agregar control de permisos por rol cuando se implemente el sistema de roles
// requireRole([ROL_ADMIN]); // Descomentar cuando se defina ROL_ADMIN

$db = getDB();
$id = (int)($_POST['id'] ?? 0);

if (!$id) {
    setFlash('danger', 'ID de OT inválido');
    redirect(BASE_URL.'modules/ot/index.php');
}

// Verificar que la OT existe
$ot = $db->prepare("SELECT id, codigo_ot FROM ordenes_trabajo WHERE id = ? AND deleted_at IS NULL");
$ot->execute([$id]);
$ot = $ot->fetch();

if (!$ot) {
    setFlash('danger', 'OT no encontrada');
    redirect(BASE_URL.'modules/ot/index.php');
}

// Soft delete: marcar como eliminada
$db->prepare("UPDATE ordenes_trabajo SET deleted_at = NOW() WHERE id = ?")->execute([$id]);

setFlash('success', 'OT ' . $ot['codigo_ot'] . ' eliminada correctamente');
redirect(BASE_URL.'modules/ot/index.php');
