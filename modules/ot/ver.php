<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
requireLogin();

$db  = getDB();
$id  = (int)($_GET['id'] ?? 0);
$user = currentUser();

$ot = $db->prepare("
  SELECT ot.*, c.nombre as cliente_nombre, c.telefono, c.whatsapp, c.email as cliente_email,
         c.ruc_dni, te.nombre as tipo_equipo, e.marca, e.modelo, e.serial, e.color,
         CONCAT(u.nombre,' ',u.apellido) as tecnico_nombre,
         CONCAT(uc.nombre,' ',uc.apellido) as creador_nombre
  FROM ordenes_trabajo ot
  JOIN clientes c    ON c.id  = ot.cliente_id
  JOIN equipos e     ON e.id  = ot.equipo_id
  JOIN tipos_equipo te ON te.id = e.tipo_equipo_id
  LEFT JOIN usuarios u  ON u.id  = ot.tecnico_id
  LEFT JOIN usuarios uc ON uc.id = ot.usuario_creador_id
  WHERE ot.id = ?
");
$ot->execute([$id]);
$ot = $ot->fetch();

if (!$ot) { setFlash('danger','OT no encontrada'); redirect(BASE_URL . 'modules/ot/index.php'); }

// Cargar estados desde BD
$estadosOT = getEstadosOT($db, true);

// Si el estado actual de la OT no está en la lista (ej: estado inactivo o recién creado), agregarlo
if (!isset($estadosOT[$ot['estado']])) {
    $estadoActual = $db->prepare("SELECT codigo, nombre as label, color, icono as icon FROM estados_orden WHERE codigo=?");
    $estadoActual->execute([$ot['estado']]);
    $estadoData = $estadoActual->fetch();
    if ($estadoData) {
        $estadosOT[$estadoData['codigo']] = [
            'label' => $estadoData['label'],
            'color' => $estadoData['color'],
            'icon' => $estadoData['icon']
        ];
    }
}

// Inicializar cache de estadoOTBadge con todos los estados
estadoOTBadge('', $db);

// Cambio de estado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'cambiar_estado') {
        $nuevo  = $_POST['nuevo_estado'] ?? '';
        $coment = trim($_POST['comentario'] ?? '');
        
        if (empty($nuevo)) {
            setFlash('danger', 'Debe seleccionar un estado');
            redirect(BASE_URL . 'modules/ot/ver.php?id=' . $id);
        }
        
        // Validar que el estado existe en la BD
        $estadoValido = $db->prepare("SELECT COUNT(*) FROM estados_orden WHERE codigo = ? AND activo = 1");
        $estadoValido->execute([$nuevo]);
        
        if ($estadoValido->fetchColumn() > 0) {
            // Ejecutar UPDATE
            try {
                $stmt = $db->prepare("UPDATE ordenes_trabajo SET estado = ? WHERE id = ?");
                $stmt->execute([$nuevo, $id]);
                
                // Si es entregado, actualizar fecha
                if ($nuevo === 'entregado') {
                    $db->prepare("UPDATE ordenes_trabajo SET fecha_entrega = NOW() WHERE id = ?")->execute([$id]);
                }
                
                $db->prepare("INSERT INTO historial_ot (ot_id,usuario_id,estado_antes,estado_nuevo,comentario) VALUES (?,?,?,?,?)")
                   ->execute([$id, $user['id'], $ot['estado'], $nuevo, $coment]);
                
                // Obtener nombre del estado para el mensaje
                $nombreEstado = $db->prepare("SELECT nombre FROM estados_orden WHERE codigo = ?");
                $nombreEstado->execute([$nuevo]);
                $nombreEstado = $nombreEstado->fetchColumn();
                
                setFlash('success', 'Estado actualizado a: ' . $nombreEstado);
            } catch (PDOException $e) {
                setFlash('danger', 'Error al actualizar estado');
            }
            redirect(BASE_URL . 'modules/ot/ver.php?id=' . $id);
        } else {
            setFlash('danger', 'Estado no válido');
            redirect(BASE_URL . 'modules/ot/ver.php?id=' . $id);
        }
    }
    if ($_POST['action'] === 'aprobar_presupuesto') {
        $db->prepare("UPDATE ordenes_trabajo SET presupuesto_aprobado=1, fecha_aprobacion=NOW(), aprobado_por=? WHERE id=?")
           ->execute([$_POST['metodo_aprobacion'] ?? 'firma', $id]);
        setFlash('success','Presupuesto aprobado.');
        redirect(BASE_URL . 'modules/ot/ver.php?id=' . $id);
    }
    if ($_POST['action'] === 'registrar_adelanto') {
        $monto_adelanto = (float)($_POST['monto_adelanto'] ?? 0);
        if ($monto_adelanto > 0) {
            $db->prepare("UPDATE ordenes_trabajo SET adelanto=?, metodo_adelanto=?, fecha_adelanto=NOW() WHERE id=?")
               ->execute([$monto_adelanto, $_POST['metodo_adelanto'], $id]);
            $cajaAbierta = $db->prepare("SELECT id FROM cajas WHERE fecha=CURDATE() AND estado='abierta' ORDER BY id DESC LIMIT 1");
            $cajaAbierta->execute();
            $caja = $cajaAbierta->fetchColumn();
            if ($caja) {
                $db->prepare("INSERT INTO movimientos_caja (caja_id,tipo,concepto,monto,referencia,usuario_id) VALUES (?,?,?,?,?,?)")
                   ->execute([$caja,'ingreso','Adelanto reparación ' . $ot['codigo_ot'], $monto_adelanto, $ot['codigo_ot'], $user['id']]);
            }
            setFlash('success','Adelanto registrado correctamente.');
        }
        redirect(BASE_URL . 'modules/ot/ver.php?id=' . $id);
    }
    if ($_POST['action'] === 'registrar_pago') {
        $db->prepare("UPDATE ordenes_trabajo SET pagado=1, metodo_pago=?, fecha_pago=NOW() WHERE id=?")
           ->execute([$_POST['metodo_pago'], $id]);
        // Movimiento de caja
        $cajaAbierta = $db->prepare("SELECT id FROM cajas WHERE fecha=CURDATE() AND estado='abierta' ORDER BY id DESC LIMIT 1");
        $cajaAbierta->execute();
        $caja = $cajaAbierta->fetchColumn();
        if ($caja) {
            $db->prepare("INSERT INTO movimientos_caja (caja_id,tipo,concepto,monto,referencia,usuario_id) VALUES (?,?,?,?,?,?)")
               ->execute([$caja,'ingreso','Pago reparación ' . $ot['codigo_ot'], $ot['precio_final'], $ot['codigo_ot'], $user['id']]);
        }
        setFlash('success','Pago registrado correctamente.');

        // Emitir comprobante electrónico automáticamente al registrar pago
        try {
            require_once __DIR__ . '/../../config/sunat.php';
            require_once __DIR__ . '/../../includes/sunat/SunatService.php';

            $tipo_doc = $_POST['tipo_comprobante'] ?? 'boleta';
            if (in_array($tipo_doc, ['boleta','factura'], true)) {
                // Obtener cliente para validar
                $cli = $db->prepare("SELECT * FROM clientes WHERE id=?");
                $cli->execute([$ot['cliente_id']]);
                $cli = $cli->fetch();
                $doc = trim($cli['ruc_dni'] ?? '');

                // Validar factura requiere RUC
                if ($tipo_doc === 'factura' && strlen($doc) !== 11) {
                    setFlash('warning', 'Pago registrado. No se emitió factura: el cliente no tiene RUC válido.');
                    redirect(BASE_URL . 'modules/ot/ver.php?id=' . $id);
                }

                // Obtener correlativo
                $correlativo = siguienteCorrelativo($db, $tipo_doc);
                if (!$correlativo) {
                    setFlash('warning', 'Pago registrado. No se emitió comprobante: no hay serie activa para ' . $tipo_doc . '.');
                    redirect(BASE_URL . 'modules/ot/ver.php?id=' . $id);
                }

                // Calcular subtotal e IGV desde precio_final
                $total   = (float)$ot['precio_final'];
                $subtotal = round($total / 1.18, 2);
                $igv      = round($total - $subtotal, 2);

                // Crear venta
                $codigoVenta = generarCodigoVenta($db);
                $db->prepare("INSERT INTO ventas (codigo,cliente_id,usuario_id,tipo_doc,serie_doc,num_doc,subtotal,igv,descuento,total,metodo_pago,monto_pagado,estado) VALUES (?,?,?,?,?,?,?,?,0,?,?,?,?)")
                   ->execute([$codigoVenta, $ot['cliente_id'], $user['id'], $tipo_doc, $correlativo['serie'], (string)$correlativo['numero'], $subtotal, $igv, $total, $_POST['metodo_pago'], $total, 'completada']);
                $ventaId = $db->lastInsertId();

                // Insertar ítems desde ot_repuestos
                $repOT = $db->prepare("SELECT * FROM ot_repuestos WHERE ot_id=?");
                $repOT->execute([$id]);
                $repOT = $repOT->fetchAll();

                if ($repOT) {
                    foreach ($repOT as $r) {
                        $db->prepare("INSERT INTO venta_detalle (venta_id,producto_id,cantidad,precio_unit,descuento,subtotal) VALUES (?,?,?,?,0,?)")
                           ->execute([$ventaId, $r['producto_id'] ?: null, $r['cantidad'], $r['precio_unit'], $r['subtotal']]);
                    }
                } else {
                    // Si no hay repuestos, insertar el servicio como ítem genérico
                    $db->prepare("INSERT INTO venta_detalle (venta_id,producto_id,cantidad,precio_unit,descuento,subtotal) VALUES (?,NULL,1,?,0,?)")
                       ->execute([$ventaId, $total, $total]);
                }

                // Generar XML
                $svc = new SunatService($db);
                $r   = $svc->generarXml($ventaId);
                if ($r['ok']) {
                    setFlash('success', 'Pago registrado y comprobante emitido correctamente.');
                } else {
                    setFlash('warning', 'Pago registrado. El comprobante quedó pendiente: ' . $r['mensaje']);
                }
            }
        } catch (Throwable $e) {
            setFlash('warning', 'Pago registrado. Error al emitir comprobante: ' . $e->getMessage());
        }

        redirect(BASE_URL . 'modules/ot/ver.php?id=' . $id);
    }
}

// Fotos
$fotos = $db->prepare("SELECT * FROM fotos_ot WHERE ot_id=? ORDER BY created_at");
$fotos->execute([$id]);
$fotos = $fotos->fetchAll();

// Historial
$historial = $db->prepare("
  SELECT h.*, CONCAT(u.nombre,' ',u.apellido) as usuario_nombre
  FROM historial_ot h
  JOIN usuarios u ON u.id=h.usuario_id
  WHERE h.ot_id=? ORDER BY h.created_at
");
$historial->execute([$id]);
$historial = $historial->fetchAll();

// Repuestos
$repuestos = $db->prepare("SELECT * FROM ot_repuestos WHERE ot_id=?");
$repuestos->execute([$id]);
$repuestos = $repuestos->fetchAll();

// Checklist
$checklist = $ot['checklist'] ? json_decode($ot['checklist'], true) : [];

// Técnicos para reasignar
$tecnicos = $db->query("SELECT id,CONCAT(nombre,' ',apellido) as nombre FROM usuarios WHERE rol='tecnico' AND activo=1")->fetchAll();

$pageTitle  = $ot['codigo_ot'] . ' — ' . APP_NAME;
$breadcrumb = [
    ['label'=>'Órdenes de trabajo','url'=>BASE_URL.'modules/ot/index.php'],
    ['label'=>$ot['codigo_ot'],'url'=>null],
];
require_once __DIR__ . '/../../includes/header.php';

$estado = $ot['estado'];
?>

<!-- Header OT -->
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0"><?= sanitize($ot['codigo_ot']) ?></h4>
    <div class="d-flex align-items-center gap-2 mt-1">
      <?= estadoOTBadge($estado, $db) ?>
      <span class="text-muted small">Creada <?= formatDateTime($ot['created_at']) ?></span>
      <span class="badge bg-light text-dark border small" title="Código para el cliente">
        🔑 <?= sanitize($ot['codigo_publico']) ?>
      </span>
    </div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?= BASE_URL ?>modules/ot/editar.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm">
      <i data-feather="edit-2" style="width:14px;height:14px"></i> Editar
    </a>
    <a href="<?= BASE_URL ?>modules/ot/pdf.php?id=<?= $id ?>" target="_blank" class="btn btn-outline-danger btn-sm">
      <i data-feather="file-text" style="width:14px;height:14px"></i> PDF / Imprimir
    </a>
    <?php if ($ot['whatsapp']): ?>
    <a href="https://wa.me/<?= preg_replace('/\D/','',$ot['whatsapp']) ?>?text=Hola+<?= urlencode($ot['cliente_nombre']) ?>%2C+su+equipo+est%C3%A1+<?= urlencode($estadosOT[$estado]['label'] ?? $estado) ?>+%28OT:+<?= $ot['codigo_ot'] ?>%29"
       target="_blank" class="btn btn-success btn-sm">
      <i data-feather="message-circle" style="width:14px;height:14px"></i> WhatsApp
    </a>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <!-- COLUMNA PRINCIPAL -->
  <div class="col-lg-8">

    <!-- Info cliente y equipo -->
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <div class="tr-card h-100">
          <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">CLIENTE</h6></div>
          <div class="tr-card-body">
            <div class="fw-semibold"><?= sanitize($ot['cliente_nombre']) ?></div>
            <?php if ($ot['ruc_dni']): ?><div class="text-muted small">DNI/RUC: <?= sanitize($ot['ruc_dni']) ?></div><?php endif; ?>
            <?php if ($ot['telefono']): ?><div class="small">📞 <?= sanitize($ot['telefono']) ?></div><?php endif; ?>
            <?php if ($ot['whatsapp']): ?><div class="small">💬 <?= sanitize($ot['whatsapp']) ?></div><?php endif; ?>
            <?php if ($ot['cliente_email']): ?><div class="small">✉️ <?= sanitize($ot['cliente_email']) ?></div><?php endif; ?>
            <a href="<?= BASE_URL ?>modules/clientes/ver.php?id=<?= $ot['cliente_id'] ?>" class="btn btn-xs btn-outline-primary btn-sm mt-2 py-0">Ver historial</a>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="tr-card h-100">
          <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">EQUIPO</h6></div>
          <div class="tr-card-body">
            <div class="fw-semibold"><?= sanitize($ot['tipo_equipo']) ?></div>
            <?php if ($ot['marca'] || $ot['modelo']): ?><div><?= sanitize($ot['marca'].' '.$ot['modelo']) ?></div><?php endif; ?>
            <?php if ($ot['serial']): ?><div class="text-muted small">S/N: <code><?= sanitize($ot['serial']) ?></code></div><?php endif; ?>
            <?php if ($ot['color']): ?><div class="text-muted small">Color: <?= sanitize($ot['color']) ?></div><?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Problema y diagnóstico -->
    <div class="tr-card mb-3">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">DIAGNÓSTICO</h6></div>
      <div class="tr-card-body">
        <div class="tr-section-title">Problema reportado</div>
        <p class="mb-3"><?= nl2br(sanitize($ot['problema_reportado'])) ?></p>
        <?php if ($ot['diagnostico_inicial']): ?>
        <div class="tr-section-title">Diagnóstico inicial</div>
        <p class="mb-3"><?= nl2br(sanitize($ot['diagnostico_inicial'])) ?></p>
        <?php endif; ?>
        <?php if ($ot['diagnostico_tecnico']): ?>
        <div class="tr-section-title">Diagnóstico técnico</div>
        <p class="mb-0"><?= nl2br(sanitize($ot['diagnostico_tecnico'])) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Fotos -->
    <?php if ($fotos): ?>
    <div class="tr-card mb-3">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">FOTOS DEL EQUIPO</h6></div>
      <div class="tr-card-body">
        <div class="foto-preview-grid">
          <?php foreach ($fotos as $foto): ?>
          <div class="foto-preview-item">
            <a href="<?= UPLOAD_URL . $foto['ruta'] ?>" target="_blank">
              <img src="<?= UPLOAD_URL . $foto['ruta'] ?>" alt="<?= sanitize($foto['descripcion'] ?? 'foto') ?>">
            </a>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Checklist -->
    <?php if ($checklist): ?>
    <div class="tr-card mb-3">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">CHECKLIST FÍSICO</h6></div>
      <div class="tr-card-body">
        <?php
        // Iterar sobre las claves reales guardadas en el JSON, excluyendo la observación
        foreach ($checklist as $k => $val):
            if ($k === '_observacion') continue;
            $badge = $val==='bueno'?'success':($val==='malo'?'danger':'secondary');
        ?>
        <div class="checklist-item">
          <span class="small"><?= sanitize($k) ?></span>
          <span class="badge bg-<?= $badge ?>"><?= ucfirst($val) ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (!empty($checklist['_observacion'])): ?>
        <div class="mt-2 p-2 bg-light rounded small"><?= sanitize($checklist['_observacion']) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Repuestos usados -->
    <?php if ($repuestos): ?>
    <div class="tr-card mb-3">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">REPUESTOS UTILIZADOS</h6></div>
      <div class="tr-card-body p-0">
        <table class="tr-table">
          <thead><tr><th>Descripción</th><th>Cant.</th><th>P. Unit.</th><th>Subtotal</th></tr></thead>
          <tbody>
            <?php foreach ($repuestos as $r): ?>
            <tr>
              <td><?= sanitize($r['descripcion']) ?></td>
              <td><?= $r['cantidad'] ?></td>
              <td><?= formatMoney($r['precio_unit']) ?></td>
              <td><?= formatMoney($r['subtotal']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Timeline -->
    <div class="tr-card">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">HISTORIAL / TIMELINE</h6></div>
      <div class="tr-card-body">
        <div class="ot-timeline">
          <?php foreach ($historial as $h): ?>
          <div class="ot-timeline-item">
            <div class="fw-semibold small">
              <?= isset($estadosOT[$h['estado_nuevo']]) ? $estadosOT[$h['estado_nuevo']]['label'] : sanitize($h['estado_nuevo']) ?>
            </div>
            <div class="text-muted" style="font-size:12px"><?= sanitize($h['usuario_nombre']) ?> — <?= formatDateTime($h['created_at']) ?></div>
            <?php if ($h['comentario']): ?>
            <div class="small text-muted mt-1"><?= sanitize($h['comentario']) ?></div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </div><!-- /col-8 -->

  <!-- COLUMNA DERECHA -->
  <div class="col-lg-4">

    <!-- Resumen financiero -->
    <div class="tr-card mb-3">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">RESUMEN FINANCIERO</h6></div>
      <div class="tr-card-body">
        <div class="d-flex justify-content-between small mb-1"><span>Repuestos:</span><span><?= formatMoney($ot['costo_repuestos']) ?></span></div>
        <div class="d-flex justify-content-between small mb-1"><span>Mano de obra:</span><span><?= formatMoney($ot['costo_mano_obra']) ?></span></div>
        <?php if ($ot['descuento'] > 0): ?>
        <div class="d-flex justify-content-between small mb-1 text-danger"><span>Descuento:</span><span>-<?= formatMoney($ot['descuento']) ?></span></div>
        <?php endif; ?>
        <hr class="my-2">
        <div class="d-flex justify-content-between fw-bold"><span>Total:</span><span><?= formatMoney($ot['precio_final']) ?></span></div>
        <?php if (($ot['adelanto'] ?? 0) > 0): ?>
        <div class="d-flex justify-content-between small mb-1 text-success mt-1"><span>Adelanto (<?= ucfirst($ot['metodo_adelanto']) ?>):</span><span>-<?= formatMoney($ot['adelanto']) ?></span></div>
        <div class="d-flex justify-content-between small fw-semibold"><span>Saldo pendiente:</span><span><?= formatMoney(max(0, $ot['precio_final'] - $ot['adelanto'])) ?></span></div>
        <?php endif; ?>
        <?php if ($ot['pagado']): ?>
        <div class="alert alert-success py-1 mt-2 small mb-0">✅ Pagado — <?= ucfirst($ot['metodo_pago']) ?></div>
        <?php else: ?>
        <div class="alert alert-warning py-1 mt-2 small mb-0">⏳ Pago pendiente</div>
        <?php endif; ?>

        <!-- Presupuesto aprobado? -->
        <?php if (!$ot['presupuesto_aprobado'] && $ot['precio_final'] > 0): ?>
        <form method="POST" class="mt-2">
          <input type="hidden" name="action" value="aprobar_presupuesto"/>
          <select name="metodo_aprobacion" class="form-select form-select-sm mb-2">
            <option value="firma">Firma digital</option>
            <option value="whatsapp">WhatsApp</option>
            <option value="llamada">Llamada</option>
          </select>
          <button type="submit" class="btn btn-sm btn-outline-success w-100">✅ Marcar presupuesto aprobado</button>
        </form>
        <?php elseif ($ot['presupuesto_aprobado']): ?>
        <div class="text-success small mt-1">✅ Presupuesto aprobado <?= $ot['aprobado_por'] ? '('.$ot['aprobado_por'].')' : '' ?></div>
        <?php endif; ?>

        <!-- Registrar adelanto -->
        <?php if (!$ot['pagado'] && ($ot['adelanto'] ?? 0) == 0): ?>
        <form method="POST" class="mt-2">
          <input type="hidden" name="action" value="registrar_adelanto"/>
          <div class="input-group input-group-sm mb-2">
            <span class="input-group-text">S/</span>
            <input type="number" name="monto_adelanto" class="form-control" step="0.01" min="0.01" placeholder="Monto adelanto" required/>
          </div>
          <select name="metodo_adelanto" class="form-select form-select-sm mb-2">
            <option value="efectivo">Efectivo</option>
            <option value="yape">Yape</option>
            <option value="plin">Plin</option>
            <option value="tarjeta">Tarjeta</option>
            <option value="transferencia">Transferencia</option>
          </select>
          <button type="submit" class="btn btn-sm btn-outline-primary w-100">💵 Registrar adelanto</button>
        </form>
        <?php endif; ?>

        <!-- Registrar pago -->
        <?php if (!$ot['pagado'] && $ot['presupuesto_aprobado']): ?>
        <form method="POST" class="mt-2">
          <input type="hidden" name="action" value="registrar_pago"/>
          <select name="metodo_pago" class="form-select form-select-sm mb-2">
            <option value="efectivo">Efectivo</option>
            <option value="yape">Yape</option>
            <option value="plin">Plin</option>
            <option value="tarjeta">Tarjeta</option>
            <option value="transferencia">Transferencia</option>
          </select>
          <select name="tipo_comprobante" class="form-select form-select-sm mb-2">
            <option value="boleta">Boleta</option>
            <option value="factura">Factura</option>
            <option value="">Sin comprobante</option>
          </select>
          <button type="submit" class="btn btn-sm btn-primary w-100">💰 Registrar pago</button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Cambiar estado -->
    <?php if (!in_array($estado,['entregado','cancelado'])): ?>
    <div class="tr-card mb-3">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">CAMBIAR ESTADO</h6></div>
      <div class="tr-card-body">
        <form method="POST">
          <input type="hidden" name="action" value="cambiar_estado"/>
          <div class="mb-2">
            <label class="tr-form-label small">Nuevo estado</label>
            <div class="input-group input-group-sm">
              <select name="nuevo_estado" id="sel-nuevo-estado" class="form-select form-select-sm">
                <?php foreach ($estadosOT as $k => $v): ?>
                <option value="<?= $k ?>" <?= $k === $estado ? 'selected' : '' ?>><?= $v['label'] ?></option>
                <?php endforeach; ?>
              </select>
              <button type="button" class="btn btn-outline-success btn-sm" title="Agregar nuevo estado"
                      onclick="agregarEstado()">
                <i data-feather="plus" style="width:14px;height:14px"></i>
              </button>
              <button type="button" class="btn btn-outline-primary btn-sm" title="Editar estado seleccionado"
                      onclick="editarEstadoSeleccionado()">
                <i data-feather="edit-2" style="width:14px;height:14px"></i>
              </button>
              <button type="button" class="btn btn-outline-danger btn-sm" title="Eliminar estado seleccionado"
                      onclick="eliminarEstadoSeleccionado()">
                <i data-feather="trash-2" style="width:14px;height:14px"></i>
              </button>
            </div>
          </div>
          <div class="mb-2">
            <textarea name="comentario" class="form-control form-control-sm" rows="2" placeholder="Comentario opcional..."></textarea>
          </div>
          <button type="submit" class="btn btn-primary btn-sm w-100">Actualizar estado</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- Técnico y fechas -->
    <div class="tr-card mb-3">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">ASIGNACIÓN</h6></div>
      <div class="tr-card-body">
        <div class="small mb-1"><strong>Técnico:</strong> <?= sanitize($ot['tecnico_nombre'] ?? 'Sin asignar') ?></div>
        <div class="small mb-1"><strong>Creado por:</strong> <?= sanitize($ot['creador_nombre']) ?></div>
        <div class="small mb-1"><strong>F. ingreso:</strong> <?= formatDateTime($ot['fecha_ingreso']) ?></div>
        <div class="small mb-1"><strong>F. estimada:</strong>
          <?php if ($ot['fecha_estimada']): ?>
            <span class="<?= $ot['fecha_estimada'] < date('Y-m-d') && !in_array($estado,['listo','entregado']) ? 'text-danger fw-semibold' : '' ?>">
              <?= formatDate($ot['fecha_estimada']) ?>
            </span>
          <?php else: ?> — <?php endif; ?>
        </div>
        <?php if ($ot['fecha_entrega']): ?>
        <div class="small mb-1"><strong>F. entrega:</strong> <?= formatDateTime($ot['fecha_entrega']) ?></div>
        <?php endif; ?>
        <div class="small"><strong>Garantía:</strong> <?= $ot['garantia_dias'] ?> días</div>
      </div>
    </div>

    <!-- Firma -->
    <?php if ($ot['firma_cliente']): ?>
    <div class="tr-card">
      <div class="tr-card-header"><h6 class="mb-0 small fw-semibold">FIRMA DEL CLIENTE</h6></div>
      <div class="tr-card-body firma-preview">
        <img src="<?= $ot['firma_cliente'] ?>" alt="Firma" class="img-fluid"/>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<!-- Modal para agregar nuevo estado -->
<div class="modal fade" id="modal-agregar-estado" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title">➕ Nuevo estado de orden</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="tr-form-label small">Nombre del estado *</label>
          <input type="text" id="input-nuevo-estado" class="form-control form-control-sm" placeholder="Ej: Esperando repuesto, En garantía..." required/>
          <div class="text-muted small mt-1">El código se generará automáticamente</div>
        </div>

        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="tr-form-label small">Color del badge</label>
            <select id="input-color-estado" class="form-select form-select-sm">
              <option value="secondary" selected>⚪ Gris</option>
              <option value="primary">🔵 Azul</option>
              <option value="success">🟢 Verde</option>
              <option value="danger">🔴 Rojo</option>
              <option value="warning">🟡 Amarillo</option>
              <option value="info">🔵 Celeste</option>
              <option value="dark">⚫ Negro</option>
            </select>
          </div>
          <div class="col-6">
            <label class="tr-form-label small">Ícono</label>
            <select id="input-icono-estado" class="form-select form-select-sm">
              <option value="circle">⚪ Círculo</option>
              <option value="clock">🕐 Reloj</option>
              <option value="package">📦 Paquete</option>
              <option value="tool">🔧 Herramienta</option>
              <option value="truck">🚚 Camión</option>
              <option value="alert-circle">⚠️ Alerta</option>
              <option value="check-circle">✅ Check</option>
              <option value="x-circle">❌ X</option>
              <option value="pause-circle">⏸️ Pausa</option>
              <option value="play-circle">▶️ Play</option>
              <option value="inbox">📥 Inbox</option>
              <option value="search">🔍 Buscar</option>
              <option value="settings">⚙️ Config</option>
              <option value="star">⭐ Estrella</option>
              <option value="flag">🚩 Bandera</option>
            </select>
          </div>
        </div>

        <div class="mb-3">
          <label class="tr-form-label small">Vista previa</label>
          <div class="p-2 bg-light rounded text-center">
            <span class="badge" id="preview-badge" style="font-size:13px">
              <i data-feather="circle" id="preview-icon" style="width:12px;height:12px"></i>
              <span id="preview-text">Nombre del estado</span>
            </span>
          </div>
        </div>

        <div class="text-danger small" id="error-agregar-estado" style="display:none"></div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-success btn-sm" id="btn-confirmar-estado">
          <i data-feather="plus" style="width:13px;height:13px"></i> Crear estado
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal para editar estado -->
<div class="modal fade" id="modal-editar-estado" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title">✏️ Editar estado</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="edit-estado-codigo"/>
        
        <div class="mb-3">
          <label class="tr-form-label small">Nombre del estado *</label>
          <input type="text" id="edit-estado-nombre" class="form-control form-control-sm" required/>
        </div>

        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="tr-form-label small">Color del badge</label>
            <select id="edit-estado-color" class="form-select form-select-sm">
              <option value="secondary">⚪ Gris</option>
              <option value="primary">🔵 Azul</option>
              <option value="success">🟢 Verde</option>
              <option value="danger">🔴 Rojo</option>
              <option value="warning">🟡 Amarillo</option>
              <option value="info">🔵 Celeste</option>
              <option value="dark">⚫ Negro</option>
            </select>
          </div>
          <div class="col-6">
            <label class="tr-form-label small">Ícono</label>
            <select id="edit-estado-icono" class="form-select form-select-sm">
              <option value="circle">⚪ Círculo</option>
              <option value="clock">🕐 Reloj</option>
              <option value="package">📦 Paquete</option>
              <option value="tool">🔧 Herramienta</option>
              <option value="truck">🚚 Camión</option>
              <option value="alert-circle">⚠️ Alerta</option>
              <option value="check-circle">✅ Check</option>
              <option value="x-circle">❌ X</option>
              <option value="pause-circle">⏸️ Pausa</option>
              <option value="play-circle">▶️ Play</option>
              <option value="inbox">📥 Inbox</option>
              <option value="search">🔍 Buscar</option>
              <option value="settings">⚙️ Config</option>
              <option value="star">⭐ Estrella</option>
              <option value="flag">🚩 Bandera</option>
            </select>
          </div>
        </div>

        <div class="text-danger small" id="error-editar-estado" style="display:none"></div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary btn-sm" id="btn-guardar-edicion">
          <i data-feather="save" style="width:13px;height:13px"></i> Guardar cambios
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal para eliminar estado -->
<div class="modal fade" id="modal-eliminar-estado" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white py-2">
        <h6 class="modal-title">⚠️ Eliminar estado</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">¿Eliminar el estado <strong id="delete-estado-nombre"></strong>?</p>
        <p class="text-muted small mb-0">Esta acción no se puede deshacer.</p>
        <input type="hidden" id="delete-estado-codigo"/>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger btn-sm" id="btn-confirmar-eliminar">
          <i data-feather="trash-2" style="width:13px;height:13px"></i> Eliminar
        </button>
      </div>
    </div>
  </div>
</div>

<script>
// Estados disponibles (cargados desde PHP)
const estadosDisponibles = <?= json_encode($estadosOT) ?>;

function agregarEstado() {
  const inp = document.getElementById('input-nuevo-estado');
  inp.value = '';
  document.getElementById('input-color-estado').value = 'secondary';
  document.getElementById('input-icono-estado').value = 'circle';
  document.getElementById('error-agregar-estado').style.display = 'none';
  actualizarPreview();
  const modal = new bootstrap.Modal(document.getElementById('modal-agregar-estado'));
  modal.show();
  setTimeout(() => inp.focus(), 400);
}

function actualizarPreview() {
  const nombre = document.getElementById('input-nuevo-estado').value.trim() || 'Nombre del estado';
  const color = document.getElementById('input-color-estado').value;
  const icono = document.getElementById('input-icono-estado').value;
  
  const badge = document.getElementById('preview-badge');
  badge.className = 'badge bg-' + color;
  badge.style.fontSize = '13px';
  
  document.getElementById('preview-text').textContent = nombre;
  document.getElementById('preview-icon').setAttribute('data-feather', icono);
  
  feather.replace();
}

// Actualizar preview en tiempo real
document.getElementById('input-nuevo-estado').addEventListener('input', actualizarPreview);
document.getElementById('input-color-estado').addEventListener('change', actualizarPreview);
document.getElementById('input-icono-estado').addEventListener('change', actualizarPreview);

document.getElementById('btn-confirmar-estado').addEventListener('click', async function() {
  const valor = document.getElementById('input-nuevo-estado').value.trim();
  const color = document.getElementById('input-color-estado').value;
  const icono = document.getElementById('input-icono-estado').value;
  
  if (!valor) {
    document.getElementById('error-agregar-estado').textContent = 'El nombre es obligatorio';
    document.getElementById('error-agregar-estado').style.display = '';
    return;
  }

  const fd = new FormData();
  fd.append('accion', 'estado_orden');
  fd.append('valor',  valor);
  fd.append('color',  color);
  fd.append('icono',  icono);

  try {
    const r = await fetch('<?= BASE_URL ?>modules/ot/api_agregar.php', { method:'POST', body: fd });
    const d = await r.json();

    if (d.ok) {
      bootstrap.Modal.getInstance(document.getElementById('modal-agregar-estado')).hide();
      window.location.reload();
    } else {
      document.getElementById('error-agregar-estado').textContent = d.error || 'Error al crear estado';
      document.getElementById('error-agregar-estado').style.display = '';
    }
  } catch(e) {
    document.getElementById('error-agregar-estado').textContent = 'Error de conexión';
    document.getElementById('error-agregar-estado').style.display = '';
  }
});

// Editar estado
function editarEstadoSeleccionado() {
  const select = document.getElementById('sel-nuevo-estado');
  const codigo = select.value;
  const estado = estadosDisponibles[codigo];
  
  if (!estado) return;
  
  document.getElementById('edit-estado-codigo').value = codigo;
  document.getElementById('edit-estado-nombre').value = estado.label;
  document.getElementById('edit-estado-color').value = estado.color;
  document.getElementById('edit-estado-icono').value = estado.icon;
  document.getElementById('error-editar-estado').style.display = 'none';
  
  const modal = new bootstrap.Modal(document.getElementById('modal-editar-estado'));
  modal.show();
}

document.getElementById('btn-guardar-edicion').addEventListener('click', async function() {
  const codigo = document.getElementById('edit-estado-codigo').value;
  const nombre = document.getElementById('edit-estado-nombre').value.trim();
  const color = document.getElementById('edit-estado-color').value;
  const icono = document.getElementById('edit-estado-icono').value;
  
  if (!nombre) {
    document.getElementById('error-editar-estado').textContent = 'El nombre es obligatorio';
    document.getElementById('error-editar-estado').style.display = '';
    return;
  }

  const fd = new FormData();
  fd.append('accion', 'editar_estado');
  fd.append('codigo', codigo);
  fd.append('nombre', nombre);
  fd.append('color',  color);
  fd.append('icono',  icono);

  try {
    const r = await fetch('<?= BASE_URL ?>modules/ot/api_agregar.php', { method:'POST', body: fd });
    const d = await r.json();

    if (d.ok) {
      bootstrap.Modal.getInstance(document.getElementById('modal-editar-estado')).hide();
      window.location.reload();
    } else {
      document.getElementById('error-editar-estado').textContent = d.error || 'Error al editar';
      document.getElementById('error-editar-estado').style.display = '';
    }
  } catch(e) {
    document.getElementById('error-editar-estado').textContent = 'Error de conexión';
    document.getElementById('error-editar-estado').style.display = '';
  }
});

// Eliminar estado
function eliminarEstadoSeleccionado() {
  const select = document.getElementById('sel-nuevo-estado');
  const codigo = select.value;
  const estado = estadosDisponibles[codigo];
  
  if (!estado) return;
  
  document.getElementById('delete-estado-codigo').value = codigo;
  document.getElementById('delete-estado-nombre').textContent = estado.label;
  
  const modal = new bootstrap.Modal(document.getElementById('modal-eliminar-estado'));
  modal.show();
}

document.getElementById('btn-confirmar-eliminar').addEventListener('click', async function() {
  const codigo = document.getElementById('delete-estado-codigo').value;

  const fd = new FormData();
  fd.append('accion', 'eliminar_estado');
  fd.append('codigo', codigo);

  try {
    const r = await fetch('<?= BASE_URL ?>modules/ot/api_agregar.php', { method:'POST', body: fd });
    const d = await r.json();

    if (d.ok) {
      bootstrap.Modal.getInstance(document.getElementById('modal-eliminar-estado')).hide();
      window.location.reload();
    } else {
      alert(d.error || 'Error al eliminar estado');
    }
  } catch(e) {
    alert('Error de conexión');
  }
});

// Confirmar con Enter
document.getElementById('input-nuevo-estado').addEventListener('keydown', e => {
  if (e.key === 'Enter') { 
    e.preventDefault(); 
    document.getElementById('btn-confirmar-estado').click(); 
  }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
