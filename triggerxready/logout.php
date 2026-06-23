<?php
define('TRIGGERX', true);
require_once __DIR__ . '/core/config/config.php';

if (Session::isLoggedIn()) {
    auditLog('logout', 'auth', 'Cierre de sesión');
}
Session::destroy();
redirect(BASE_URL . '/login.php');
