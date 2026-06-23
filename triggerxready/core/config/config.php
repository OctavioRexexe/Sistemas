<?php
/**
 * TRIGGERX ENTERPRISE PLATFORM — config.php
 * Compatible: WAMP (Windows) | LAMP | cPanel
 * PHP 8.0+
 */

defined('TRIGGERX') or define('TRIGGERX', true);

// Buffer de salida — evita errores "headers already sent" en WAMP
if (!ob_get_level()) {
    ob_start();
}

// ============================================================
// RUTAS EN DISCO
// ============================================================
define('CORE_PATH',    dirname(__DIR__));          // core/config -> core/
define('ROOT_PATH',    dirname(CORE_PATH));         // core/ -> triggerx/
define('MODULES_PATH', ROOT_PATH . '/modules');
define('ASSETS_PATH',  ROOT_PATH . '/assets');
define('UPLOADS_PATH', ROOT_PATH . '/assets/uploads');

// ============================================================
// BASE URL
// Ajusta manualmente si la detección automática falla:
// define('BASE_URL', 'http://localhost/triggerx');
// ============================================================
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Normalizar separadores Windows/Linux
    $rootNorm    = rtrim(str_replace('\\', '/', ROOT_PATH), '/');
    $docRootNorm = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

    if ($docRootNorm !== '' && strpos($rootNorm, $docRootNorm) === 0) {
        $subdir = substr($rootNorm, strlen($docRootNorm));
    } else {
        $subdir = '';
    }

    define('BASE_URL',    $protocol . '://' . $host . rtrim($subdir, '/'));
    define('ASSETS_URL',  BASE_URL . '/assets');
    define('UPLOADS_URL', BASE_URL . '/assets/uploads');
}

// ============================================================
// BASE DE DATOS
// ============================================================
define('DB_HOST',    'localhost');
define('DB_NAME',    'triggerx');
define('DB_USER',    'root');     // WAMP: root | cPanel: tu_usuario
define('DB_PASS',    '');         // WAMP: vacío | cPanel: tu_password
define('DB_CHARSET', 'utf8mb4');
define('DB_PORT',    3306);

// ============================================================
// SESIONES
// ============================================================
define('SESSION_NAME',       'TRIGGERX_SESSION');
define('SESSION_LIFETIME',   7200);
define('SESSION_PATH',       '/');
define('REMEMBER_ME_DAYS',   30);

// ============================================================
// SEGURIDAD
// ============================================================
define('SALT_KEY',           'TxEnT3rpR!se$2024_S3cur!ty_K3y');
define('CSRF_TOKEN_NAME',    '_csrf_token');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME',       15);
define('BCRYPT_COST',        10);

// ============================================================
// UPLOADS
// ============================================================
define('UPLOAD_MAX_SIZE',     10 * 1024 * 1024);
define('UPLOAD_ALLOWED_MIME', [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf',
]);
define('UPLOAD_ALLOWED_EXT', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf']);

// ============================================================
// SISTEMA
// ============================================================
define('APP_VERSION',     '1.0.0');
define('APP_NAME',        'TriggerX Enterprise');
define('TIMEZONE',        'America/Lima');
define('DATE_FORMAT',     'd/m/Y');
define('DATETIME_FORMAT', 'd/m/Y H:i');
define('DEBUG_MODE',      true);   // false en producción

// ============================================================
// PHP SETTINGS
// ============================================================
date_default_timezone_set(TIMEZONE);
mb_internal_encoding('UTF-8');

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

@ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);
@ini_set('session.cookie_httponly', '1');
@ini_set('session.use_strict_mode', '1');

// ============================================================
// CARGAR NÚCLEO
// ============================================================
require_once CORE_PATH . '/classes/Database.php';
require_once CORE_PATH . '/classes/Session.php';
require_once CORE_PATH . '/classes/Config.php';
require_once CORE_PATH . '/helpers/functions.php';
require_once CORE_PATH . '/helpers/security.php';

// ============================================================
// INICIALIZAR SESIÓN
// ============================================================
Session::start();
