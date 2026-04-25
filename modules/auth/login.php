<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

if (isLoggedIn()) redirect(BASE_URL . 'modules/dashboard/index.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['user_nombre'] = $user['nombre'];
            $_SESSION['user_rol']    = $user['rol'];
            $_SESSION['user_email']  = $user['email'];

            // Actualizar último acceso
            $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?")
               ->execute([$user['id']]);

            redirect(BASE_URL . 'modules/dashboard/index.php');
        } else {
            $error = 'Correo o contraseña incorrectos.';
        }
    } else {
        $error = 'Completa todos los campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Iniciar sesión — <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
  <style>
    body { background: #f4f6fb; }
    .login-card { max-width: 420px; border-radius: 14px; }
    .brand-icon { width: 52px; height: 52px; background: #4f46e5; border-radius: 14px;
      display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
    .brand-icon svg { width: 28px; height: 28px; color: #fff; stroke: #fff; }
  </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">
  <div class="login-card card shadow-sm p-4 w-100 mx-3">
    <div class="text-center mb-4">
      <div class="brand-icon"><i data-feather="tool"></i></div>
      <h4 class="fw-bold mb-0"><?= APP_NAME ?></h4>
      <p class="text-muted small mt-1">Sistema de gestión técnica</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger py-2 small"><?= sanitize($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-3">
        <label class="form-label small fw-semibold">Correo electrónico</label>
        <div class="input-group">
          <span class="input-group-text"><i data-feather="mail" style="width:16px;height:16px"></i></span>
          <input type="email" name="email" class="form-control"
                 value="<?= sanitize($_POST['email'] ?? '') ?>"
                 placeholder="admin@techrepair.com" required autofocus/>
        </div>
      </div>
      <div class="mb-4">
        <label class="form-label small fw-semibold">Contraseña</label>
        <div class="input-group">
          <span class="input-group-text"><i data-feather="lock" style="width:16px;height:16px"></i></span>
          <input type="password" name="password" class="form-control" placeholder="••••••••" required/>
          <button type="button" class="btn btn-outline-secondary" id="btn-show-pass">
            <i data-feather="eye" style="width:16px;height:16px"></i>
          </button>
        </div>
      </div>
      <button type="submit" class="btn btn-primary w-100 fw-semibold">
        Ingresar al sistema
      </button>
    </form>

    <div class="text-center mt-4 text-muted" style="font-size:11px">
      Demo: admin@techrepair.com / Admin123!
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    feather.replace();
    document.getElementById('btn-show-pass').addEventListener('click', function() {
      const inp = this.previousElementSibling;
      inp.type = inp.type === 'password' ? 'text' : 'password';
    });
  </script>
</body>
</html>
