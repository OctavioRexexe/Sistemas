<?php
define('TRIGGERX', true);
require_once __DIR__ . '/../../../core/config/config.php';
requireLogin();
requireRole('admin');

$db = Database::getInstance();

$tickets = $db->fetchAll(
    "SELECT t.codigo, t.cliente_nombre, t.cliente_empresa, t.cliente_email, t.cliente_telefono,
            t.asunto, t.serie_equipo, t.origen, t.created_at, t.updated_at,
            t.primera_respuesta, t.fecha_resolucion, t.sla_estado, t.sla_vencido,
            e.nombre AS estado, p.nombre AS prioridad, c.nombre AS categoria,
            CONCAT(u.nombre,' ',u.apellido) AS tecnico,
            TIMESTAMPDIFF(DAY, t.created_at, NOW()) AS dias_abierto
     FROM tickets t
     LEFT JOIN estados_ticket e ON t.estado_id = e.id
     LEFT JOIN prioridades_ticket p ON t.prioridad_id = p.id
     LEFT JOIN categorias_ticket c ON t.categoria_id = c.id
     LEFT JOIN usuarios u ON t.tecnico_id = u.id
     ORDER BY t.id DESC"
);

$filename = 'tickets_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache');

// BOM UTF-8 para Excel
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Cabecera
fputcsv($out, [
    'Código','Cliente','Empresa','Email','Teléfono','Asunto','Serie Equipo',
    'Estado','Prioridad','Categoría','Técnico','Origen',
    'SLA Estado','SLA Vencido','Días Abierto',
    'Fecha Creación','Primera Respuesta','Fecha Resolución'
], ';');

foreach ($tickets as $t) {
    fputcsv($out, [
        $t['codigo'],
        $t['cliente_nombre'],
        $t['cliente_empresa'] ?? '',
        $t['cliente_email'],
        $t['cliente_telefono'] ?? '',
        $t['asunto'],
        $t['serie_equipo'] ?? '',
        $t['estado'],
        $t['prioridad'],
        $t['categoria'] ?? '',
        $t['tecnico'] ?? '',
        $t['origen'],
        $t['sla_estado'],
        $t['sla_vencido'] ? 'Sí' : 'No',
        $t['dias_abierto'],
        $t['created_at'],
        $t['primera_respuesta'] ?? '',
        $t['fecha_resolucion'] ?? '',
    ], ';');
}

fclose($out);
auditLog('tickets_exportar', 'tickets', 'Exportación CSV: ' . count($tickets) . ' registros');
exit;
