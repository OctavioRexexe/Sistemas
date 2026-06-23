<?php
define('TRIGGERX', true);
require_once __DIR__ . '/../../core/config/config.php';
requireLogin();
requireRole('admin');

$userId = (int)get_param('usuario_id');
if (!$userId) jsonResponse(false, 'ID inválido');

$db = Database::getInstance();
$permisos = $db->fetchAll(
    "SELECT * FROM permisos_modulos WHERE usuario_id = ?",
    [$userId]
);
jsonResponse(true, '', $permisos);
