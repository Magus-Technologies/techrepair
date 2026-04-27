<?php
// config/database.php — Conexión PDO con manejo de errores
// Auto-detecta entorno (LOCAL vs PRODUCCIÓN) por hostname/SO.

$__host      = $_SERVER['HTTP_HOST'] ?? gethostname();
$__isWindows = DIRECTORY_SEPARATOR === '\\';
$__isLocal   = (
    $__isWindows ||
    str_contains($__host, 'localhost') ||
    str_contains($__host, '127.0.0.1') ||
    str_contains($__host, '.test')     ||
    str_contains($__host, '.local')
);

define('DB_HOST', 'localhost');
define('DB_NAME', 'techrepair');
define('DB_USER', 'root');
define('DB_PASS', $__isLocal ? '' : 'c4p1cu4$$');
define('DB_CHARSET', 'utf8mb4');
define('APP_ENV',   $__isLocal ? 'development' : 'production');
define('MIGRATIONS_TOKEN', $__isLocal ? 'dev_local_token_no_importa' : 'techrepair_2026_migrations');

// Auto-detectar dominio y protocolo (funciona en localhost Y en servidor real)
$_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_script   = $_SERVER['SCRIPT_NAME'] ?? '/techrepair/index.php';

// Obtener la carpeta raíz del proyecto (primer segmento del path, ej: "techrepair")
$_parts = explode('/', trim($_script, '/'));
$_root  = $_parts[0] ?? 'techrepair';

define('BASE_URL',    $_protocol . '://' . $_host . '/' . $_root . '/');
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
