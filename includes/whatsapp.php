<?php
// includes/whatsapp.php — Envío de mensajes via WhatsApp Business API (Meta)

function enviarWhatsApp(PDO $db, int $otId, string $tipo): bool {
    $token   = getConfig('whatsapp_api_token', $db);
    $phoneId = getConfig('whatsapp_phone_id',   $db);
    if (!$token || !$phoneId) return false;

    $ot = $db->prepare("SELECT ot.*, c.nombre as cliente_nombre, c.whatsapp FROM ordenes_trabajo ot JOIN clientes c ON c.id=ot.cliente_id WHERE ot.id=?");
    $ot->execute([$otId]);
    $ot = $ot->fetch();
    if (!$ot || !$ot['whatsapp']) return false;

    $empresa = getConfig('empresa_nombre', $db);
    $wa = preg_replace('/\D/', '', $ot['whatsapp']);
    if (!str_starts_with($wa, '51')) $wa = '51'.$wa; // Perú

    $mensajes = [
        'listo'        => "¡Hola {$ot['cliente_nombre']}! 👋\n\nTe informamos que tu equipo ya está *listo para recoger* en {$empresa}. 🎉\n\n🔑 Código OT: *{$ot['codigo_ot']}*\n\nRecuerda traer tu DNI. ¡Te esperamos!",
        'en_reparacion'=> "Hola {$ot['cliente_nombre']}, tu equipo está siendo reparado en {$empresa}. 🔧\n\n📋 OT: *{$ot['codigo_ot']}*\nTe avisamos cuando esté listo.",
        'presupuesto'  => "Hola {$ot['cliente_nombre']}, el presupuesto de tu reparación en {$empresa} está disponible. 💰\n\n📋 OT: *{$ot['codigo_ot']}*\nTotal: *S/ ".number_format($ot['precio_final'],2)."*\n\nResponde a este mensaje para confirmarlo.",
        'entregado'    => "¡Gracias por confiar en {$empresa}! 🙏\n\nTu equipo fue entregado correctamente.\n📋 OT: *{$ot['codigo_ot']}*\n\nRecuerda que cuentas con *{$ot['garantia_dias']} días* de garantía.",
    ];

    $mensaje = $mensajes[$tipo] ?? "Actualización de tu OT {$ot['codigo_ot']} en {$empresa}.";

    $payload = [
        'messaging_product' => 'whatsapp',
        'to'      => $wa,
        'type'    => 'text',
        'text'    => ['preview_url' => false, 'body' => $mensaje],
    ];

    $ch = curl_init("https://graph.facebook.com/v18.0/{$phoneId}/messages");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.$token, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $ok = $code === 200;

    // Registrar en tabla notificaciones
    $db->prepare("INSERT INTO notificaciones (ot_id,cliente_id,tipo,asunto,mensaje,estado,enviado_at,error_msg) VALUES (?,?,?,?,?,?,?,?)")
       ->execute([$otId, $ot['cliente_id'], 'whatsapp', "Estado: $tipo", $mensaje, $ok?'enviado':'error', $ok?date('Y-m-d H:i:s'):null, $ok?null:$resp]);

    return $ok;
}
