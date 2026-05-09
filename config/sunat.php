<?php
/**
 * TechRepair — Configuración SUNAT
 * ───────────────────────────────
 * Auto-detecta entorno (LOCAL vs PRODUCCIÓN) por hostname.
 */

$__host = $_SERVER['HTTP_HOST'] ?? gethostname();
$__isLocalSunat = (
    DIRECTORY_SEPARATOR === '\\' ||
    str_contains($__host, 'localhost') ||
    str_contains($__host, '127.0.0.1') ||
    str_contains($__host, '.test')     ||
    str_contains($__host, '.local')
);

if ($__isLocalSunat) {
    define('SUNAT_API_URL', 'http://api-sunat-laravel.test/api/v1');
} else {
    define('SUNAT_API_URL', 'http://84.247.162.204/api-sunat-laravel/api/v1');
}

define('SUNAT_API_TIMEOUT', 60);

// 'beta' = pruebas | 'produccion' = ambiente real
define('SUNAT_ENDPOINT', 'beta');

// ─── Datos del emisor desde tabla empresa (DB) ───────────────
if (!defined('SUNAT_RUC')) {
    try {
        $__empRow = getDB()->query("SELECT * FROM empresa WHERE id=1")->fetch();
    } catch (Exception $e) { $__empRow = null; }

    define('SUNAT_RUC',              $__empRow['ruc']              ?? '20000000001');
    define('SUNAT_USUARIO_SOL',      $__empRow['sunat_usuario_sol']?? 'MODDATOS');
    define('SUNAT_CLAVE_SOL',        $__empRow['sunat_clave_sol']  ?? 'MODDATOS');
    define('SUNAT_RAZON_SOCIAL',     $__empRow['razon_social']     ?? 'MI EMPRESA S.A.C.');
    define('SUNAT_NOMBRE_COMERCIAL', $__empRow['nombre_comercial'] ?? ($__empRow['razon_social'] ?? 'MI EMPRESA'));
    define('SUNAT_DIRECCION',        $__empRow['direccion']        ?? '');
    define('SUNAT_UBIGEO',           $__empRow['ubigeo']           ?? '150101');
    define('SUNAT_DISTRITO',         $__empRow['distrito']         ?? 'LIMA');
    define('SUNAT_PROVINCIA',        $__empRow['provincia']        ?? 'LIMA');
    define('SUNAT_DEPARTAMENTO',     $__empRow['departamento']     ?? 'LIMA');
    define('SUNAT_MODO',             $__empRow['modo']             ?? 'beta');
    define('SUNAT_LOGO_PATH',        !empty($__empRow['logo']) ? BASE_PATH.'assets/img/uploads/'.$__empRow['logo'] : null);
    define('SUNAT_LOGO_URL',         !empty($__empRow['logo']) ? BASE_URL.'assets/img/uploads/'.$__empRow['logo']  : null);
}

// ─── Series ──────────────────────────────────────────────────
define('SUNAT_SERIE_FACTURA', 'F001');
define('SUNAT_SERIE_BOLETA',  'B001');
