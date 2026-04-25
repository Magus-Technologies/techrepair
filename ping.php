<?php
// ping.php - archivo de diagnóstico mínimo, sin dependencias
echo json_encode([
    'status'      => 'ok',
    'php'         => PHP_VERSION,
    'server'      => $_SERVER['HTTP_HOST'] ?? '',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
    'script'      => $_SERVER['SCRIPT_NAME'] ?? '',
    'time'        => date('Y-m-d H:i:s'),
]);
