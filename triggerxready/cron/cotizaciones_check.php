<?php
/**
 * TRIGGERX - CRON JOB: Vencimiento Automático de Cotizaciones
 *
 * Marca como 'vencida' toda cotización en estado 'enviada' cuya fecha_vencimiento
 * ya pasó y nadie respondió (no se marcó aprobada/rechazada).
 *
 * Ejecutar diariamente desde cPanel Cron Jobs:
 * 0 8 * * * php /home/tu_usuario/public_html/cron/cotizaciones_check.php
 *
 * O vía URL protegida (solo con token):
 * https://tudominio.com/cron/cotizaciones_check.php?token=TU_TOKEN_AQUI
 */

$isCli = (php_sapi_name() === 'cli');
$token = $_GET['token'] ?? '';

define('TRIGGERX', true);
require_once __DIR__ . '/../core/config/config.php';

if (!$isCli) {
    $validToken = hash('sha256', SALT_KEY . 'cron_cotizaciones');
    if ($token !== $validToken) {
        http_response_code(403);
        die('Acceso denegado');
    }
}

$db  = Database::getInstance();
$log = [];

function cronLog(string $msg): void {
    global $log, $isCli;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    $log[] = $line;
    if ($isCli) echo $line . PHP_EOL;
}

cronLog('=== Inicio verificación de vencimiento de cotizaciones ===');

try {
    $vencidas = $db->fetchAll(
        "SELECT id, codigo, usuario_id FROM cotizaciones
         WHERE estado = 'enviada' AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento < CURDATE()"
    );

    cronLog("Cotizaciones a vencer: " . count($vencidas));

    foreach ($vencidas as $c) {
        $db->update('cotizaciones', ['estado' => 'vencida'], 'id = ?', [$c['id']]);
        cronLog("Cotización #{$c['id']} ({$c['codigo']}) → vencida");

        if ($c['usuario_id']) {
            crearNotificacion($c['usuario_id'], 'cotizacion_vencida',
                "Cotización vencida: {$c['codigo']}",
                "La cotización {$c['codigo']} venció sin respuesta del cliente.",
                BASE_URL . "/modules/cotizaciones/form.php?id={$c['id']}"
            );
        }
    }

    $db->insert('logs_auditoria', [
        'accion'      => 'cron_cotizaciones',
        'modulo'      => 'cron',
        'descripcion' => "Cotizaciones vencidas automáticamente: " . count($vencidas),
        'ip'          => '127.0.0.1',
    ]);

    cronLog('=== Cron de cotizaciones completado exitosamente ===');

    if (!$isCli) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'vencidas' => count($vencidas)]);
    }

} catch (Throwable $e) {
    cronLog('ERROR: ' . $e->getMessage());
    error_log('Cron Cotizaciones Error: ' . $e->getMessage());
    if (!$isCli) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
