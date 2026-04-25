<?php
// config/database.php — Conexión PDO con manejo de errores

define('DB_HOST', 'localhost');
define('DB_NAME', 'techrepair');
define('DB_USER', 'root');
define('DB_PASS', 'c4p1cu4$$');
define('DB_CHARSET', 'utf8mb4');

// Auto-detectar dominio y protocolo (funciona en vhost, localhost subdir y servidor real)
$_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host     = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Calcular subdirectorio comparando la raíz del proyecto con el DocumentRoot del servidor
$_project_root = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$_doc_root     = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
$_subdir = '';
if ($_project_root && $_doc_root && strpos($_project_root, $_doc_root) === 0) {
    $_subdir = substr($_project_root, strlen($_doc_root));
}

define('BASE_URL',    $_protocol . '://' . $_host . rtrim($_subdir, '/') . '/');
define('BASE_PATH',   dirname(__DIR__) . '/');
define('UPLOAD_PATH', BASE_PATH . 'assets/img/uploads/');
define('UPLOAD_URL',  BASE_URL  . 'assets/img/uploads/');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Error de conexión a la base de datos: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}
