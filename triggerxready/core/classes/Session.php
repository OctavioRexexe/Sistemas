<?php
/**
 * TRIGGERX - Clase Session
 * Manejo seguro de sesiones con regeneración de ID
 */
defined('TRIGGERX') or die('Acceso denegado');

class Session
{
    private static bool $started = false;

    /** Iniciar sesión segura */
    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        session_name(SESSION_NAME);

        $cookieParams = [
            'lifetime' => SESSION_LIFETIME,
            'path'     => SESSION_PATH,
            'domain'   => '',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        session_set_cookie_params($cookieParams);
        session_start();
        self::$started = true;

        // Regenerar ID si hay sesión activa (previene session fixation)
        if (!self::has('_session_init')) {
            session_regenerate_id(true);
            self::set('_session_init', time());
        }
    }

    /** Establecer valor en sesión */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /** Obtener valor de sesión */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /** Verificar si existe clave */
    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /** Eliminar clave */
    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Destruir sesión completamente */
    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
        self::$started = false;
    }

    /** Establecer datos del usuario autenticado */
    public static function setUser(array $user): void
    {
        session_regenerate_id(true);
        self::set('user', [
            'id'       => (int)$user['id'],
            'nombre'   => $user['nombre'],
            'apellido' => $user['apellido'],
            'email'    => $user['email'],
            'rol_id'   => (int)$user['rol_id'],
            'rol_slug' => $user['rol_slug'] ?? 'cliente',
            'rol_nombre' => $user['rol_nombre'] ?? '',
            'avatar'   => $user['avatar'] ?? null,
            'empresa'  => $user['empresa'] ?? null,
        ]);
        self::set('_login_time', time());
        self::set('_last_activity', time());
    }

    /** Obtener usuario de sesión */
    public static function getUser(): array|null
    {
        return self::get('user');
    }

    /** Verificar si hay usuario autenticado */
    public static function isLoggedIn(): bool
    {
        if (!self::has('user')) return false;

        // Verificar expiración de inactividad
        $lastActivity = self::get('_last_activity', 0);
        if ((time() - $lastActivity) > SESSION_LIFETIME) {
            self::destroy();
            return false;
        }

        self::set('_last_activity', time());
        return true;
    }

    /** Obtener ID del usuario actual */
    public static function userId(): int|null
    {
        return self::get('user')['id'] ?? null;
    }

    /** Obtener rol slug del usuario */
    public static function userRole(): string
    {
        return self::get('user')['rol_slug'] ?? 'cliente';
    }

    /** Verificar si el usuario tiene cierto rol o superior */
    public static function hasRole(string $roleSlug): bool
    {
        $roles = ['cliente' => 1, 'tecnico' => 2, 'admin' => 3, 'superadmin' => 4];
        $userLevel = $roles[self::userRole()] ?? 0;
        $requiredLevel = $roles[$roleSlug] ?? 99;
        return $userLevel >= $requiredLevel;
    }

    /** Flash message (disponible solo en próxima request) */
    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    /** Obtener y eliminar flash message */
    public static function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    /** Generar/validar token CSRF */
    public static function csrfToken(): string
    {
        if (!self::has(CSRF_TOKEN_NAME)) {
            self::set(CSRF_TOKEN_NAME, bin2hex(random_bytes(32)));
        }
        return self::get(CSRF_TOKEN_NAME);
    }

    public static function verifyCsrf(string $token): bool
    {
        return hash_equals(self::get(CSRF_TOKEN_NAME, ''), $token);
    }

    public static function regenerateCsrf(): void
    {
        self::set(CSRF_TOKEN_NAME, bin2hex(random_bytes(32)));
    }
}
