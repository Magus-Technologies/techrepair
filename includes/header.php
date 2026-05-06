<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"/>
  <title><?= $pageTitle ?? APP_NAME ?></title>

  <!-- Bootstrap 5.3 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <!-- Feather Icons -->
  <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
  <!-- SortableJS (drag estados OT) -->
  <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
  <!-- Signature Pad -->
  <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link href="<?= BASE_URL ?>assets/css/app.css" rel="stylesheet"/>
  <script>window.BASE_URL = '<?= BASE_URL ?>';</script>
  <style>
    .tr-nav-collapse { overflow: hidden; max-height: 0; transition: max-height 0.25s ease; }
    .tr-nav-collapse.open { max-height: 500px; }
  </style>
</head>
<body class="tr-body">

<!-- Mobile overlay -->
<div id="sidebar-overlay"></div>

<!-- SIDEBAR -->
<div class="tr-sidebar" id="sidebar">
  <div class="tr-sidebar-brand">
    <i data-feather="tool" class="me-2"></i>
    <span><?= APP_NAME ?></span>
  </div>

  <nav class="tr-nav">
    <?php $u = currentUser(); $rol = $u['rol']; ?>

    <a href="<?= BASE_URL ?>modules/dashboard/index.php"
       class="tr-nav-item <?= strpos($_SERVER['REQUEST_URI'],'dashboard')!==false?'active':'' ?>">
      <i data-feather="home"></i><span>Dashboard</span>
    </a>

    <?php if(in_array($rol,[ROL_ADMIN,ROL_TECNICO,ROL_VENDEDOR])): ?>
    <?php $repActive = strpos($_SERVER['REQUEST_URI'],'/ot/')!==false; ?>
    <div class="tr-nav-group tr-nav-collapse-toggle" data-target="nav-reparaciones" style="cursor:pointer">
      Reparaciones <i data-feather="chevron-down" style="width:12px;height:12px;float:right;margin-top:2px;transition:.2s" id="icon-reparaciones"></i>
    </div>
    <div id="nav-reparaciones" class="tr-nav-collapse <?= $repActive?'open':'' ?>">
    <a href="<?= BASE_URL ?>modules/ot/index.php"
       class="tr-nav-item <?= $repActive?'active':'' ?>">
      <i data-feather="clipboard"></i><span>Órdenes de trabajo</span>
    </a>
    <a href="<?= BASE_URL ?>modules/ot/nueva.php" class="tr-nav-item">
      <i data-feather="plus-circle"></i><span>Nueva OT</span>
    </a>
    </div>
    <?php endif; ?>

    <?php if(in_array($rol,[ROL_ADMIN,ROL_VENDEDOR])): ?>
    <?php $ventasActive = strpos($_SERVER['REQUEST_URI'],'ventas')!==false || strpos($_SERVER['REQUEST_URI'],'facturacion')!==false; ?>
    <div class="tr-nav-group tr-nav-collapse-toggle" data-target="nav-ventas" style="cursor:pointer">
      Ventas <i data-feather="chevron-down" style="width:12px;height:12px;float:right;margin-top:2px;transition:.2s" id="icon-ventas"></i>
    </div>
    <div id="nav-ventas" class="tr-nav-collapse <?= $ventasActive?'open':'' ?>">
    <a href="<?= BASE_URL ?>modules/ventas/pos.php" class="tr-nav-item">
      <i data-feather="shopping-cart"></i><span>Punto de venta</span>
    </a>
    <a href="<?= BASE_URL ?>modules/ventas/index.php" class="tr-nav-item">
      <i data-feather="list"></i><span>Historial ventas</span>
    </a>
    <a href="<?= BASE_URL ?>modules/facturacion/index.php"
       class="tr-nav-item <?= strpos($_SERVER['REQUEST_URI'],'facturacion')!==false?'active':'' ?>">
      <i data-feather="file-text"></i><span>Facturación</span>
    </a>
    </div>
    <?php endif; ?>

    <?php if(in_array($rol,[ROL_ADMIN,ROL_TECNICO])): ?>
    <?php $catActive = strpos($_SERVER['REQUEST_URI'],'catalogo')!==false; ?>
    <div class="tr-nav-group tr-nav-collapse-toggle" data-target="nav-catalogo" style="cursor:pointer">
      Catálogo público <i data-feather="chevron-down" style="width:12px;height:12px;float:right;margin-top:2px;transition:.2s" id="icon-catalogo"></i>
    </div>
    <div id="nav-catalogo" class="tr-nav-collapse <?= $catActive?'open':'' ?>">
    <a href="<?= BASE_URL ?>modules/catalogo/index.php"
       class="tr-nav-item <?= $catActive?'active':'' ?>">
      <i data-feather="shopping-bag"></i><span>Catálogo</span>
    </a>
    <a href="<?= BASE_URL ?>public/catalogo/" target="_blank" class="tr-nav-item">
      <i data-feather="external-link"></i><span>Ver catálogo</span>
    </a>
    </div>

    <?php $invActive = strpos($_SERVER['REQUEST_URI'],'inventario')!==false || strpos($_SERVER['REQUEST_URI'],'compras')!==false; ?>
    <div class="tr-nav-group tr-nav-collapse-toggle" data-target="nav-inventario" style="cursor:pointer">
      Inventario <i data-feather="chevron-down" style="width:12px;height:12px;float:right;margin-top:2px;transition:.2s" id="icon-inventario"></i>
    </div>
    <div id="nav-inventario" class="tr-nav-collapse <?= $invActive?'open':'' ?>">
    <a href="<?= BASE_URL ?>modules/inventario/index.php" class="tr-nav-item">
      <i data-feather="package"></i><span>Productos</span>
    </a>
    <a href="<?= BASE_URL ?>modules/compras/index.php"
       class="tr-nav-item <?= strpos($_SERVER['REQUEST_URI'],'compras')!==false?'active':'' ?>">
      <i data-feather="truck"></i><span>Compras</span>
    </a>
    <a href="<?= BASE_URL ?>modules/inventario/kardex.php" class="tr-nav-item">
      <i data-feather="bar-chart-2"></i><span>Kardex</span>
    </a>
    </div>
    <?php endif; ?>

    <div class="tr-nav-group">Comunicaciones</div>
    <a href="<?= BASE_URL ?>modules/whatsapp/index.php"
       class="tr-nav-item <?= strpos($_SERVER['REQUEST_URI'],'whatsapp')!==false?'active':'' ?>">
      <i data-feather="message-circle"></i><span>WhatsApp</span>
    </a>

    <div class="tr-nav-group">Clientes</div>
    <a href="<?= BASE_URL ?>modules/clientes/index.php" class="tr-nav-item">
      <i data-feather="users"></i><span>Clientes</span>
    </a>

    <?php if(in_array($rol,[ROL_ADMIN,ROL_TECNICO])): ?>
    <div class="tr-nav-group">Servicios</div>
    <a href="<?= BASE_URL ?>modules/servicios/index.php"
       class="tr-nav-item <?= strpos($_SERVER['REQUEST_URI'],'servicios')!==false?'active':'' ?>">
      <i data-feather="briefcase"></i><span>Servicios</span>
    </a>
    <?php endif; ?>

    <?php if($rol === ROL_ADMIN): ?>
    <?php $adminActive = strpos($_SERVER['REQUEST_URI'],'caja')!==false || strpos($_SERVER['REQUEST_URI'],'reportes')!==false || strpos($_SERVER['REQUEST_URI'],'tecnicos')!==false || strpos($_SERVER['REQUEST_URI'],'garantias')!==false || strpos($_SERVER['REQUEST_URI'],'configuracion')!==false; ?>
    <div class="tr-nav-group tr-nav-collapse-toggle" data-target="nav-admin" style="cursor:pointer">
      Administración <i data-feather="chevron-down" style="width:12px;height:12px;float:right;margin-top:2px;transition:.2s" id="icon-admin"></i>
    </div>
    <div id="nav-admin" class="tr-nav-collapse <?= $adminActive?'open':'' ?>">
    <a href="<?= BASE_URL ?>modules/caja/index.php" class="tr-nav-item">
      <i data-feather="dollar-sign"></i><span>Caja</span>
    </a>
    <a href="<?= BASE_URL ?>modules/reportes/index.php" class="tr-nav-item">
      <i data-feather="trending-up"></i><span>Reportes</span>
    </a>
    <a href="<?= BASE_URL ?>modules/tecnicos/index.php" class="tr-nav-item">
      <i data-feather="user-check"></i><span>Técnicos</span>
    </a>
    <a href="<?= BASE_URL ?>modules/garantias/index.php" class="tr-nav-item">
      <i data-feather="shield"></i><span>Garantías</span>
    </a>
    <a href="<?= BASE_URL ?>modules/configuracion/index.php" class="tr-nav-item">
      <i data-feather="settings"></i><span>Configuración</span>
    </a>
    </div>
    <?php endif; ?>
  </nav>

  <div class="tr-sidebar-footer">
    <div class="d-flex align-items-center gap-2">
      <div class="tr-avatar"><?= strtoupper(substr($u['nombre'],0,1)) ?></div>
      <div class="flex-grow-1 small">
        <div class="fw-semibold text-truncate"><?= sanitize($u['nombre']) ?></div>
        <div class="text-muted" style="font-size:11px"><?= ucfirst($u['rol']) ?></div>
      </div>
      <a href="<?= BASE_URL ?>modules/auth/logout.php" title="Cerrar sesión">
        <i data-feather="log-out" style="color:#fff"></i>
      </a>
    </div>
  </div>
</div>

<!-- MAIN WRAPPER -->
<script>
document.querySelectorAll('.tr-nav-collapse-toggle').forEach(function(toggle) {
  var targetId = toggle.getAttribute('data-target');
  var target   = document.getElementById(targetId);
  var icon     = document.getElementById('icon-' + targetId.replace('nav-',''));
  if (!target) return;
  // Si ya está abierto, rotar icono
  if (target.classList.contains('open') && icon) icon.style.transform = 'rotate(180deg)';
  toggle.addEventListener('click', function() {
    target.classList.toggle('open');
    if (icon) icon.style.transform = target.classList.contains('open') ? 'rotate(180deg)' : 'rotate(0deg)';
  });
});
</script>

<!-- MAIN WRAPPER -->
<div class="tr-main" id="main-content">

  <!-- TOPBAR -->
  <div class="tr-topbar">
    <button class="btn btn-sm btn-light" id="sidebar-toggle">
      <i data-feather="menu"></i>
    </button>
    <nav aria-label="breadcrumb" class="ms-3">
      <ol class="breadcrumb mb-0">
        <?php foreach ($breadcrumb ?? [] as $item): ?>
          <?php if ($item['url']): ?>
            <li class="breadcrumb-item">
              <a href="<?= $item['url'] ?>"><?= sanitize($item['label']) ?></a>
            </li>
          <?php else: ?>
            <li class="breadcrumb-item active"><?= sanitize($item['label']) ?></li>
          <?php endif; ?>
        <?php endforeach; ?>
      </ol>
    </nav>
    <div class="ms-auto d-flex align-items-center gap-3">
      <!-- Notificaciones stock -->
      <div class="position-relative">
        <button class="btn btn-sm btn-light" id="btn-notif">
          <i data-feather="bell"></i>
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="badge-notif" style="display:none">0</span>
        </button>
      </div>
      <span class="text-muted small"><?= date('d/m/Y H:i') ?></span>
    </div>
  </div>

  <!-- FLASH MESSAGE -->
  <?php $flash = getFlash(); if ($flash): ?>
  <div class="alert alert-<?= $flash['tipo'] ?> alert-dismissible mx-4 mt-3 fade show" role="alert">
    <?= sanitize($flash['mensaje']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <!-- PAGE CONTENT -->
  <div class="tr-content">

<script>
// Sidebar toggle
document.getElementById('sidebar-toggle').addEventListener('click', function() {
  document.getElementById('sidebar').classList.toggle('active');
  document.getElementById('sidebar-overlay').classList.toggle('active');
});

document.getElementById('sidebar-overlay').addEventListener('click', function() {
  document.getElementById('sidebar').classList.remove('active');
  this.classList.remove('active');
});

// Feather icons
feather.replace();

// Notificaciones stock
fetch(window.BASE_URL + 'modules/inventario/api_stock_alerts.php')
  .then(r => r.json())
  .then(data => {
    if (data.count > 0) {
      document.getElementById('badge-notif').textContent = data.count;
      document.getElementById('badge-notif').style.display = 'block';
    }
  });
</script>
