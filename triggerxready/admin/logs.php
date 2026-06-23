<?php
define('TRIGGERX', true);
require_once __DIR__ . '/../core/config/config.php';
requireLogin();
requireRole('superadmin');
securityHeaders();

$db = Database::getInstance();

$logs = $db->fetchAll(
    "SELECT l.*, CONCAT(u.nombre,' ',u.apellido) AS usuario_nombre
     FROM logs_auditoria l
     LEFT JOIN usuarios u ON l.usuario_id = u.id
     ORDER BY l.created_at DESC LIMIT 1000"
);

$pageTitle    = 'Logs de Auditoría';
$activeModule = 'dashboard';
include CORE_PATH . '/layout/header.php';
?>
<div class="container-fluid">
    <div class="tx-page-header">
        <div>
            <h1 class="tx-page-title"><i class="bi bi-journal-text me-2" style="color:var(--tx-primary)"></i>Logs de Auditoría</h1>
            <div class="tx-breadcrumb"><a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a> › Logs</div>
        </div>
    </div>

    <div class="tx-card">
        <div class="table-responsive">
            <table id="tablaLogs" class="tx-table table mb-0">
                <thead>
                    <tr>
                        <th>#</th><th>Fecha</th><th>Usuario</th><th>Acción</th>
                        <th>Módulo</th><th>Descripción</th><th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $l): ?>
                    <tr>
                        <td><?= $l['id'] ?></td>
                        <td style="white-space:nowrap;font-size:12px"><?= fdatetime($l['created_at']) ?></td>
                        <td><?= e($l['usuario_nombre'] ?? 'Sistema') ?></td>
                        <td><code style="font-size:12px"><?= e($l['accion']) ?></code></td>
                        <td><span class="badge bg-secondary" style="font-size:11px"><?= e($l['modulo'] ?? '—') ?></span></td>
                        <td style="font-size:12px;max-width:300px"><?= e(truncate($l['descripcion'] ?? '', 80)) ?></td>
                        <td><code style="font-size:11px"><?= e($l['ip'] ?? '—') ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$extraScripts = '<script>$(document).ready(function(){ txDataTable("#tablaLogs", {order:[[0,"desc"]]}); });</script>';
include CORE_PATH . '/layout/footer.php';
?>
