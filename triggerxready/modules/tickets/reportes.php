<?php
define('TRIGGERX', true);
require_once __DIR__ . '/../../core/config/config.php';
requireLogin();
requireRole('admin');
requireModule('tickets');
securityHeaders();

$db = Database::getInstance();

// Rangos de fechas
$desde = get_param('desde', date('Y-m-01'));
$hasta = get_param('hasta', date('Y-m-d'));

// Estadísticas del período
$total    = (int)$db->fetchColumn("SELECT COUNT(*) FROM tickets WHERE DATE(created_at) BETWEEN ? AND ?", [$desde, $hasta]);
$resueltos= (int)$db->fetchColumn("SELECT COUNT(*) FROM tickets t JOIN estados_ticket e ON t.estado_id=e.id WHERE e.es_final=1 AND DATE(t.created_at) BETWEEN ? AND ?", [$desde, $hasta]);
$vencidos = (int)$db->fetchColumn("SELECT COUNT(*) FROM tickets WHERE sla_vencido=1 AND DATE(created_at) BETWEEN ? AND ?", [$desde, $hasta]);
$promHoras= round((float)($db->fetchColumn("SELECT AVG(TIMESTAMPDIFF(HOUR,created_at,fecha_resolucion)) FROM tickets WHERE fecha_resolucion IS NOT NULL AND DATE(created_at) BETWEEN ? AND ?", [$desde, $hasta]) ?? 0));

// Por técnico
$porTecnico = $db->fetchAll(
    "SELECT CONCAT(u.nombre,' ',u.apellido) AS tecnico,
            COUNT(t.id) AS total,
            SUM(CASE WHEN e.es_final=1 THEN 1 ELSE 0 END) AS resueltos,
            AVG(TIMESTAMPDIFF(HOUR,t.created_at,t.fecha_resolucion)) AS prom_horas
     FROM tickets t
     LEFT JOIN usuarios u ON t.tecnico_id = u.id
     LEFT JOIN estados_ticket e ON t.estado_id = e.id
     WHERE DATE(t.created_at) BETWEEN ? AND ?
     GROUP BY t.tecnico_id ORDER BY total DESC",
    [$desde, $hasta]
);

// Por categoría
$porCategoria = $db->fetchAll(
    "SELECT COALESCE(c.nombre,'Sin categoría') AS categoria, COUNT(t.id) AS total
     FROM tickets t LEFT JOIN categorias_ticket c ON t.categoria_id=c.id
     WHERE DATE(t.created_at) BETWEEN ? AND ?
     GROUP BY t.categoria_id ORDER BY total DESC",
    [$desde, $hasta]
);

// Por prioridad
$porPrioridad = $db->fetchAll(
    "SELECT p.nombre, p.color, COUNT(t.id) AS total
     FROM tickets t LEFT JOIN prioridades_ticket p ON t.prioridad_id=p.id
     WHERE DATE(t.created_at) BETWEEN ? AND ?
     GROUP BY t.prioridad_id",
    [$desde, $hasta]
);

$pageTitle    = 'Reportes — Tickets';
$activeModule = 'tickets';
include CORE_PATH . '/layout/header.php';
?>
<div class="container-fluid">
    <div class="tx-page-header">
        <div>
            <h1 class="tx-page-title"><i class="bi bi-bar-chart-fill me-2" style="color:var(--tx-primary)"></i>Reportes</h1>
            <div class="tx-breadcrumb">
                <a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a> ›
                <a href="<?= BASE_URL ?>/modules/tickets/">Tickets</a> › Reportes
            </div>
        </div>
        <a href="<?= BASE_URL ?>/modules/tickets/ajax/export.php" class="btn-tx-outline">
            <i class="bi bi-download me-1"></i>Exportar CSV
        </a>
    </div>

    <!-- Filtro fechas -->
    <div class="tx-card mb-4">
        <div class="tx-card-body py-3">
            <form method="GET" class="d-flex gap-3 align-items-end flex-wrap">
                <div>
                    <label class="tx-label mb-1">Desde</label>
                    <input type="date" name="desde" class="form-control form-control-sm" value="<?= e($desde) ?>">
                </div>
                <div>
                    <label class="tx-label mb-1">Hasta</label>
                    <input type="date" name="hasta" class="form-control form-control-sm" value="<?= e($hasta) ?>">
                </div>
                <button type="submit" class="btn-tx" style="height:34px">
                    <i class="bi bi-search me-1"></i>Filtrar
                </button>
                <a href="?" class="btn btn-sm" style="height:34px;line-height:20px;background:var(--tx-bg);border:1px solid var(--tx-border)">
                    Este mes
                </a>
            </form>
        </div>
    </div>

    <!-- KPIs del período -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="tx-kpi" style="--kpi-color:var(--tx-primary)">
                <i class="bi bi-ticket-perforated tx-kpi-icon"></i>
                <div class="tx-kpi-value"><?= $total ?></div>
                <div class="tx-kpi-label">Tickets creados</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="tx-kpi" style="--kpi-color:#198754">
                <i class="bi bi-check-circle-fill tx-kpi-icon"></i>
                <div class="tx-kpi-value"><?= $resueltos ?></div>
                <div class="tx-kpi-label">Resueltos</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="tx-kpi" style="--kpi-color:#dc3545">
                <i class="bi bi-exclamation-triangle-fill tx-kpi-icon"></i>
                <div class="tx-kpi-value"><?= $vencidos ?></div>
                <div class="tx-kpi-label">SLA Vencidos</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="tx-kpi" style="--kpi-color:#6f42c1">
                <i class="bi bi-clock-history tx-kpi-icon"></i>
                <div class="tx-kpi-value"><?= $promHoras ?>h</div>
                <div class="tx-kpi-label">Tiempo promedio</div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- Rendimiento por Técnico -->
        <div class="col-lg-8">
            <div class="tx-card h-100">
                <div class="tx-card-header">
                    <h6 class="tx-card-title"><i class="bi bi-person-badge-fill"></i>Rendimiento por Técnico</h6>
                </div>
                <div class="table-responsive">
                    <table class="tx-table table mb-0">
                        <thead>
                            <tr>
                                <th>Técnico</th><th>Asignados</th><th>Resueltos</th>
                                <th>Tasa</th><th>Tiempo Prom.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($porTecnico)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">Sin datos en el período</td></tr>
                            <?php else: ?>
                            <?php foreach ($porTecnico as $t): ?>
                            <?php
                            $tasa = $t['total'] > 0 ? round($t['resueltos'] / $t['total'] * 100) : 0;
                            $prom = round((float)($t['prom_horas'] ?? 0));
                            ?>
                            <tr>
                                <td class="fw-500"><?= e($t['tecnico'] ?? 'Sin asignar') ?></td>
                                <td><?= $t['total'] ?></td>
                                <td class="text-success fw-600"><?= $t['resueltos'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="flex:1;height:6px;background:var(--tx-border);border-radius:10px;overflow:hidden">
                                            <div style="width:<?= $tasa ?>%;height:100%;background:<?= $tasa>=80?'#198754':($tasa>=50?'#F4A020':'#dc3545') ?>;border-radius:10px"></div>
                                        </div>
                                        <span style="font-size:12px;font-weight:600;min-width:35px"><?= $tasa ?>%</span>
                                    </div>
                                </td>
                                <td class="text-muted"><?= $prom ?>h</td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Por Categoría y Prioridad -->
        <div class="col-lg-4">
            <div class="tx-card mb-4">
                <div class="tx-card-header">
                    <h6 class="tx-card-title"><i class="bi bi-tag-fill"></i>Por Categoría</h6>
                </div>
                <div class="tx-card-body p-0">
                    <?php foreach ($porCategoria as $c): ?>
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom" style="font-size:13px">
                        <span><?= e($c['categoria']) ?></span>
                        <span class="badge" style="background:var(--tx-primary)"><?= $c['total'] ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($porCategoria)): ?>
                    <div class="text-center text-muted py-3 px-3" style="font-size:13px">Sin datos</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="tx-card">
                <div class="tx-card-header">
                    <h6 class="tx-card-title"><i class="bi bi-flag-fill"></i>Por Prioridad</h6>
                </div>
                <div class="tx-card-body">
                    <canvas id="chartPrioridad" height="160"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$chartPriorLabels = json_encode(array_column($porPrioridad, 'nombre'));
$chartPriorData   = json_encode(array_column($porPrioridad, 'total'));
$chartPriorColors = json_encode(array_column($porPrioridad, 'color'));

$extraScripts = <<<JS
<script>
new Chart(document.getElementById('chartPrioridad'), {
    type: 'doughnut',
    data: {
        labels: $chartPriorLabels,
        datasets: [{ data: $chartPriorData, backgroundColor: $chartPriorColors, borderWidth:3, borderColor:'#fff' }]
    },
    options: { cutout:'60%', plugins:{ legend:{ position:'bottom', labels:{ font:{size:11} } } } }
});
</script>
JS;
include CORE_PATH . '/layout/footer.php';
?>
