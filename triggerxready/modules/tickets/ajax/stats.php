<?php
define('TRIGGERX', true);
require_once __DIR__ . '/../../../core/config/config.php';
requireLogin();
requireModule('tickets');

$db   = Database::getInstance();
$user = Session::getUser();

$stats = [
    'total'      => (int)$db->fetchColumn("SELECT COUNT(*) FROM tickets"),
    'abiertos'   => (int)$db->fetchColumn("SELECT COUNT(*) FROM tickets t JOIN estados_ticket e ON t.estado_id=e.id WHERE e.slug='abierto'"),
    'en_proceso' => (int)$db->fetchColumn("SELECT COUNT(*) FROM tickets t JOIN estados_ticket e ON t.estado_id=e.id WHERE e.slug='en_proceso'"),
    'resueltos'  => (int)$db->fetchColumn("SELECT COUNT(*) FROM tickets t JOIN estados_ticket e ON t.estado_id=e.id WHERE e.slug='resuelto'"),
    'cerrados'   => (int)$db->fetchColumn("SELECT COUNT(*) FROM tickets t JOIN estados_ticket e ON t.estado_id=e.id WHERE e.slug='cerrado'"),
    'sla_vencido'=> (int)$db->fetchColumn("SELECT COUNT(*) FROM tickets WHERE sla_vencido=1 AND fecha_resolucion IS NULL"),
];

jsonResponse(true, '', $stats);
