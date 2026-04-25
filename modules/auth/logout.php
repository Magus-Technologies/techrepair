<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
session_destroy();
header('Location: ' . BASE_URL . 'modules/auth/login.php');
exit;
