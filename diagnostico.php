<?php
// diagnostico.php — Subir al servidor, ejecutar UNA VEZ, luego ELIMINAR
require_once __DIR__ . '/config/database.php';

$nuevaPassword = 'Admin123!';
$hash = password_hash($nuevaPassword, PASSWORD_BCRYPT, ['cost' => 10]);

try {
    $db = getDB();

    // 1. Ver qué hay en la tabla usuarios
    $usuarios = $db->query("SELECT id, nombre, email, rol, activo, LEFT(password_hash,30) as hash_preview FROM usuarios")->fetchAll();

    // 2. Actualizar o insertar admin
    $existe = $db->prepare("SELECT id FROM usuarios WHERE email = 'admin@techrepair.com'");
    $existe->execute();
    $uid = $existe->fetchColumn();

    if ($uid) {
        $db->prepare("UPDATE usuarios SET password_hash = ?, activo = 1 WHERE email = 'admin@techrepair.com'")->execute([$hash]);
        $accion = "✅ Contraseña ACTUALIZADA para admin existente (id=$uid)";
    } else {
        $db->prepare("INSERT INTO usuarios (nombre,apellido,email,password_hash,rol,activo) VALUES (?,?,?,?,?,1)")
           ->execute(['Administrador','Sistema','admin@techrepair.com',$hash,'admin']);
        $accion = "✅ Usuario admin CREADO desde cero";
    }

    // 3. Verificar que el hash funciona
    $row = $db->prepare("SELECT password_hash FROM usuarios WHERE email='admin@techrepair.com'");
    $row->execute();
    $row = $row->fetch();
    $verificacion = password_verify($nuevaPassword, $row['password_hash']) ? "✅ HASH VÁLIDO" : "❌ HASH INVÁLIDO";

} catch (Exception $e) {
    die("<pre style='color:red'>ERROR BD: " . $e->getMessage() . "</pre>");
}
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"/>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
</head><body class="p-4">
<div class="card" style="max-width:700px">
<div class="card-body">
  <h5>🔧 Diagnóstico TechRepair</h5><hr/>

  <h6>Usuarios en BD:</h6>
  <table class="table table-sm table-bordered small">
    <thead><tr><th>ID</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Activo</th><th>Hash (primeros 30 chars)</th></tr></thead>
    <tbody>
      <?php foreach($usuarios as $u): ?>
      <tr>
        <td><?= $u['id'] ?></td>
        <td><?= htmlspecialchars($u['nombre']) ?></td>
        <td><?= htmlspecialchars($u['email']) ?></td>
        <td><?= htmlspecialchars($u['rol']) ?></td>
        <td><?= $u['activo'] ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-danger">No</span>' ?></td>
        <td><code><?= htmlspecialchars($u['hash_preview']) ?>...</code></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="alert alert-info"><?= $accion ?></div>
  <div class="alert alert-<?= str_contains($verificacion,'VÁLIDO') && !str_contains($verificacion,'IN') ? 'success' : 'danger' ?>">
    Verificación: <?= $verificacion ?>
  </div>

  <div class="alert alert-success">
    <strong>Credenciales:</strong><br/>
    Email: <code>admin@techrepair.com</code><br/>
    Contraseña: <code>Admin123!</code>
  </div>

  <div class="alert alert-warning">
    ⚠️ <strong>ELIMINA este archivo del servidor después de usarlo:</strong><br/>
    <code>diagnostico.php</code>
  </div>

  <a href="modules/auth/login.php" class="btn btn-primary">Ir al login →</a>
</div>
</div>
</body></html>
