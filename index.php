<?php
// index.php — Punto de entrada principal
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';

if (!isLoggedIn()) {
    redirect(BASE_URL . 'modules/auth/login.php');
}
redirect(BASE_URL . 'modules/dashboard/index.php');
