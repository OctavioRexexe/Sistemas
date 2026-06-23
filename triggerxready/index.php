<?php
/**
 * TRIGGERX - Punto de entrada principal
 */
define('TRIGGERX', true);
require_once __DIR__ . '/core/config/config.php';

try {
    Config::load();
} catch (Throwable $e) {
    // BD no disponible - continuar sin config de BD
}

if (Session::isLoggedIn()) {
    redirect(BASE_URL . '/dashboard.php');
} else {
    redirect(BASE_URL . '/login.php');
}
