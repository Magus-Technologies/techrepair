<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

// Datos de la empresa
$empresa = [];
try {
    $db2  = getDB();
    $cfgs = $db2->query("SELECT clave, valor FROM configuracion")->fetchAll();
    foreach ($cfgs as $r) $empresa[$r['clave']] = $r['valor'];
} catch(Exception $e) {}

$nombreEmpresa = $empresa['empresa_nombre']   ?? APP_NAME;
$telEmpresa    = $empresa['empresa_telefono'] ?? '';
$dirEmpresa    = $empresa['empresa_direccion']?? '';

// Buscar OT por código público
$ot      = null;
$fotos   = [];
$historial = [];
$error   = '';
$codigo  = strtoupper(trim($_GET['codigo'] ?? $_POST['codigo'] ?? ''));

if ($codigo) {
    try {
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT ot.*,
                   c.nombre   AS cliente_nombre,
                   te.nombre  AS tipo_equipo,
                   e.marca, e.modelo, e.serial, e.color,
                   CONCAT(u.nombre,' ',u.apellido) AS tecnico_nombre
            FROM ordenes_trabajo ot
            JOIN clientes    c  ON c.id  = ot.cliente_id
            JOIN equipos     e  ON e.id  = ot.equipo_id
            JOIN tipos_equipo te ON te.id = e.tipo_equipo_id
            LEFT JOIN usuarios u ON u.id = ot.tecnico_id
            WHERE ot.codigo_publico = ?
            LIMIT 1
        ");
        $stmt->execute([$codigo]);
        $ot = $stmt->fetch();

        if ($ot) {
            // Fotos ingreso
            $f = $db->prepare("SELECT ruta FROM fotos_ot WHERE ot_id=? AND tipo='ingreso' ORDER BY id LIMIT 6");
            $f->execute([$ot['id']]);
            $fotos = $f->fetchAll(PDO::FETCH_COLUMN);

            // Historial de estados (solo cambios visibles al cliente)
            $h = $db->prepare("
                SELECT estado_nuevo, comentario, created_at
                FROM historial_ot
                WHERE ot_id=?
                ORDER BY created_at ASC
            ");
            $h->execute([$ot['id']]);
            $historial = $h->fetchAll();
        } else {
            $error = 'No encontramos ninguna orden con ese código. Verifica que esté escrito correctamente.';
        }
    } catch(Exception $e) {
        $error = 'Error de conexión. Intenta más tarde.';
    }
}

// Config de estados para el portal
$estadosPortal = [
    'ingresado'     => [
        'paso'  => 1,
        'label' => 'Ingresado',
        'emoji' => '📥',
        'color' => '#6b7280',
        'bg'    => '#f3f4f6',
        'desc'  => 'Tu equipo ha ingresado al taller y será revisado pronto.',
    ],
    'en_revision'   => [
        'paso'  => 2,
        'label' => 'En revisión',
        'emoji' => '🔍',
        'color' => '#0284c7',
        'bg'    => '#e0f2fe',
        'desc'  => 'Nuestro técnico está evaluando tu equipo para determinar el diagnóstico.',
    ],
    'en_reparacion' => [
        'paso'  => 3,
        'label' => 'En reparación',
        'emoji' => '🔧',
        'color' => '#d97706',
        'bg'    => '#fef3c7',
        'desc'  => 'Tu equipo se encuentra en proceso de reparación.',
    ],
    'listo'         => [
        'paso'  => 4,
        'label' => '¡Listo para recoger!',
        'emoji' => '✅',
        'color' => '#16a34a',
        'bg'    => '#dcfce7',
        'desc'  => '¡Tu equipo está listo! Puedes pasar a recogerlo cuando gustes.',
    ],
    'entregado'     => [
        'paso'  => 5,
        'label' => 'Entregado',
        'emoji' => '📦',
        'color' => '#7c3aed',
        'bg'    => '#ede9fe',
        'desc'  => 'El equipo fue entregado. ¡Gracias por confiar en nosotros!',
    ],
    'cancelado'     => [
        'paso'  => 0,
        'label' => 'Cancelado',
        'emoji' => '❌',
        'color' => '#dc2626',
        'bg'    => '#fee2e2',
        'desc'  => 'Esta orden fue cancelada.',
    ],
];

$eData   = $ot ? ($estadosPortal[$ot['estado']] ?? $estadosPortal['ingresado']) : null;
$pasoAct = $ot ? $eData['paso'] : 0;
$pasos   = ['ingresado','en_revision','en_reparacion','listo','entregado'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1"/>
  <title>Consultar reparación — <?= htmlspecialchars($nombreEmpresa) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --brand:    #4f46e5;
      --brand-lt: #eef2ff;
      --gray-50:  #f9fafb;
      --gray-100: #f3f4f6;
      --gray-200: #e5e7eb;
      --gray-400: #9ca3af;
      --gray-600: #4b5563;
      --gray-800: #1f2937;
      --radius:   14px;
      --shadow:   0 4px 24px rgba(0,0,0,.08);
    }

    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      padding: 24px 16px 48px;
      color: var(--gray-800);
    }

    /* ── HEADER ── */
    .portal-header {
      text-align: center;
      margin-bottom: 28px;
      color: #fff;
    }
    .portal-header .brand-icon {
      width: 64px; height: 64px;
      background: rgba(255,255,255,.2);
      backdrop-filter: blur(8px);
      border-radius: 18px;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 12px;
      font-size: 30px;
    }
    .portal-header h1 { font-size: 22px; font-weight: 800; }
    .portal-header p  { font-size: 14px; opacity: .85; margin-top: 4px; }

    /* ── CARD ── */
    .card {
      background: #fff;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      max-width: 600px;
      margin: 0 auto 20px;
      overflow: hidden;
    }
    .card-body { padding: 24px; }

    /* ── SEARCH BOX ── */
    .search-box { padding: 24px; }
    .search-box label {
      display: block;
      font-size: 13px; font-weight: 600;
      color: var(--gray-600); margin-bottom: 8px;
      text-transform: uppercase; letter-spacing: .05em;
    }
    .search-row { display: flex; gap: 10px; }
    .search-input {
      flex: 1;
      border: 2px solid var(--gray-200);
      border-radius: 10px;
      padding: 12px 16px;
      font-size: 18px; font-weight: 700;
      font-family: 'Inter', monospace;
      letter-spacing: .15em;
      text-transform: uppercase;
      outline: none;
      transition: border-color .2s;
    }
    .search-input:focus { border-color: var(--brand); }
    .search-btn {
      background: var(--brand);
      color: #fff;
      border: none;
      border-radius: 10px;
      padding: 12px 20px;
      font-size: 15px; font-weight: 600;
      cursor: pointer;
      transition: background .15s, transform .1s;
      white-space: nowrap;
    }
    .search-btn:hover  { background: #4338ca; }
    .search-btn:active { transform: scale(.97); }

    .error-msg {
      background: #fee2e2;
      border: 1px solid #fca5a5;
      border-radius: 8px;
      padding: 12px 16px;
      font-size: 14px;
      color: #991b1b;
      margin-top: 12px;
    }

    /* ── STATUS HERO ── */
    .status-hero {
      padding: 28px 24px 20px;
      text-align: center;
      border-bottom: 1px solid var(--gray-100);
    }
    .status-emoji { font-size: 52px; line-height: 1; margin-bottom: 8px; }
    .status-label {
      font-size: 22px; font-weight: 800;
      margin-bottom: 6px;
    }
    .status-desc {
      font-size: 14px; color: var(--gray-600);
      max-width: 360px; margin: 0 auto;
      line-height: 1.5;
    }

    /* ── PROGRESS BAR ── */
    .progress-wrap { padding: 20px 24px; border-bottom: 1px solid var(--gray-100); }
    .progress-steps {
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: relative;
    }
    .progress-steps::before {
      content: '';
      position: absolute;
      top: 18px; left: 18px; right: 18px;
      height: 3px;
      background: var(--gray-200);
      z-index: 0;
    }
    .progress-line {
      position: absolute;
      top: 18px; left: 18px;
      height: 3px;
      background: var(--brand);
      z-index: 1;
      transition: width .6s ease;
    }
    .step-item {
      display: flex; flex-direction: column;
      align-items: center; gap: 6px;
      position: relative; z-index: 2;
      flex: 1;
    }
    .step-circle {
      width: 36px; height: 36px;
      border-radius: 50%;
      border: 3px solid var(--gray-200);
      background: #fff;
      display: flex; align-items: center; justify-content: center;
      font-size: 14px; font-weight: 700;
      color: var(--gray-400);
      transition: all .3s;
    }
    .step-circle.done    { background: var(--brand); border-color: var(--brand); color: #fff; }
    .step-circle.active  { background: #fff; border-color: var(--brand); color: var(--brand); box-shadow: 0 0 0 4px rgba(79,70,229,.15); }
    .step-label {
      font-size: 10px; font-weight: 600;
      color: var(--gray-400);
      text-align: center; line-height: 1.2;
      max-width: 60px;
    }
    .step-label.done   { color: var(--brand); }
    .step-label.active { color: var(--brand); font-weight: 700; }

    /* ── INFO SECTION ── */
    .info-section { padding: 20px 24px; border-bottom: 1px solid var(--gray-100); }
    .info-section:last-child { border-bottom: none; }
    .section-title {
      font-size: 11px; font-weight: 700;
      color: var(--gray-400);
      text-transform: uppercase; letter-spacing: .08em;
      margin-bottom: 14px;
    }
    .info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }
    .info-item .label {
      font-size: 11px; color: var(--gray-400); font-weight: 500;
      margin-bottom: 2px;
    }
    .info-item .value {
      font-size: 14px; font-weight: 600; color: var(--gray-800);
    }
    .info-item.full { grid-column: 1 / -1; }

    /* ── TIMELINE ── */
    .timeline { padding: 0; list-style: none; }
    .tl-item {
      display: flex; gap: 14px;
      padding-bottom: 18px;
      position: relative;
    }
    .tl-item:last-child { padding-bottom: 0; }
    .tl-item:not(:last-child)::before {
      content: '';
      position: absolute;
      left: 14px; top: 28px; bottom: 0;
      width: 2px; background: var(--gray-200);
    }
    .tl-dot {
      width: 28px; height: 28px; flex-shrink: 0;
      border-radius: 50%;
      background: var(--brand-lt);
      border: 2px solid var(--brand);
      display: flex; align-items: center; justify-content: center;
      font-size: 12px;
    }
    .tl-dot.last { background: var(--brand); color: #fff; }
    .tl-content .tl-estado {
      font-size: 13px; font-weight: 700; color: var(--gray-800);
    }
    .tl-content .tl-fecha {
      font-size: 11px; color: var(--gray-400); margin-top: 1px;
    }
    .tl-content .tl-comentario {
      font-size: 12px; color: var(--gray-600);
      background: var(--gray-50); border-radius: 6px;
      padding: 6px 10px; margin-top: 6px;
      border-left: 3px solid var(--brand);
    }

    /* ── FOTOS ── */
    .fotos-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 8px;
    }
    .fotos-grid img {
      width: 100%; aspect-ratio: 1;
      object-fit: cover; border-radius: 8px;
      border: 1px solid var(--gray-200);
      cursor: pointer;
      transition: transform .15s;
    }
    .fotos-grid img:hover { transform: scale(1.03); }

    /* ── ALERT LISTO ── */
    .alert-listo {
      background: linear-gradient(135deg, #16a34a, #15803d);
      color: #fff;
      border-radius: var(--radius);
      padding: 20px 24px;
      text-align: center;
      margin: 0 24px 20px;
    }
    .alert-listo h3 { font-size: 18px; font-weight: 800; margin-bottom: 6px; }
    .alert-listo p  { font-size: 14px; opacity: .9; }
    .code-badge {
      display: inline-block;
      background: rgba(255,255,255,.2);
      border: 2px solid rgba(255,255,255,.4);
      border-radius: 8px;
      padding: 4px 14px;
      font-size: 20px; font-weight: 800;
      letter-spacing: .12em;
      margin: 8px 0;
    }

    /* ── CTA WHATSAPP ── */
    .btn-wa {
      display: flex; align-items: center; justify-content: center; gap: 10px;
      background: #25D366; color: #fff;
      border: none; border-radius: 10px;
      padding: 14px 20px;
      font-size: 15px; font-weight: 700;
      text-decoration: none;
      transition: background .15s, transform .1s;
      cursor: pointer;
      width: 100%;
    }
    .btn-wa:hover  { background: #20b858; color: #fff; }
    .btn-wa:active { transform: scale(.97); }
    .btn-wa svg    { width: 22px; height: 22px; flex-shrink: 0; }

    /* ── FOOTER ── */
    .portal-footer {
      text-align: center;
      color: rgba(255,255,255,.7);
      font-size: 12px;
      margin-top: 24px;
    }
    .portal-footer a { color: rgba(255,255,255,.9); }

    /* ── LIGHTBOX ── */
    .lightbox {
      display: none; position: fixed;
      inset: 0; background: rgba(0,0,0,.9);
      z-index: 999;
      align-items: center; justify-content: center;
    }
    .lightbox.open { display: flex; }
    .lightbox img {
      max-width: 90vw; max-height: 90vh;
      border-radius: 8px;
    }
    .lightbox-close {
      position: absolute; top: 16px; right: 20px;
      color: #fff; font-size: 32px; cursor: pointer;
      line-height: 1;
    }

    @media (max-width: 480px) {
      .info-grid { grid-template-columns: 1fr; }
      .fotos-grid { grid-template-columns: repeat(2,1fr); }
      .step-label { font-size: 9px; max-width: 50px; }
      .step-circle { width: 30px; height: 30px; font-size: 12px; }
    }
  </style>
</head>
<body>

<!-- Header del portal -->
<div class="portal-header">
  <div class="brand-icon">🔧</div>
  <h1><?= htmlspecialchars($nombreEmpresa) ?></h1>
  <p>Consulta el estado de tu reparación en tiempo real</p>
</div>

<!-- Caja de búsqueda -->
<div class="card">
  <div class="search-box">
    <form method="POST">
      <label>Ingresa tu código de seguimiento</label>
      <div class="search-row">
        <input type="text" name="codigo" class="search-input"
               placeholder="Ej: A1B2C3D4"
               value="<?= htmlspecialchars($codigo) ?>"
               maxlength="10" autocomplete="off" autocapitalize="characters"
               autofocus/>
        <button type="submit" class="search-btn">🔍 Consultar</button>
      </div>
      <?php if ($error): ?>
      <div class="error-msg">⚠️ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
    </form>
    <?php if (!$ot && !$error): ?>
    <p style="font-size:13px;color:var(--gray-400);margin-top:12px;text-align:center">
      Encuentra tu código en el comprobante que te entregamos al dejar tu equipo.
    </p>
    <?php endif; ?>
  </div>
</div>

<?php if ($ot): ?>

<!-- Alerta especial si está listo -->
<?php if ($ot['estado'] === 'listo'): ?>
<div class="card" style="max-width:600px;margin:0 auto 20px;overflow:hidden">
  <div class="alert-listo">
    <h3>🎉 ¡Tu equipo está listo!</h3>
    <p>Puedes pasar a recogerlo. Recuerda traer este código y tu DNI:</p>
    <div class="code-badge"><?= htmlspecialchars($ot['codigo_publico']) ?></div>
    <p style="margin-top:8px;font-size:13px"><?= htmlspecialchars($dirEmpresa) ?></p>
  </div>
</div>
<?php endif; ?>

<!-- Card principal del estado -->
<div class="card">

  <!-- Hero del estado -->
  <div class="status-hero" style="background:<?= $eData['bg'] ?>">
    <div class="status-emoji"><?= $eData['emoji'] ?></div>
    <div class="status-label" style="color:<?= $eData['color'] ?>"><?= $eData['label'] ?></div>
    <div class="status-desc"><?= $eData['desc'] ?></div>
  </div>

  <!-- Barra de progreso por pasos -->
  <?php if ($ot['estado'] !== 'cancelado'): ?>
  <div class="progress-wrap">
    <?php
      // Calcular ancho de la línea de progreso
      $totalPasos = count($pasos) - 1; // 4 gaps entre 5 pasos
      $anchoLinea = $pasoAct > 0
        ? min(100, round((($pasoAct - 1) / $totalPasos) * 100))
        : 0;
    ?>
    <div class="progress-steps">
      <div class="progress-line" style="width:calc(<?= $anchoLinea ?>% - 0px)"></div>
      <?php foreach ($pasos as $i => $paso):
        $num    = $i + 1;
        $eP     = $estadosPortal[$paso];
        $hecho  = $pasoAct > $num;
        $activo = $pasoAct === $num;
        $clase  = $hecho ? 'done' : ($activo ? 'active' : '');
      ?>
      <div class="step-item">
        <div class="step-circle <?= $clase ?>">
          <?= $hecho ? '✓' : $num ?>
        </div>
        <div class="step-label <?= $clase ?>"><?= $eP['label'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Datos del equipo y la OT -->
  <div class="info-section">
    <div class="section-title">📋 Información de tu orden</div>
    <div class="info-grid">
      <div class="info-item">
        <div class="label">Código OT</div>
        <div class="value"><?= htmlspecialchars($ot['codigo_ot']) ?></div>
      </div>
      <div class="info-item">
        <div class="label">Código consulta</div>
        <div class="value" style="font-family:monospace;letter-spacing:.08em"><?= htmlspecialchars($ot['codigo_publico']) ?></div>
      </div>
      <div class="info-item full">
        <div class="label">Cliente</div>
        <div class="value"><?= htmlspecialchars($ot['cliente_nombre']) ?></div>
      </div>
      <div class="info-item">
        <div class="label">Equipo</div>
        <div class="value"><?= htmlspecialchars(trim($ot['tipo_equipo'].' '.($ot['marca']??'').' '.($ot['modelo']??''))) ?></div>
      </div>
      <?php if ($ot['serial']): ?>
      <div class="info-item">
        <div class="label">Serial</div>
        <div class="value" style="font-family:monospace;font-size:12px"><?= htmlspecialchars($ot['serial']) ?></div>
      </div>
      <?php endif; ?>
      <div class="info-item">
        <div class="label">Fecha de ingreso</div>
        <div class="value"><?= date('d/m/Y', strtotime($ot['fecha_ingreso'])) ?></div>
      </div>
      <?php if ($ot['fecha_estimada']): ?>
      <div class="info-item">
        <div class="label">Entrega estimada</div>
        <div class="value <?= ($ot['fecha_estimada'] < date('Y-m-d') && !in_array($ot['estado'],['listo','entregado','cancelado'])) ? 'text-danger' : '' ?>">
          <?= date('d/m/Y', strtotime($ot['fecha_estimada'])) ?>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($ot['fecha_entrega']): ?>
      <div class="info-item">
        <div class="label">Fecha entregado</div>
        <div class="value"><?= date('d/m/Y H:i', strtotime($ot['fecha_entrega'])) ?></div>
      </div>
      <?php endif; ?>
      <?php if ($ot['tecnico_nombre']): ?>
      <div class="info-item">
        <div class="label">Técnico asignado</div>
        <div class="value">👨‍🔧 <?= htmlspecialchars($ot['tecnico_nombre']) ?></div>
      </div>
      <?php endif; ?>
      <?php if ($ot['garantia_dias'] && $ot['estado'] === 'entregado'): ?>
      <div class="info-item">
        <div class="label">Garantía</div>
        <div class="value">🛡️ <?= $ot['garantia_dias'] ?> días</div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Problema reportado -->
  <div class="info-section">
    <div class="section-title">💬 Problema reportado</div>
    <p style="font-size:14px;color:var(--gray-600);line-height:1.6">
      <?= nl2br(htmlspecialchars($ot['problema_reportado'])) ?>
    </p>
    <?php if ($ot['diagnostico_tecnico']): ?>
    <div style="margin-top:12px">
      <div class="section-title" style="margin-bottom:8px">🔬 Diagnóstico técnico</div>
      <p style="font-size:14px;color:var(--gray-600);line-height:1.6">
        <?= nl2br(htmlspecialchars($ot['diagnostico_tecnico'])) ?>
      </p>
    </div>
    <?php endif; ?>
  </div>

  <!-- Presupuesto aprobado -->
  <?php if ($ot['presupuesto_aprobado'] && $ot['precio_final'] > 0): ?>
  <div class="info-section">
    <div class="section-title">💰 Presupuesto aprobado</div>
    <div style="display:flex;justify-content:space-between;align-items:center;background:var(--gray-50);border-radius:10px;padding:14px 16px">
      <span style="font-size:14px;color:var(--gray-600)">Total de la reparación</span>
      <span style="font-size:22px;font-weight:800;color:var(--gray-800)">S/ <?= number_format($ot['precio_final'],2) ?></span>
    </div>
    <?php if ($ot['pagado']): ?>
    <div style="margin-top:8px;background:#dcfce7;border-radius:8px;padding:10px 14px;font-size:13px;color:#16a34a;font-weight:600">
      ✅ Pago registrado
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Fotos del equipo -->
  <?php if (!empty($fotos)): ?>
  <div class="info-section">
    <div class="section-title">📸 Fotos al ingreso</div>
    <div class="fotos-grid">
      <?php foreach($fotos as $f): ?>
      <img src="<?= UPLOAD_URL . htmlspecialchars($f) ?>"
           alt="Foto equipo"
           onclick="abrirLightbox(this.src)"
           loading="lazy"/>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Timeline / historial -->
  <?php if (!empty($historial)): ?>
  <div class="info-section">
    <div class="section-title">📅 Historial de la orden</div>
    <ul class="timeline">
      <?php foreach($historial as $hi => $h):
        $esUltimo = $hi === array_key_last($historial);
        $eH = $estadosPortal[$h['estado_nuevo']] ?? ['emoji'=>'•','label'=>$h['estado_nuevo']];
      ?>
      <li class="tl-item">
        <div class="tl-dot <?= $esUltimo?'last':'' ?>"><?= $eH['emoji'] ?></div>
        <div class="tl-content">
          <div class="tl-estado"><?= htmlspecialchars($eH['label'] ?? $h['estado_nuevo']) ?></div>
          <div class="tl-fecha"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></div>
          <?php if ($h['comentario']): ?>
          <div class="tl-comentario"><?= htmlspecialchars($h['comentario']) ?></div>
          <?php endif; ?>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <!-- Botón WhatsApp contacto -->
  <?php if ($telEmpresa): ?>
  <div class="info-section">
    <div class="section-title">📞 ¿Tienes alguna consulta?</div>
    <a href="https://wa.me/51<?= preg_replace('/\D/','',$telEmpresa) ?>?text=Hola+<?= urlencode($nombreEmpresa) ?>%2C+tengo+una+consulta+sobre+mi+reparaci%C3%B3n+con+c%C3%B3digo+<?= $ot['codigo_publico'] ?>"
       target="_blank" class="btn-wa">
      <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16">
        <path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.898 7.898 0 0 0 13.6 2.326zM7.994 14.521a6.573 6.573 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.557 6.557 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592zm3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.729.729 0 0 0-.529.247c-.182.198-.691.677-.691 1.654 0 .977.71 1.916.81 2.049.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232z"/>
      </svg>
      Contactar por WhatsApp
    </a>
  </div>
  <?php endif; ?>

</div><!-- /card principal -->

<!-- Lightbox para fotos -->
<div class="lightbox" id="lightbox" onclick="cerrarLightbox()">
  <span class="lightbox-close" onclick="cerrarLightbox()">×</span>
  <img id="lightbox-img" src="" alt="Foto"/>
</div>

<?php endif; ?>

<!-- Footer -->
<div class="portal-footer">
  <p><?= htmlspecialchars($nombreEmpresa) ?><?= $dirEmpresa ? ' — '.htmlspecialchars($dirEmpresa) : '' ?></p>
  <p style="margin-top:4px">Sistema de gestión técnica TechRepair Pro</p>
</div>

<script>
function abrirLightbox(src) {
  document.getElementById('lightbox-img').src = src;
  document.getElementById('lightbox').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function cerrarLightbox() {
  document.getElementById('lightbox').classList.remove('open');
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key==='Escape') cerrarLightbox(); });

// Auto-uppercase en el input
document.querySelector('.search-input')?.addEventListener('input', function() {
  const pos = this.selectionStart;
  this.value = this.value.toUpperCase();
  this.setSelectionRange(pos, pos);
});
</script>
</body>
</html>
