<?php
// api_agregar.php — Agrega tipo equipo, marca o item checklist dinámicamente
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['error'=>'No autorizado']); exit; }

$db     = getDB();
$accion = $_POST['accion'] ?? '';
$valor  = trim($_POST['valor'] ?? '');

// Solo validar $valor para acciones que lo requieren
if (!$valor && !in_array($accion, ['editar_estado', 'eliminar_estado'])) { 
    echo json_encode(['error'=>'Valor vacío']); 
    exit; 
}

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

    case 'estado_orden':
        // Generar código automático desde el nombre
        $codigo = strtolower($valor); // Primero convertir a minúsculas
        $codigo = preg_replace('/[^a-z0-9_]/', '_', $codigo); // Luego reemplazar caracteres no válidos
        $codigo = preg_replace('/_+/', '_', $codigo); // Eliminar guiones múltiples
        $codigo = trim($codigo, '_'); // Eliminar guiones al inicio y final
        
        $color = trim($_POST['color'] ?? 'secondary');
        $icono = trim($_POST['icono'] ?? 'circle');
        
        // Validar color
        $coloresValidos = ['primary','secondary','success','danger','warning','info','dark'];
        if (!in_array($color, $coloresValidos)) $color = 'secondary';
        
        $existe = $db->prepare("SELECT id, codigo FROM estados_orden WHERE codigo=?");
        $existe->execute([$codigo]);
        $row = $existe->fetch();
        
        if (!$row) {
            $maxOrden = $db->query("SELECT COALESCE(MAX(orden),0)+1 FROM estados_orden")->fetchColumn();
            $db->prepare("INSERT INTO estados_orden (codigo, nombre, color, icono, orden, activo, sistema) VALUES (?,?,?,?,?,1,0)")
               ->execute([$codigo, $valor, $color, $icono, $maxOrden]);
            $id = $db->lastInsertId();
            echo json_encode(['ok'=>true,'id'=>$id,'codigo'=>$codigo,'nombre'=>$valor,'color'=>$color,'icono'=>$icono]);
        } else {
            echo json_encode(['ok'=>true,'id'=>$row['id'],'codigo'=>$row['codigo'],'nombre'=>$valor]);
        }
        break;

    case 'editar_estado':
        $codigo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $color = trim($_POST['color'] ?? 'secondary');
        $icono = trim($_POST['icono'] ?? 'circle');
        
        if (empty($codigo) || empty($nombre)) {
            echo json_encode(['error'=>'Código y nombre son obligatorios', 'debug'=>$_POST]);
            break;
        }
        
        // Validar color
        $coloresValidos = ['primary','secondary','success','danger','warning','info','dark'];
        if (!in_array($color, $coloresValidos)) $color = 'secondary';
        
        // Verificar que no sea estado del sistema
        $est = $db->prepare("SELECT sistema FROM estados_orden WHERE codigo=?");
        $est->execute([$codigo]);
        $est = $est->fetch();
        
        if ($est && $est['sistema']) {
            echo json_encode(['error'=>'No se puede editar un estado del sistema']);
            break;
        }
        
        $db->prepare("UPDATE estados_orden SET nombre=?, color=?, icono=? WHERE codigo=?")
           ->execute([$nombre, $color, $icono, $codigo]);
        echo json_encode(['ok'=>true]);
        break;

    case 'eliminar_estado':
        $codigo = trim($_POST['codigo'] ?? '');
        
        if (empty($codigo)) {
            echo json_encode(['error'=>'Código es obligatorio']);
            break;
        }
        
        // Verificar que no sea estado del sistema
        $est = $db->prepare("SELECT sistema FROM estados_orden WHERE codigo=?");
        $est->execute([$codigo]);
        $est = $est->fetch();
        
        if ($est && $est['sistema']) {
            echo json_encode(['error'=>'No se puede eliminar un estado del sistema']);
            break;
        }
        
        // Verificar si hay OTs con este estado
        $count = $db->prepare("SELECT COUNT(*) FROM ordenes_trabajo WHERE estado=? AND deleted_at IS NULL");
        $count->execute([$codigo]);
        $count = $count->fetchColumn();
        
        if ($count > 0) {
            echo json_encode(['error'=>"No se puede eliminar: hay $count órdenes con este estado"]);
            break;
        }
        
        $db->prepare("DELETE FROM estados_orden WHERE codigo=?")->execute([$codigo]);
        echo json_encode(['ok'=>true]);
        break;

    default:
        echo json_encode(['error'=>'Acción inválida']);
}
