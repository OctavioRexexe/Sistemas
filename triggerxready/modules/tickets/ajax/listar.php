<?php
define('TRIGGERX', true);
require_once __DIR__ . '/../../../core/config/config.php';
requireLogin();
requireModule('tickets');

$user = Session::getUser();
$db   = Database::getInstance();

$estado    = get_param('estado');
$prioridad = get_param('prioridad');
$sla       = get_param('sla');
$tecnico   = get_param('tecnico');

$where  = ['1=1'];
$params = [];

// Clientes solo ven sus tickets
if ($user['rol_slug'] === 'cliente') {
    $where[]  = '(t.usuario_id = ? OR t.cliente_email = ?)';
    $params[] = $user['id'];
    $params[] = $user['email'];
}
// Técnico solo ve sus asignados
if ($user['rol_slug'] === 'tecnico') {
    $where[]  = '(t.tecnico_id = ? OR t.tecnico_id IS NULL)';
    $params[] = $user['id'];
}

if ($estado)    { $where[] = 'e.slug = ?';     $params[] = $estado; }
if ($prioridad) { $where[] = 'p.slug = ?';     $params[] = $prioridad; }
if ($sla)       { $where[] = 't.sla_estado = ?'; $params[] = $sla; }
if ($tecnico)   { $where[] = 't.tecnico_id = ?'; $params[] = (int)$tecnico; }

$sql = "SELECT t.*, e.nombre AS estado_nombre, e.color AS estado_color, e.slug AS estado_slug,
               p.nombre AS prioridad_nombre, p.color AS prioridad_color,
               c.nombre AS categoria_nombre,
               CONCAT(u.nombre,' ',u.apellido) AS tecnico_nombre,
               TIMESTAMPDIFF(DAY, t.created_at, NOW()) AS dias_abierto
        FROM tickets t
        LEFT JOIN estados_ticket e ON t.estado_id = e.id
        LEFT JOIN prioridades_ticket p ON t.prioridad_id = p.id
        LEFT JOIN categorias_ticket c ON t.categoria_id = c.id
        LEFT JOIN usuarios u ON t.tecnico_id = u.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY t.id DESC LIMIT 500";

try {
    $tickets = $db->fetchAll($sql, $params);
    jsonResponse(true, '', $tickets);
} catch (Throwable $e) {
    jsonResponse(false, 'Error al obtener tickets');
}
