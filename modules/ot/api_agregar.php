<?php
// api_agregar.php — Agrega tipo equipo, marca o item checklist dinámicamente
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['error'=>'No autorizado']); exit; }

$db     = getDB();
$accion = $_POST['accion'] ?? '';
$valor  = trim($_POST['valor'] ?? '');

if (!$valor) { echo json_encode(['error'=>'Valor vacío']); exit; }

switch ($accion) {
    case 'tipo_equipo':
        $existe = $db->prepare("SELECT id FROM tipos_equipo WHERE nombre=?");
        $existe->execute([$valor]);
        $id = $existe->fetchColumn();
        if (!$id) {
            $db->prepare("INSERT INTO tipos_equipo (nombre,icono) VALUES (?,'package')")->execute([$valor]);
            $id = $db->lastInsertId();
        }
        echo json_encode(['ok'=>true,'id'=>$id,'nombre'=>$valor]);
        break;

    case 'marca':
        $existe = $db->prepare("SELECT id FROM marcas_equipo WHERE nombre=?");
        $existe->execute([$valor]);
        $id = $existe->fetchColumn();
        if (!$id) {
            $db->prepare("INSERT INTO marcas_equipo (nombre) VALUES (?)")->execute([$valor]);
            $id = $db->lastInsertId();
        }
        echo json_encode(['ok'=>true,'id'=>$id,'nombre'=>$valor]);
        break;

    case 'checklist_item':
        $existe = $db->prepare("SELECT id FROM checklist_items WHERE nombre=?");
        $existe->execute([$valor]);
        $id = $existe->fetchColumn();
        if (!$id) {
            $maxOrden = $db->query("SELECT COALESCE(MAX(orden),0)+1 FROM checklist_items")->fetchColumn();
            $db->prepare("INSERT INTO checklist_items (nombre,orden) VALUES (?,?)")->execute([$valor,$maxOrden]);
            $id = $db->lastInsertId();
        }
        echo json_encode(['ok'=>true,'id'=>$id,'nombre'=>$valor]);
        break;

    default:
        echo json_encode(['error'=>'Acción inválida']);
}
