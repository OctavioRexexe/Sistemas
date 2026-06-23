<?php
define('TRIGGERX', true);
require_once __DIR__ . '/../../core/config/config.php';
requireLogin();

$action = get_param('action') ?: post('action');
$db     = Database::getInstance();
$userId = Session::userId();

switch ($action) {
    case 'count':
        $count = (int)$db->fetchColumn("SELECT COUNT(*) FROM notificaciones WHERE usuario_id=? AND leida=0", [$userId]);
        echo json_encode(['count' => $count]);
        break;

    case 'list':
        $notifs = $db->fetchAll("SELECT * FROM notificaciones WHERE usuario_id=? AND leida=0 ORDER BY created_at DESC LIMIT 20", [$userId]);
        jsonResponse(true, '', $notifs);
        break;

    case 'mark_read':
        verifyCsrf();
        $id = (int)post('id');
        $db->update('notificaciones', ['leida' => 1], 'id=? AND usuario_id=?', [$id, $userId]);
        jsonResponse(true, 'Marcada como leída');
        break;

    case 'mark_all':
        verifyCsrf();
        $db->query("UPDATE notificaciones SET leida=1 WHERE usuario_id=?", [$userId]);
        jsonResponse(true, 'Todas marcadas como leídas');
        break;

    default:
        jsonResponse(false, 'Acción inválida', null, 400);
}
