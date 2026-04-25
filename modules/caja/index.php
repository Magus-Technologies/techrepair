<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
requireLogin();
requireRole([ROL_ADMIN]);

$db   = getDB();
$user = currentUser();
$hoy  = date('Y-m-d');

$billetes = [200, 100, 50, 20, 10];
$monedas  = [5.00, 2.00, 1.00, 0.50, 0.20, 0.10];

// ── Key helper para denominaciones ──────────────────────
function denomKey(string $tipo, $valor): string {
    if ($tipo === 'bil') return 'bil_' . (int)$valor;
    return 'mon_' . str_replace('.', '_', number_format((float)$valor, 2, '_', ''));
}

// ── Calcular total desde POST ────────────────────────────
function calcTotalDenominaciones(array $data, array $billetes, array $monedas, string $prefix = ''): float {
    $total = 0;
    foreach ($billetes as $b) {
        $k = $prefix . denomKey('bil', $b);
        $total += (int)($data[$k] ?? 0) * (float)$b;
    }
    foreach ($monedas as $m) {
        $k = $prefix . denomKey('mon', $m);
        $total += (int)($data[$k] ?? 0) * (float)$m;
    }
    return round($total, 2);
}

// ── Obtener denominaciones del POST ─────────────────────
function getDenominacionesPost(array $data, array $billetes, array $monedas, string $prefix = ''): array {
    $dens = [];
    foreach ($billetes as $b) {
        $k = denomKey('bil', $b);
        $dens[$k] = (int)($data[$prefix . $k] ?? 0);
    }
    foreach ($monedas as $m) {
        $k = denomKey('mon', $m);
        $dens[$k] = (int)($data[$prefix . $k] ?? 0);
    }
    return $dens;
}

// ════════════════════════════════════════════════════════
// ABRIR CAJA
// ════════════════════════════════════════════════════════
if (isset($_POST['action']) && $_POST['action'] === 'abrir') {
    // Verificar si hay caja abierta (de cualquier fecha)
    $cajaAbierta = $db->query("SELECT id, fecha FROM cajas WHERE estado='abierta' ORDER BY fecha DESC LIMIT 1")->fetch();
    if ($cajaAbierta) {
        setFlash('danger', '⚠️ Hay una caja abierta del ' . date('d/m/Y', strtotime($cajaAbierta['fecha'])) . '. Debes cerrarla antes de abrir una nueva.');
        redirect(BASE_URL . 'modules/caja/index.php');
    }

    $saldo = calcTotalDenominaciones($_POST, $billetes, $monedas, 'ap_');
    $dens  = getDenominacionesPost($_POST, $billetes, $monedas, 'ap_');

    $db->prepare("INSERT INTO cajas (usuario_id, fecha, saldo_inicial, denominaciones_apertura, estado) VALUES (?,?,?,?,'abierta')")
       ->execute([$user['id'], $hoy, $saldo, json_encode($dens)]);
    setFlash('success', '✅ Caja abierta. Saldo inicial: S/ ' . number_format($saldo, 2));
    redirect(BASE_URL . 'modules/caja/index.php');
}

// ════════════════════════════════════════════════════════
// CERRAR CAJA
// ════════════════════════════════════════════════════════
if (isset($_POST['action']) && $_POST['action'] === 'cerrar') {
    $cajaId      = (int)$_POST['caja_id'];
    $saldoCierre = calcTotalDenominaciones($_POST, $billetes, $monedas, 'ci_');
    $dens        = getDenominacionesPost($_POST, $billetes, $monedas, 'ci_');

    // Recalcular totales desde BD (no confiar en JS)
    $c = $db->prepare("SELECT saldo_inicial FROM cajas WHERE id=?");
    $c->execute([$cajaId]);
    $si = (float)$c->fetchColumn();

    $ing = (float)$db->prepare("SELECT COALESCE(SUM(monto),0) FROM movimientos_caja WHERE caja_id=? AND tipo='ingreso'")->execute([$cajaId]) ? 0 : 0;
    $stmt = $db->prepare("SELECT COALESCE(SUM(monto),0) FROM movimientos_caja WHERE caja_id=? AND tipo='ingreso'");
    $stmt->execute([$cajaId]); $ing = (float)$stmt->fetchColumn();

    $stmt2 = $db->prepare("SELECT COALESCE(SUM(monto),0) FROM movimientos_caja WHERE caja_id=? AND tipo='egreso'");
    $stmt2->execute([$cajaId]); $egr = (float)$stmt2->fetchColumn();

    $esperado   = round($si + $ing - $egr, 2);
    $diferencia = round($saldoCierre - $esperado, 2);

    $db->prepare("UPDATE cajas SET estado='cerrada', total_ingresos=?, total_egresos=?, saldo_final=?, denominaciones_cierre=?, diferencia_cierre=?, fecha_cierre=NOW() WHERE id=?")
       ->execute([$ing, $egr, $saldoCierre, json_encode($dens), $diferencia, $cajaId]);

    $tipo  = $diferencia == 0 ? 'success' : ($diferencia > 0 ? 'warning' : 'danger');
    $emoji = $diferencia == 0 ? '✅' : ($diferencia > 0 ? '⚠️' : '❌');
    setFlash($tipo, "$emoji Caja cerrada. Esperado: S/" . number_format($esperado, 2) .
        " | Contado: S/" . number_format($saldoCierre, 2) .
        " | Diferencia: S/" . number_format($diferencia, 2));
    redirect(BASE_URL . 'modules/caja/index.php');
}

// ════════════════════════════════════════════════════════
// MOVIMIENTO MANUAL
// ════════════════════════════════════════════════════════
if (isset($_POST['action']) && $_POST['action'] === 'movimiento') {
    $cajaId = (int)$_POST['caja_id'];
    $db->prepare("INSERT INTO movimientos_caja (caja_id,tipo,concepto,monto,referencia,usuario_id) VALUES (?,?,?,?,?,?)")
       ->execute([$cajaId, $_POST['tipo'], trim($_POST['concepto']), (float)$_POST['monto'], trim($_POST['referencia'] ?? ''), $user['id']]);
    setFlash('success', 'Movimiento registrado.');
    redirect(BASE_URL . 'modules/caja/index.php');
}

// ════════════════════════════════════════════════════════
// CARGAR DATOS
// ════════════════════════════════════════════════════════

// Caja abierta (de cualquier fecha)
$cajaAbierta = $db->query("SELECT * FROM cajas WHERE estado='abierta' ORDER BY fecha DESC LIMIT 1")->fetch();

// Caja de hoy (abierta o cerrada)
$cajaHoy = $db->prepare("SELECT * FROM cajas WHERE fecha=? ORDER BY id DESC LIMIT 1");
$cajaHoy->execute([$hoy]);
$cajaHoy = $cajaHoy->fetch();

// La caja activa para mostrar movimientos es la abierta (aunque sea de ayer)
$cajaActiva = $cajaAbierta ?: $cajaHoy;

$movimientos = [];
if ($cajaActiva) {
    $m = $db->prepare("
        SELECT mv.*, CONCAT(u.nombre,' ',u.apellido) AS usuario_nombre
        FROM movimientos_caja mv
        JOIN usuarios u ON u.id = mv.usuario_id
        WHERE mv.caja_id = ?
        ORDER BY mv.created_at DESC
    ");
    $m->execute([$cajaActiva['id']]);
    $movimientos = $m->fetchAll();
}

// Historial — con ingresos y egresos calculados correctamente
$historial = $db->query("
    SELECT ca.*,
           CONCAT(u.nombre,' ',u.apellido) AS usuario_nombre,
           (SELECT COALESCE(SUM(monto),0) FROM movimientos_caja WHERE caja_id=ca.id AND tipo='ingreso') AS ing_real,
           (SELECT COALESCE(SUM(monto),0) FROM movimientos_caja WHERE caja_id=ca.id AND tipo='egreso')  AS egr_real
    FROM cajas ca
    JOIN usuarios u ON u.id = ca.usuario_id
    ORDER BY ca.fecha DESC, ca.id DESC
    LIMIT 30
")->fetchAll();

$densApertura = $cajaActiva ? json_decode($cajaActiva['denominaciones_apertura'] ?? '{}', true) : [];

// Totales del día activo
$totalIng    = array_sum(array_map(fn($mv) => $mv['tipo'] === 'ingreso' ? (float)$mv['monto'] : 0, $movimientos));
$totalEgr    = array_sum(array_map(fn($mv) => $mv['tipo'] === 'egreso'  ? (float)$mv['monto'] : 0, $movimientos));
$saldoActual = $cajaActiva ? round((float)$cajaActiva['saldo_inicial'] + $totalIng - $totalEgr, 2) : 0;

$pageTitle  = 'Caja diaria — ' . APP_NAME;
$breadcrumb = [['label' => 'Caja', 'url' => null]];
require_once __DIR__ . '/../../includes/header.php';

// ════════════════════════════════════════════════════════
// HELPER: Tabla de denominaciones
// ════════════════════════════════════════════════════════
function tablaDenominaciones(array $billetes, array $monedas, string $prefix, array $valores = []): string {
    $html = '<div class="row g-2">';
    $html .= '<div class="col-12"><div class="tr-section-title mb-1">💵 Billetes</div></div>';
    foreach ($billetes as $b) {
        $k   = denomKey('bil', $b);
        $val = (int)($valores[$k] ?? 0);
        $sub = number_format($val * $b, 2);
        $html .= "
        <div class='col-6 col-md-4'>
          <label class='tr-form-label small'>S/ {$b}.00 c/u</label>
          <div class='input-group input-group-sm'>
            <input type='number' name='{$prefix}{$k}' class='form-control denom-input text-center'
                   value='{$val}' min='0' data-valor='{$b}' autocomplete='off'
                   oninput='calcTotal(\"{$prefix}\")'>
            <span class='input-group-text subtotal-den' id='{$prefix}sub_{$k}' style='min-width:70px'>S/ {$sub}</span>
          </div>
        </div>";
    }
    $html .= '<div class="col-12 mt-2"><div class="tr-section-title mb-1">🪙 Monedas</div></div>';
    foreach ($monedas as $m) {
        $k   = denomKey('mon', $m);
        $val = (int)($valores[$k] ?? 0);
        $sub = number_format($val * $m, 2);
        $fmt = number_format($m, 2);
        $html .= "
        <div class='col-6 col-md-4'>
          <label class='tr-form-label small'>S/ {$fmt} c/u</label>
          <div class='input-group input-group-sm'>
            <input type='number' name='{$prefix}{$k}' class='form-control denom-input text-center'
                   value='{$val}' min='0' data-valor='{$m}' autocomplete='off'
                   oninput='calcTotal(\"{$prefix}\")'>
            <span class='input-group-text subtotal-den' id='{$prefix}sub_{$k}' style='min-width:70px'>S/ {$sub}</span>
          </div>
        </div>";
    }
    $html .= '</div>';
    return $html;
}

// ════════════════════════════════════════════════════════
// HELPER: Resumen de denominaciones guardadas
// ════════════════════════════════════════════════════════
function resumeDenominaciones(array $dens, array $billetes, array $monedas): string {
    $html  = '';
    $total = 0;
    foreach ($billetes as $b) {
        $k = denomKey('bil', $b);
        $c = (int)($dens[$k] ?? 0);
        if (!$c) continue;
        $sub   = $c * $b;
        $total += $sub;
        $html  .= "<div class='d-flex justify-content-between small py-1 border-bottom'>
                     <span>💵 S/ " . number_format($b, 2) . " × {$c}</span>
                     <span class='fw-semibold'>S/ " . number_format($sub, 2) . "</span>
                   </div>";
    }
    foreach ($monedas as $m) {
        $k = denomKey('mon', $m);
        $c = (int)($dens[$k] ?? 0);
        if (!$c) continue;
        $sub   = $c * $m;
        $total += $sub;
        $html  .= "<div class='d-flex justify-content-between small py-1 border-bottom'>
                     <span>🪙 S/ " . number_format($m, 2) . " × {$c}</span>
                     <span class='fw-semibold'>S/ " . number_format($sub, 2) . "</span>
                   </div>";
    }
    if ($html) {
        $html .= "<div class='d-flex justify-content-between fw-bold mt-2 pt-1 border-top'>
                    <span>Total:</span>
                    <span>S/ " . number_format($total, 2) . "</span>
                  </div>";
    } else {
        $html = '<p class="text-muted small mb-0">Sin denominaciones registradas</p>';
    }
    return $html;
}
?>

<h5 class="fw-bold mb-3">Caja diaria — <?= date('d/m/Y') ?></h5>

<?php
// ── ALERTA: caja abierta de otro día ──────────────────────
if ($cajaAbierta && $cajaAbierta['fecha'] !== $hoy): ?>
<div class="alert alert-warning d-flex align-items-start gap-3 mb-3">
  <span style="font-size:22px">⚠️</span>
  <div>
    <strong>Hay una caja abierta del <?= date('d/m/Y', strtotime($cajaAbierta['fecha'])) ?></strong><br>
    <span class="small">Debes cerrar esa caja antes de abrir la de hoy.
    No se puede abrir una nueva caja mientras haya una pendiente.</span>
    <div class="mt-2">
      <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modal-cerrar-caja">
        🔒 Cerrar caja del <?= date('d/m/Y', strtotime($cajaAbierta['fecha'])) ?>
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (!$cajaActiva): ?>
<!-- ══════════ PANTALLA ABRIR CAJA ══════════ -->
<div class="row justify-content-center">
  <div class="col-lg-7 col-md-9">
    <div class="tr-card">
      <div class="tr-card-header">
        <h6 class="mb-0 fw-semibold">🔓 Abrir caja del día</h6>
      </div>
      <div class="tr-card-body">
        <p class="text-muted small mb-3">Cuenta el efectivo físico en caja y registra la cantidad de cada denominación:</p>
        <form method="POST">
          <input type="hidden" name="action" value="abrir"/>
          <?= tablaDenominaciones($billetes, $monedas, 'ap_') ?>
          <div class="mt-4 p-3 rounded text-center" style="background:#eef2ff;border:2px solid #c7d2fe">
            <div class="text-muted small fw-semibold text-uppercase">Total a aperturar</div>
            <div class="fw-bold text-primary mt-1" style="font-size:32px" id="total-ap">S/ 0.00</div>
          </div>
          <button type="submit" class="btn btn-primary w-100 btn-lg mt-3">🔓 Abrir caja</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ══════════ CAJA ACTIVA / CERRADA ══════════ -->

<!-- KPIs -->
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="kpi-card">
      <div class="kpi-label">Saldo inicial</div>
      <div class="kpi-value text-success"><?= formatMoney($cajaActiva['saldo_inicial']) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card">
      <div class="kpi-label">Ingresos del día</div>
      <div class="kpi-value text-primary"><?= formatMoney($totalIng) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card">
      <div class="kpi-label">Egresos del día</div>
      <div class="kpi-value text-danger"><?= formatMoney($totalEgr) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card">
      <div class="kpi-label">Saldo esperado</div>
      <div class="kpi-value fw-bold"><?= formatMoney($saldoActual) ?></div>
      <?php if($cajaActiva['fecha'] !== $hoy): ?>
      <div class="small text-warning mt-1">📅 Caja del <?= date('d/m/Y', strtotime($cajaActiva['fecha'])) ?></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="row g-3">

  <!-- Columna principal: movimientos + historial -->
  <div class="col-lg-8">

    <!-- Movimientos -->
    <div class="tr-card mb-3">
      <div class="tr-card-header">
        <h6 class="mb-0 small fw-semibold">
          MOVIMIENTOS
          <?php if($cajaActiva['fecha'] !== $hoy): ?>
          <span class="badge bg-warning text-dark ms-1"><?= date('d/m/Y', strtotime($cajaActiva['fecha'])) ?></span>
          <?php endif; ?>
        </h6>
        <?php if($cajaActiva['estado'] === 'abierta'): ?>
        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modal-cerrar-caja">
          🔒 Cerrar caja
        </button>
        <?php else: ?>
        <span class="badge bg-secondary">
          CERRADA <?= $cajaActiva['fecha_cierre'] ? '— ' . date('H:i', strtotime($cajaActiva['fecha_cierre'])) : '' ?>
        </span>
        <?php endif; ?>
      </div>
      <div class="tr-card-body p-0" style="overflow:hidden">
        <div class="table-responsive-wrapper" style="overflow-x:auto">
          <table class="tr-table">
            <thead>
              <tr>
                <th>Tipo</th>
                <th>Concepto</th>
                <th>Monto</th>
                <th>Referencia</th>
                <th class="hide-mobile">Usuario</th>
                <th>Hora</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($movimientos as $mv): ?>
              <tr>
                <td>
                  <span class="badge bg-<?= $mv['tipo']==='ingreso'?'success':'danger' ?>">
                    <?= $mv['tipo']==='ingreso' ? '↑ Ingreso' : '↓ Egreso' ?>
                  </span>
                </td>
                <td class="small"><?= sanitize($mv['concepto']) ?></td>
                <td class="fw-semibold <?= $mv['tipo']==='ingreso'?'text-success':'text-danger' ?>">
                  <?= $mv['tipo']==='ingreso' ? '+' : '-' ?><?= formatMoney($mv['monto']) ?>
                </td>
                <td class="small">
                  <?php
                  $ref = $mv['referencia'] ?? '';
                  if (!$ref) {
                      echo '—';
                  } elseif (str_starts_with($ref, 'VTA-')) {
                      echo '<a href="'.BASE_URL.'modules/ventas/detalle.php?ref='.urlencode($ref).'" class="text-primary text-decoration-none fw-semibold">'.sanitize($ref).'</a>';
                  } elseif (str_starts_with($ref, 'OT-')) {
                      echo '<a href="'.BASE_URL.'modules/ot/index.php?q='.urlencode($ref).'" class="text-primary text-decoration-none fw-semibold">'.sanitize($ref).'</a>';
                  } else {
                      echo sanitize($ref);
                  }
                  ?>
                </td>
                <td class="small hide-mobile"><?= sanitize($mv['usuario_nombre']) ?></td>
                <td class="small text-muted"><?= date('H:i', strtotime($mv['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if(empty($movimientos)): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">Sin movimientos registrados</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Historial de cajas -->
    <div class="tr-card">
      <div class="tr-card-header">
        <h6 class="mb-0 small fw-semibold">HISTORIAL DE CAJAS</h6>
      </div>
      <div class="tr-card-body p-0" style="overflow:hidden">
        <div class="table-responsive-wrapper" style="overflow-x:auto">
          <table class="tr-table">
            <thead>
              <tr>
                <th>Fecha</th>
                <th class="hide-mobile">Usuario</th>
                <th>S. Inicial</th>
                <th>Ingresos</th>
                <th>Egresos</th>
                <th>S. Final</th>
                <th>Diferencia</th>
                <th>Estado</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($historial as $h):
                $dif  = (float)($h['diferencia_cierre'] ?? 0);
                $ing  = (float)$h['ing_real'];
                $egr  = (float)$h['egr_real'];
                $sf   = $h['estado'] === 'cerrada' ? (float)$h['saldo_final'] : ((float)$h['saldo_inicial'] + $ing - $egr);
              ?>
              <tr>
                <td class="fw-semibold small"><?= formatDate($h['fecha']) ?></td>
                <td class="small hide-mobile"><?= sanitize($h['usuario_nombre']) ?></td>
                <td><?= formatMoney($h['saldo_inicial']) ?></td>
                <td class="text-success fw-semibold"><?= formatMoney($ing) ?></td>
                <td class="text-danger fw-semibold"><?= formatMoney($egr) ?></td>
                <td class="fw-bold"><?= formatMoney($sf) ?></td>
                <td class="fw-semibold <?= $h['estado']==='cerrada' ? ($dif>0?'text-warning':($dif<0?'text-danger':'text-success')) : 'text-muted' ?>">
                  <?= $h['estado']==='cerrada' ? formatMoney($dif) : '—' ?>
                </td>
                <td>
                  <span class="badge bg-<?= $h['estado']==='cerrada'?'secondary':'success' ?>">
                    <?= $h['estado']==='cerrada' ? 'Cerrada' : 'Abierta' ?>
                  </span>
                </td>
                <td>
                  <button class="btn btn-sm btn-outline-secondary py-0"
                          onclick="verDetalleCaja(<?= $h['id'] ?>, '<?= date('d/m/Y', strtotime($h['fecha'])) ?>')"
                          title="Ver detalle">
                    <i data-feather="eye" style="width:13px;height:13px"></i>
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div><!-- /col-8 -->

  <!-- Columna lateral -->
  <div class="col-lg-4">

    <?php if($cajaActiva['estado'] === 'abierta'): ?>
    <!-- Nuevo movimiento manual -->
    <div class="tr-card mb-3">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">NUEVO MOVIMIENTO</h6></div>
      <div class="tr-card-body">
        <form method="POST">
          <input type="hidden" name="action" value="movimiento"/>
          <input type="hidden" name="caja_id" value="<?= $cajaActiva['id'] ?>"/>
          <div class="mb-2">
            <div class="d-flex gap-2">
              <div>
                <input type="radio" class="btn-check" name="tipo" id="t_ing" value="ingreso" checked>
                <label class="btn btn-outline-success btn-sm" for="t_ing">↑ Ingreso</label>
              </div>
              <div>
                <input type="radio" class="btn-check" name="tipo" id="t_egr" value="egreso">
                <label class="btn btn-outline-danger btn-sm" for="t_egr">↓ Egreso</label>
              </div>
            </div>
          </div>
          <div class="mb-2">
            <label class="tr-form-label">Concepto *</label>
            <input type="text" name="concepto" class="form-control form-control-sm" required placeholder="Ej: Compra pasta térmica"/>
          </div>
          <div class="mb-2">
            <label class="tr-form-label">Monto (S/) *</label>
            <input type="number" name="monto" class="form-control form-control-sm" step="0.01" min="0.01" required/>
          </div>
          <div class="mb-2">
            <label class="tr-form-label">Referencia</label>
            <input type="text" name="referencia" class="form-control form-control-sm" placeholder="Nro factura, OT, VTA..."/>
          </div>
          <button type="submit" class="btn btn-primary w-100 btn-sm">Registrar movimiento</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- Denominaciones de apertura -->
    <div class="tr-card <?= $cajaActiva['estado']==='abierta'?'':'mb-3' ?>">
      <div class="tr-card-header">
        <h6 class="mb-0 small fw-semibold">
          💰 DENOMINACIONES <?= $cajaActiva['estado']==='cerrada'?'— APERTURA':'' ?>
        </h6>
      </div>
      <div class="tr-card-body p-3">
        <?= resumeDenominaciones($densApertura ?: [], $billetes, $monedas) ?>
      </div>
    </div>

    <!-- Si cerrada: mostrar denominaciones de cierre también -->
    <?php if($cajaActiva['estado']==='cerrada'):
      $densCierre = json_decode($cajaActiva['denominaciones_cierre'] ?? '{}', true);
    ?>
    <div class="tr-card mt-3">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">💰 DENOMINACIONES — CIERRE</h6></div>
      <div class="tr-card-body p-3">
        <?= resumeDenominaciones($densCierre ?: [], $billetes, $monedas) ?>
        <?php $dif = (float)($cajaActiva['diferencia_cierre'] ?? 0); ?>
        <div class="mt-2 p-2 rounded text-center fw-bold <?= $dif==0?'bg-success bg-opacity-10 text-success':($dif>0?'bg-warning bg-opacity-10 text-warning':'bg-danger bg-opacity-10 text-danger') ?>">
          Diferencia: <?= formatMoney($dif) ?>
          <?= $dif==0?' ✅':($dif>0?' ⚠️ Sobrante':' ❌ Faltante') ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<!-- ══════ MODAL CERRAR CAJA ══════ -->
<div class="modal fade" id="modal-cerrar-caja" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:#1a1a2e;color:#fff">
        <h6 class="modal-title fw-bold">
          🔒 Cierre de caja —
          <?= $cajaActiva ? date('d/m/Y', strtotime($cajaActiva['fecha'])) : date('d/m/Y') ?>
        </h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="cerrar"/>
        <input type="hidden" name="caja_id" value="<?= $cajaActiva['id'] ?>"/>
        <div class="modal-body">

          <!-- Resumen del día -->
          <div class="row g-2 mb-4">
            <div class="col-4">
              <div class="p-2 bg-light rounded text-center">
                <div class="small text-muted">Saldo inicial</div>
                <div class="fw-bold text-success"><?= formatMoney($cajaActiva['saldo_inicial']) ?></div>
              </div>
            </div>
            <div class="col-4">
              <div class="p-2 bg-light rounded text-center">
                <div class="small text-muted">Ingresos</div>
                <div class="fw-bold text-primary"><?= formatMoney($totalIng) ?></div>
              </div>
            </div>
            <div class="col-4">
              <div class="p-2 bg-light rounded text-center">
                <div class="small text-muted">Egresos</div>
                <div class="fw-bold text-danger"><?= formatMoney($totalEgr) ?></div>
              </div>
            </div>
          </div>

          <p class="text-muted small mb-3">
            <strong>Cuenta el efectivo físico</strong> que tienes en caja ahora mismo e ingresa la cantidad de cada billete y moneda:
          </p>

          <?= tablaDenominaciones($billetes, $monedas, 'ci_') ?>

          <!-- Comparativo -->
          <div class="row g-2 mt-4">
            <div class="col-md-4">
              <div class="p-3 rounded text-center" style="background:#f0fdf4;border:1px solid #86efac">
                <div class="small text-muted fw-semibold">Saldo esperado en BD</div>
                <div class="fw-bold fs-5 text-success"><?= formatMoney($saldoActual) ?></div>
                <div class="small text-muted">S.inicial + ingresos - egresos</div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="p-3 rounded text-center" style="background:#eef2ff;border:1px solid #c7d2fe">
                <div class="small text-muted fw-semibold">Contado en caja</div>
                <div class="fw-bold fs-5 text-primary" id="total-ci">S/ 0.00</div>
                <div class="small text-muted">Lo que acabas de contar</div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="p-3 rounded text-center" id="div-diferencia" style="background:#f3f4f6;border:1px solid #e5e7eb">
                <div class="small text-muted fw-semibold">Diferencia</div>
                <div class="fw-bold fs-5" id="txt-diferencia">S/ 0.00</div>
                <div class="small text-muted" id="lbl-diferencia">Sin diferencia</div>
              </div>
            </div>
          </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning fw-bold">
            🔒 Confirmar cierre de caja
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php endif; ?>

<!-- ══════ MODAL DETALLE CAJA (historial) ══════ -->
<div class="modal fade" id="modal-detalle-caja" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold" id="titulo-detalle-caja">Detalle de caja</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="body-detalle-caja">
        <div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></div>
      </div>
    </div>
  </div>
</div>

<!-- ══════ MODAL UNIVERSAL DE DOCUMENTOS ══════ -->
<div class="modal fade" id="modal-doc-universal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold" id="titulo-doc-universal">Detalle</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="body-doc-universal">
        <div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>
      </div>
    </div>
  </div>
</div>
<?php
// JS correcto — sin heredoc, con PHP directo
$esperado = isset($saldoActual) ? (float)$saldoActual : 0;
?>
<script>
// Variables PHP → JS (forma correcta, sin heredoc)
const ESPERADO_CAJA = <?= json_encode($esperado) ?>;
const BASE_URL_JS   = <?= json_encode(BASE_URL) ?>;

// ── Cálculo denominaciones ───────────────────────────────
function calcTotal(prefix) {
  let total = 0;
  document.querySelectorAll('input.denom-input').forEach(inp => {
    if (!inp.name.startsWith(prefix)) return;
    const cant  = parseInt(inp.value)  || 0;
    const valor = parseFloat(inp.dataset.valor) || 0;
    const sub   = cant * valor;
    total += sub;
    const key  = inp.name.slice(prefix.length);
    const span = document.getElementById(prefix + 'sub_' + key);
    if (span) span.textContent = 'S/ ' + sub.toFixed(2);
  });
  const pfx = prefix.replace(/_$/, '');
  const el  = document.getElementById('total-' + pfx);
  if (el) el.textContent = 'S/ ' + total.toFixed(2);

  if (prefix === 'ci_') {
    const dif   = total - ESPERADO_CAJA;
    const difEl = document.getElementById('txt-diferencia');
    const divEl = document.getElementById('div-diferencia');
    const lblEl = document.getElementById('lbl-diferencia');
    if (difEl) {
      difEl.textContent = 'S/ ' + dif.toFixed(2);
      difEl.className   = 'fw-bold fs-5 ' + (dif===0?'text-success':dif>0?'text-warning':'text-danger');
    }
    if (divEl) {
      divEl.style.background  = dif===0?'#f0fdf4':dif>0?'#fef9c3':'#fee2e2';
      divEl.style.borderColor = dif===0?'#86efac':dif>0?'#fde047':'#fca5a5';
    }
    if (lblEl) lblEl.textContent = dif===0?'✅ Cuadra perfectamente':dif>0?'⚠️ Sobrante en caja':'❌ Faltante en caja';
  }
}

document.addEventListener('DOMContentLoaded', () => {
  calcTotal('ap_');
  calcTotal('ci_');
});

// ── Abrir modal de detalle de caja (historial) ───────────
async function verDetalleCaja(cajaId, fecha) {
  const modalEl = document.getElementById('modal-detalle-caja');
  const bodyEl  = document.getElementById('body-detalle-caja');
  const titleEl = document.getElementById('titulo-detalle-caja');

  titleEl.textContent = 'Detalle de caja — ' + fecha;
  bodyEl.innerHTML    = '<div class="text-center py-5"><div class="spinner-border text-primary"></div><div class="mt-2 text-muted small">Cargando...</div></div>';

  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  modal.show();

  try {
    const resp = await fetch(BASE_URL_JS + 'modules/caja/detalle_ajax.php?id=' + cajaId);
    if (!resp.ok) throw new Error('HTTP ' + resp.status);
    const html = await resp.text();
    bodyEl.innerHTML = html;

    // Inicializar tabs de Bootstrap dentro del modal recién cargado
    bodyEl.querySelectorAll('[data-bs-toggle="tab"]').forEach(btn => {
      btn.addEventListener('click', e => {
        e.preventDefault();
        const target = document.querySelector(btn.dataset.bsTarget);
        if (!target) return;
        bodyEl.querySelectorAll('.tab-pane').forEach(p => { p.classList.remove('show','active'); });
        bodyEl.querySelectorAll('[data-bs-toggle="tab"]').forEach(b => b.classList.remove('active'));
        target.classList.add('show','active');
        btn.classList.add('active');
      });
    });

    feather.replace();
  } catch(err) {
    bodyEl.innerHTML = '<div class="alert alert-danger m-3">Error al cargar: ' + err.message + '</div>';
  }
}

// ── Navegación entre paneles dentro del modal ────────────
// Esta función es llamada por los botones "Ver detalle →" generados en detalle_ajax.php
window.mostrarPanel = function(panelId) {
  const body = document.getElementById('body-detalle-caja');
  if (!body) return;
  body.querySelectorAll('.det-panel').forEach(p => p.classList.remove('active'));
  const target = body.querySelector('#' + panelId);
  if (target) {
    target.classList.add('active');
    // Scroll al top del modal body
    body.scrollTop = 0;
    feather.replace();
  }
};
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
