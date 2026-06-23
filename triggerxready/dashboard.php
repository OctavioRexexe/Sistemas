<?php
/**
 * TRIGGERX - Dashboard Principal
 */
define('TRIGGERX', true);
require_once __DIR__ . '/core/config/config.php';
requireLogin();
securityHeaders();

$user = Session::getUser();
$db   = Database::getInstance();

// ============================================================
// KPI STATS
// ============================================================
$stats = [];

try {
    // Totales tickets
    $stats['total']     = (int) $db->fetchColumn("SELECT COUNT(*) FROM tickets");
    $stats['abiertos']  = (int) $db->fetchColumn("SELECT COUNT(*) FROM tickets t JOIN estados_ticket e ON t.estado_id = e.id WHERE e.slug = 'abierto'");
    $stats['proceso']   = (int) $db->fetchColumn("SELECT COUNT(*) FROM tickets t JOIN estados_ticket e ON t.estado_id = e.id WHERE e.slug = 'en_proceso'");
    $stats['resueltos'] = (int) $db->fetchColumn("SELECT COUNT(*) FROM tickets t JOIN estados_ticket e ON t.estado_id = e.id WHERE e.slug = 'resuelto'");
    $stats['cerrados']  = (int) $db->fetchColumn("SELECT COUNT(*) FROM tickets t JOIN estados_ticket e ON t.estado_id = e.id WHERE e.slug = 'cerrado'");
    $stats['sla_vencido'] = (int) $db->fetchColumn("SELECT COUNT(*) FROM tickets WHERE sla_vencido = 1 AND fecha_resolucion IS NULL");

    // Tickets por estado (para chart)
    $ticketsPorEstado = $db->fetchAll(
        "SELECT e.nombre, e.color, COUNT(t.id) AS total
         FROM estados_ticket e
         LEFT JOIN tickets t ON t.estado_id = e.id
         GROUP BY e.id ORDER BY e.orden"
    );

    // Tickets por mes (últimos 6 meses)
    $ticketsPorMes = $db->fetchAll(
        "SELECT DATE_FORMAT(created_at, '%b %Y') AS mes,
                DATE_FORMAT(created_at, '%Y-%m') AS mes_ord,
                COUNT(*) AS total
         FROM tickets
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
         GROUP BY mes_ord
         ORDER BY mes_ord"
    );

    // Tickets por técnico
    $ticketsPorTecnico = $db->fetchAll(
        "SELECT CONCAT(u.nombre,' ',u.apellido) AS tecnico, COUNT(t.id) AS total
         FROM tickets t
         JOIN usuarios u ON t.tecnico_id = u.id
         WHERE t.fecha_resolucion IS NULL
         GROUP BY t.tecnico_id
         ORDER BY total DESC
         LIMIT 8"
    );

    // SLA por colores
    $slaData = $db->fetchAll(
        "SELECT sla_estado, COUNT(*) AS total
         FROM tickets
         WHERE fecha_resolucion IS NULL
         GROUP BY sla_estado"
    );

    // Últimos tickets
    $ultimosTickets = $db->fetchAll(
        "SELECT * FROM v_tickets_dashboard ORDER BY id DESC LIMIT 10"
    );

    // Promedio tiempo atención (en horas)
    $promedioAtencion = round(
        (float) $db->fetchColumn(
            "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, fecha_resolucion)) FROM tickets WHERE fecha_resolucion IS NOT NULL"
        ) ?? 0
    );

} catch (Throwable $e) {
    error_log('Dashboard error: ' . $e->getMessage());
    $stats = array_fill_keys(['total','abiertos','proceso','resueltos','cerrados','sla_vencido'], 0);
    $ticketsPorEstado = $ticketsPorMes = $ticketsPorTecnico = $slaData = $ultimosTickets = [];
    $promedioAtencion = 0;
}

$pageTitle  = 'Dashboard';
$activeModule = 'dashboard';
include CORE_PATH . '/layout/header.php';
?>

<div class="container-fluid">

    <!-- Page Header -->
    <div class="tx-page-header">
        <div>
            <h1 class="tx-page-title">
                <i class="bi bi-grid-fill me-2" style="color:var(--tx-primary)"></i>
                Dashboard General
            </h1>
            <div class="tx-breadcrumb">
                Hola, <strong><?= e($user['nombre']) ?></strong> — <?= fdatetime(date('Y-m-d H:i:s')) ?>
            </div>
        </div>
        <div class="d-flex gap-2">
            <?php if (Session::hasRole('tecnico')): ?>
            <a href="<?= BASE_URL ?>/modules/tickets/" class="btn-tx">
                <i class="bi bi-headset"></i> Mesa de Ayuda
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Flash Alert -->
    <?= getFlashAlert() ?>

    <!-- ============================================================
         KPI CARDS
         ============================================================ -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="tx-kpi" style="--kpi-color:#0d6efd">
                <i class="bi bi-ticket-perforated tx-kpi-icon"></i>
                <div class="tx-kpi-value"><?= $stats['total'] ?></div>
                <div class="tx-kpi-label">Total Tickets</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="tx-kpi" style="--kpi-color:var(--tx-primary)">
                <i class="bi bi-circle-fill tx-kpi-icon"></i>
                <div class="tx-kpi-value"><?= $stats['abiertos'] ?></div>
                <div class="tx-kpi-label">Abiertos</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="tx-kpi" style="--kpi-color:#6f42c1">
                <i class="bi bi-gear-fill tx-kpi-icon"></i>
                <div class="tx-kpi-value"><?= $stats['proceso'] ?></div>
                <div class="tx-kpi-label">En Proceso</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="tx-kpi" style="--kpi-color:#198754">
                <i class="bi bi-check-circle-fill tx-kpi-icon"></i>
                <div class="tx-kpi-value"><?= $stats['resueltos'] ?></div>
                <div class="tx-kpi-label">Resueltos</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="tx-kpi" style="--kpi-color:#6c757d">
                <i class="bi bi-x-circle-fill tx-kpi-icon"></i>
                <div class="tx-kpi-value"><?= $stats['cerrados'] ?></div>
                <div class="tx-kpi-label">Cerrados</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="tx-kpi" style="--kpi-color:#dc3545">
                <i class="bi bi-exclamation-triangle-fill tx-kpi-icon"></i>
                <div class="tx-kpi-value"><?= $stats['sla_vencido'] ?></div>
                <div class="tx-kpi-label">SLA Vencidos</div>
                <?php if ($stats['sla_vencido'] > 0): ?>
                <div class="tx-kpi-change" style="color:#dc3545">
                    <i class="bi bi-exclamation-circle me-1"></i>Requieren atención
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============================================================
         CHARTS ROW 1
         ============================================================ -->
    <div class="row g-3 mb-4">

        <!-- Tickets por Mes -->
        <div class="col-lg-8">
            <div class="tx-card h-100">
                <div class="tx-card-header">
                    <h6 class="tx-card-title">
                        <i class="bi bi-bar-chart-fill"></i>
                        Tickets por Mes (últimos 6 meses)
                    </h6>
                </div>
                <div class="tx-card-body">
                    <canvas id="chartTicketsMes" height="90"></canvas>
                </div>
            </div>
        </div>

        <!-- Tickets por Estado (Doughnut) -->
        <div class="col-lg-4">
            <div class="tx-card h-100">
                <div class="tx-card-header">
                    <h6 class="tx-card-title">
                        <i class="bi bi-pie-chart-fill"></i>
                        Por Estado
                    </h6>
                </div>
                <div class="tx-card-body d-flex align-items-center justify-content-center">
                    <canvas id="chartEstados" style="max-height:220px"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         CHARTS ROW 2
         ============================================================ -->
    <div class="row g-3 mb-4">

        <!-- SLA Semáforo -->
        <div class="col-md-4">
            <div class="tx-card h-100">
                <div class="tx-card-header">
                    <h6 class="tx-card-title">
                        <i class="bi bi-traffic-light-fill"></i>
                        Semáforo SLA
                    </h6>
                </div>
                <div class="tx-card-body">
                    <canvas id="chartSLA" height="140"></canvas>
                    <div class="mt-3 d-flex flex-wrap gap-2 justify-content-center" style="font-size:12px">
                        <span class="badge bg-success">Verde: 0-5 días</span>
                        <span class="badge bg-warning text-dark">Amarillo: 6-10 días</span>
                        <span class="badge" style="background:#fd7e14">Naranja: 11-14 días</span>
                        <span class="badge bg-danger">Rojo: 15+ días</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tickets por Técnico -->
        <div class="col-md-8">
            <div class="tx-card h-100">
                <div class="tx-card-header">
                    <h6 class="tx-card-title">
                        <i class="bi bi-person-badge-fill"></i>
                        Carga por Técnico (activos)
                    </h6>
                </div>
                <div class="tx-card-body">
                    <canvas id="chartTecnicos" height="90"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         ÚLTIMOS TICKETS
         ============================================================ -->
    <div class="tx-card">
        <div class="tx-card-header">
            <h6 class="tx-card-title">
                <i class="bi bi-clock-history"></i>
                Últimos Tickets
            </h6>
            <a href="<?= BASE_URL ?>/modules/tickets/" class="btn-tx btn-sm" style="font-size:12px">
                Ver todos <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
        <div class="table-responsive">
            <table class="tx-table table mb-0">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Cliente</th>
                        <th>Asunto</th>
                        <th>Estado</th>
                        <th>Prioridad</th>
                        <th>SLA</th>
                        <th>Técnico</th>
                        <th>Fecha</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ultimosTickets)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2 opacity-25"></i>
                            No hay tickets registrados
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($ultimosTickets as $t): ?>
                    <tr>
                        <td>
                            <span class="tx-ticket-code"><?= e($t['codigo']) ?></span>
                        </td>
                        <td>
                            <div class="fw-500"><?= e($t['cliente_nombre']) ?></div>
                            <?php if ($t['cliente_empresa']): ?>
                            <small class="text-muted"><?= e($t['cliente_empresa']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= e(truncate($t['asunto'], 45)) ?></td>
                        <td><?= badgeEstado($t['estado_nombre'], $t['estado_color']) ?></td>
                        <td><?= badgePrioridad($t['prioridad_nombre'], $t['prioridad_color']) ?></td>
                        <td>
                            <?php
                            $dias = (int)$t['dias_abierto'];
                            $slaClass = 'tx-sla-' . $t['sla_estado'];
                            ?>
                            <span class="<?= $slaClass ?>">
                                <i class="bi bi-clock me-1"></i><?= $dias ?>d
                            </span>
                        </td>
                        <td class="text-muted"><?= e($t['tecnico_nombre'] ?? '—') ?></td>
                        <td class="text-muted" style="white-space:nowrap"><?= fdatetime($t['created_at']) ?></td>
                        <td>
                            <a href="<?= BASE_URL ?>/modules/tickets/detalle.php?id=<?= $t['id'] ?>"
                               class="btn btn-sm btn-tx-outline" style="padding:4px 10px;font-size:12px">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /container -->

<?php
// Preparar datos JSON para Charts
$chartMesLabels  = json_encode(array_column($ticketsPorMes, 'mes'));
$chartMesData    = json_encode(array_column($ticketsPorMes, 'total'));
$chartEstLabels  = json_encode(array_column($ticketsPorEstado, 'nombre'));
$chartEstData    = json_encode(array_column($ticketsPorEstado, 'total'));
$chartEstColors  = json_encode(array_column($ticketsPorEstado, 'color'));
$chartTecLabels  = json_encode(array_column($ticketsPorTecnico, 'tecnico'));
$chartTecData    = json_encode(array_column($ticketsPorTecnico, 'total'));

$slaMap = [];
foreach ($slaData as $s) { $slaMap[$s['sla_estado']] = (int)$s['total']; }
$chartSlaData    = json_encode([
    $slaMap['verde']   ?? 0,
    $slaMap['amarillo'] ?? 0,
    $slaMap['naranja'] ?? 0,
    $slaMap['rojo']    ?? 0,
]);

$extraScripts = <<<JS
<script>
const primary = TX.colorPrimario || '#F4A020';

// --- Tickets por Mes ---
new Chart(document.getElementById('chartTicketsMes'), {
    type: 'bar',
    data: {
        labels: $chartMesLabels,
        datasets: [{
            label: 'Tickets',
            data: $chartMesData,
            backgroundColor: primary + '88',
            borderColor: primary,
            borderWidth: 2,
            borderRadius: 8,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#f0f0f0' } },
            x: { grid: { display: false } }
        }
    }
});

// --- Tickets por Estado (Doughnut) ---
new Chart(document.getElementById('chartEstados'), {
    type: 'doughnut',
    data: {
        labels: $chartEstLabels,
        datasets: [{
            data: $chartEstData,
            backgroundColor: $chartEstColors,
            borderWidth: 3,
            borderColor: '#fff',
            hoverBorderWidth: 0,
        }]
    },
    options: {
        cutout: '65%',
        plugins: {
            legend: { position: 'bottom', labels: { padding: 12, font: { size: 11 } } }
        }
    }
});

// --- SLA Doughnut ---
new Chart(document.getElementById('chartSLA'), {
    type: 'doughnut',
    data: {
        labels: ['Verde (0-5d)', 'Amarillo (6-10d)', 'Naranja (11-14d)', 'Rojo (15+d)'],
        datasets: [{
            data: $chartSlaData,
            backgroundColor: ['#198754','#ffc107','#fd7e14','#dc3545'],
            borderWidth: 3,
            borderColor: '#fff',
        }]
    },
    options: {
        cutout: '60%',
        plugins: { legend: { display: false } }
    }
});

// --- Carga por Técnico ---
new Chart(document.getElementById('chartTecnicos'), {
    type: 'bar',
    data: {
        labels: $chartTecLabels,
        datasets: [{
            label: 'Tickets activos',
            data: $chartTecData,
            backgroundColor: '#0d6efd88',
            borderColor: '#0d6efd',
            borderWidth: 2,
            borderRadius: 6,
            borderSkipped: false,
            indexAxis: 'y',
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#f0f0f0' } },
            y: { grid: { display: false } }
        }
    }
});
</script>
JS;

include CORE_PATH . '/layout/footer.php';
?>
