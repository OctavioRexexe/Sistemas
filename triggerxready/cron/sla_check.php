<?php
/**
 * TRIGGERX - CRON JOB: Verificación SLA Automática
 *
 * Ejecutar diariamente desde cPanel Cron Jobs:
 * 0 8 * * * php /home/tu_usuario/public_html/cron/sla_check.php
 *
 * O vía URL protegida (solo con token):
 * https://tudominio.com/cron/sla_check.php?token=TU_TOKEN_AQUI
 */

// Permitir ejecución por CLI o por URL con token
$isCli = (php_sapi_name() === 'cli');
$token = $_GET['token'] ?? '';

define('TRIGGERX', true);
require_once __DIR__ . '/../core/config/config.php';

// Seguridad: requerir token si es web
if (!$isCli) {
    $validToken = hash('sha256', SALT_KEY . 'cron_sla');
    if ($token !== $validToken) {
        http_response_code(403);
        die('Acceso denegado');
    }
}

$db   = Database::getInstance();
$sla  = Config::sla();
$log  = [];
$now  = date('Y-m-d H:i:s');

function cronLog(string $msg): void {
    global $log, $isCli;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    $log[] = $line;
    if ($isCli) echo $line . PHP_EOL;
}

cronLog('=== Inicio verificación SLA ===');

try {
    // Obtener todos los tickets activos (no cerrados/resueltos)
    $tickets = $db->fetchAll(
        "SELECT t.*, TIMESTAMPDIFF(DAY, t.created_at, NOW()) AS dias_abierto,
                e.es_final, e.slug AS estado_slug,
                p.sla_horas
         FROM tickets t
         LEFT JOIN estados_ticket e ON t.estado_id = e.id
         LEFT JOIN prioridades_ticket p ON t.prioridad_id = p.id
         WHERE e.es_final = 0"
    );

    cronLog("Tickets activos a verificar: " . count($tickets));

    $actualizados = 0;
    $alertasEnviadas = 0;

    foreach ($tickets as $t) {
        $dias = (int)$t['dias_abierto'];

        // Calcular nuevo estado SLA
        $nuevoEstado = slaEstado($dias);

        // ¿Está vencido?
        $vencido = ($dias >= $sla['max_dias']) ? 1 : 0;

        // Solo actualizar si cambió algo
        if ($nuevoEstado !== $t['sla_estado'] || $vencido !== (int)$t['sla_vencido']) {
            $db->update('tickets',
                ['sla_estado' => $nuevoEstado, 'sla_vencido' => $vencido],
                'id = ?', [$t['id']]
            );
            $actualizados++;
            cronLog("Ticket #{$t['id']} ({$t['codigo']}): SLA → {$nuevoEstado}" . ($vencido ? ' [VENCIDO]' : ''));
        }

        // Enviar alertas a técnico asignado
        if ($t['tecnico_id']) {
            if ($dias == $sla['preventiva']) {
                // Alerta preventiva
                crearNotificacion($t['tecnico_id'], 'sla_proximo',
                    "⚠️ SLA Próximo a Vencer: {$t['codigo']}",
                    "El ticket lleva {$dias} días. Límite: {$sla['max_dias']} días.",
                    BASE_URL . "/modules/tickets/detalle.php?id={$t['id']}"
                );
                $alertasEnviadas++;
                cronLog("Alerta preventiva → técnico #{$t['tecnico_id']} para ticket {$t['codigo']}");
            }
            if ($dias == $sla['importante']) {
                crearNotificacion($t['tecnico_id'], 'sla_proximo',
                    "🔴 URGENTE: SLA próximo a vencer — {$t['codigo']}",
                    "El ticket lleva {$dias} días sin resolución. Límite: {$sla['max_dias']} días.",
                    BASE_URL . "/modules/tickets/detalle.php?id={$t['id']}"
                );
                $alertasEnviadas++;
            }
            if ($vencido && !$t['sla_vencido']) {
                // Primera vez que vence
                crearNotificacion($t['tecnico_id'], 'sla_vencido',
                    "🚨 SLA VENCIDO: {$t['codigo']}",
                    "El ticket ha superado el límite de {$sla['max_dias']} días sin resolución.",
                    BASE_URL . "/modules/tickets/detalle.php?id={$t['id']}"
                );
                $alertasEnviadas++;

                // Notificar también a admins
                $admins = $db->fetchAll("SELECT id FROM usuarios u JOIN roles r ON u.rol_id=r.id WHERE r.nivel>=3");
                foreach ($admins as $admin) {
                    crearNotificacion($admin['id'], 'sla_vencido',
                        "🚨 SLA VENCIDO: {$t['codigo']}",
                        "Ticket sin resolver por {$dias} días. Cliente: {$t['cliente_nombre']}",
                        BASE_URL . "/modules/tickets/detalle.php?id={$t['id']}"
                    );
                }
            }
        }
    }

    cronLog("Tickets actualizados: {$actualizados}");
    cronLog("Alertas enviadas: {$alertasEnviadas}");

    // Cierre automático de tickets resueltos viejos
    $diasCierre = (int)Config::get('ticket_cerrar_dias', 3);
    if ($diasCierre > 0) {
        $estadoCerradoId = $db->fetchColumn("SELECT id FROM estados_ticket WHERE slug='cerrado'");
        $estadoResueltoId = $db->fetchColumn("SELECT id FROM estados_ticket WHERE slug='resuelto'");

        if ($estadoCerradoId && $estadoResueltoId) {
            $cerrados = $db->query(
                "UPDATE tickets SET estado_id = ?
                 WHERE estado_id = ?
                 AND fecha_resolucion IS NOT NULL
                 AND TIMESTAMPDIFF(DAY, fecha_resolucion, NOW()) >= ?",
                [$estadoCerradoId, $estadoResueltoId, $diasCierre]
            )->rowCount();

            if ($cerrados > 0) {
                cronLog("Tickets cerrados automáticamente: {$cerrados}");
            }
        }
    }

    // Registrar en log
    $db->insert('logs_auditoria', [
        'accion'      => 'cron_sla',
        'modulo'      => 'cron',
        'descripcion' => "SLA verificado. Actualizados: {$actualizados}. Alertas: {$alertasEnviadas}.",
        'ip'          => '127.0.0.1',
    ]);

    cronLog('=== Cron SLA completado exitosamente ===');

    if (!$isCli) {
        header('Content-Type: application/json');
        echo json_encode([
            'success'    => true,
            'actualizados' => $actualizados,
            'alertas'    => $alertasEnviadas,
            'timestamp'  => $now,
        ]);
    }

} catch (Throwable $e) {
    cronLog('ERROR: ' . $e->getMessage());
    error_log('Cron SLA Error: ' . $e->getMessage());
    if (!$isCli) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
