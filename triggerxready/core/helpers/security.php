<?php
/**
 * TRIGGERX - Helpers de Seguridad
 * CSRF, XSS, Auth, Rate limiting
 */
defined('TRIGGERX') or die('Acceso denegado');

// ============================================================
// CSRF
// ============================================================

/** Campo CSRF para formularios */
function csrfField(): string
{
    $token = Session::csrfToken();
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . e($token) . '">';
}

/** Verificar token CSRF - lanzar excepción si inválido */
function verifyCsrf(): void
{
    $token = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Session::verifyCsrf($token)) {
        if (isAjax()) {
            jsonResponse(false, 'Token de seguridad inválido. Recarga la página.', null, 403);
        }
        http_response_code(403);
        die('<h1>403 - Token de seguridad inválido</h1><p>Por favor <a href="javascript:history.back()">regresa</a> y vuelve a intentarlo.</p>');
    }
    Session::regenerateCsrf();
}

// ============================================================
// AUTENTICACIÓN
// ============================================================

/** Requerir login - Redirige si no autenticado */
function requireLogin(string $redirectTo = null): void
{
    if (!Session::isLoggedIn()) {
        $redirectTo ??= BASE_URL . '/login.php';
        if (isAjax()) {
            jsonResponse(false, 'Sesión expirada', null, 401);
        }
        Session::flash('alert_type', 'warning');
        Session::flash('alert_msg', 'Por favor inicia sesión para continuar.');
        redirect($redirectTo . '?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
}

/** Requerir rol mínimo */
function requireRole(string $roleSlug): void
{
    requireLogin();
    if (!Session::hasRole($roleSlug)) {
        if (isAjax()) {
            jsonResponse(false, 'Sin permisos para esta acción', null, 403);
        }
        http_response_code(403);
        include ROOT_PATH . '/errors/403.php';
        exit;
    }
}

/** Requerir acceso a módulo específico */
function requireModule(string $moduleSlug): void
{
    requireLogin();
    $user = Session::getUser();
    if (!canAccessModule($moduleSlug, $user['id'], $user['rol_id'])) {
        if (isAjax()) {
            jsonResponse(false, 'Sin acceso a este módulo', null, 403);
        }
        redirect(BASE_URL . '/dashboard.php?error=no_access');
    }
}

// ============================================================
// HEADERS DE SEGURIDAD
// ============================================================

/** Enviar headers de seguridad HTTP */
function securityHeaders(): void
{
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    }
}

// ============================================================
// RATE LIMITING (usando sesión)
// ============================================================

/** Verificar intentos de login */
function checkLoginAttempts(string $email): bool
{
    $key = 'login_attempts_' . md5($email);
    $attempts = Session::get($key, ['count' => 0, 'time' => 0]);
    
    if ($attempts['count'] >= MAX_LOGIN_ATTEMPTS) {
        $lockoutEnd = $attempts['time'] + (LOCKOUT_TIME * 60);
        if (time() < $lockoutEnd) {
            $remaining = ceil(($lockoutEnd - time()) / 60);
            return false; // Bloqueado
        }
        // Limpiar si ya pasó el tiempo
        Session::remove($key);
    }
    return true;
}

/** Registrar intento fallido */
function registerFailedLogin(string $email): void
{
    $key = 'login_attempts_' . md5($email);
    $attempts = Session::get($key, ['count' => 0, 'time' => time()]);
    $attempts['count']++;
    $attempts['time'] = time();
    Session::set($key, $attempts);
}

/** Limpiar intentos de login */
function clearLoginAttempts(string $email): void
{
    Session::remove('login_attempts_' . md5($email));
}

/** Obtener intentos restantes */
function getRemainingAttempts(string $email): int
{
    $key = 'login_attempts_' . md5($email);
    $attempts = Session::get($key, ['count' => 0]);
    return max(0, MAX_LOGIN_ATTEMPTS - $attempts['count']);
}

// ============================================================
// UTILIDADES
// ============================================================

/** Verificar si es petición AJAX */
function isAjax(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/** Verificar método HTTP */
function isPost(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }
function isGet(): bool  { return $_SERVER['REQUEST_METHOD'] === 'GET'; }

/** Sanitizar input de usuario */
function sanitize(mixed $input): string
{
    return trim(htmlspecialchars((string)($input ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

/** Sanitizar array de inputs */
function sanitizeArray(array $data, array $fields): array
{
    $clean = [];
    foreach ($fields as $field) {
        $clean[$field] = sanitize($data[$field] ?? '');
    }
    return $clean;
}

/** Input POST sanitizado */
function post(string $key, string $default = ''): string
{
    return sanitize($_POST[$key] ?? $default);
}

/** Input GET sanitizado */
function get_param(string $key, string $default = ''): string
{
    return sanitize($_GET[$key] ?? $default);
}

/** Validar email */
function isValidEmail(string $email): bool
{
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

/** Validar que no esté vacío */
function notEmpty(string ...$values): bool
{
    foreach ($values as $v) {
        if (trim($v) === '') return false;
    }
    return true;
}
