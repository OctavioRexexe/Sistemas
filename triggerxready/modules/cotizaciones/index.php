<?php
/**
 * TRIGGERX - Módulo Cotizaciones: Listado
 */
define('TRIGGERX', true);
require_once __DIR__ . '/../../core/config/config.php';
requireLogin();
requireModule('cotizaciones');
securityHeaders();

$user = Session::getUser();
$db   = Database::getInstance();

$where  = ['1=1'];
$params = [];

// Técnico solo ve las suyas; admin ve todas
if (!Session::hasRole('admin')) {
    $where[]  = 'c.usuario_id = ?';
    $params[] = $user['id'];
}

$filtroEstado = get_param('estado', '');
if ($filtroEstado) { $where[] = 'c.estado = ?'; $params[] = $filtroEstado; }

$cotizaciones = $db->fetchAll(
    "SELECT c.*, CONCAT(u.nombre,' ',u.apellido) AS creado_por, t.codigo AS ticket_codigo
     FROM cotizaciones c
     LEFT JOIN usuarios u ON c.usuario_id = u.id
     LEFT JOIN tickets t ON c.ticket_id = t.id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY c.id DESC LIMIT 500",
    $params
);

$pageTitle    = 'Cotizaciones';
$activeModule = 'cotizaciones';
include CORE_PATH . '/layout/header.php';
?>

<div class="container-fluid">

    <div class="tx-page-header">
        <div>
            <h1 class="tx-page-title">
                <i class="bi bi-file-earmark-spreadsheet-fill me-2" style="color:var(--tx-primary)"></i>
                Cotizaciones
            </h1>
            <div class="tx-breadcrumb">
                <a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a>
                <span class="mx-1">›</span> Cotizaciones
            </div>
        </div>
        <a href="<?= BASE_URL ?>/modules/cotizaciones/form.php" class="btn-tx">
            <i class="bi bi-plus-circle-fill"></i> Nueva Cotización
        </a>
    </div>

    <div class="d-flex gap-2 mb-4">
        <a href="<?= BASE_URL ?>/modules/cotizaciones/empresas.php" class="btn btn-sm" style="background:var(--tx-bg);border:1px solid var(--tx-border)">
            <i class="bi bi-building me-1"></i>Gestionar Empresas
        </a>
        <a href="<?= BASE_URL ?>/modules/cotizaciones/productos.php" class="btn btn-sm" style="background:var(--tx-bg);border:1px solid var(--tx-border)">
            <i class="bi bi-box-seam me-1"></i>Gestionar Productos/Servicios
        </a>
    </div>

    <?= getFlashAlert() ?>

    <!-- Filtros -->
    <div class="tx-card mb-4">
        <div class="tx-card-body py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="tx-label mb-1">Estado</label>
                    <select name="estado" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Todos</option>
                        <?php foreach (['borrador','enviada','aprobada','rechazada','vencida'] as $est):
                            [$nombre,] = badgeEstadoCotizacion($est); ?>
                        <option value="<?= $est ?>" <?= $filtroEstado === $est ? 'selected' : '' ?>><?= $nombre ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla -->
    <div class="tx-card">
        <div class="tx-card-header">
            <h6 class="tx-card-title"><i class="bi bi-list-ul"></i>Listado</h6>
            <span class="badge bg-secondary"><?= count($cotizaciones) ?> cotización(es)</span>
        </div>
        <div class="table-responsive">
            <table class="tx-table table mb-0">
                <thead>
                    <tr>
                        <th>Código</th><th>Empresa / Cliente</th><th>Ticket</th>
                        <th>Emisión</th><th>Vencimiento</th><th>Total</th>
                        <th>Estado</th><th>Creado por</th><th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($cotizaciones)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">Sin cotizaciones registradas</td></tr>
                <?php endif; ?>
                <?php foreach ($cotizaciones as $c): [$estNombre, $estColor] = badgeEstadoCotizacion($c['estado']); ?>
                <tr>
                    <td class="font-monospace fw-600"><?= e($c['codigo']) ?></td>
                    <td>
                        <div class="fw-500"><?= e($c['empresa_razon_social']) ?></div>
                        <small class="text-muted"><?= e($c['cliente_nombre']) ?></small>
                    </td>
                    <td>
                        <?php if ($c['ticket_id']): ?>
                        <a href="<?= BASE_URL ?>/modules/tickets/detalle.php?id=<?= $c['ticket_id'] ?>" style="font-size:12.5px">
                            <?= e($c['ticket_codigo']) ?>
                        </a>
                        <?php else: ?>
                        <span class="text-muted" style="font-size:12px">— Libre —</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12.5px"><?= fdate($c['fecha_emision']) ?></td>
                    <td style="font-size:12.5px"><?= $c['fecha_vencimiento'] ? fdate($c['fecha_vencimiento']) : '—' ?></td>
                    <td class="fw-600">S/ <?= number_format((float)$c['total'], 2) ?></td>
                    <td><span class="badge" style="background:<?= $estColor ?>"><?= $estNombre ?></span></td>
                    <td style="font-size:12.5px"><?= e($c['creado_por'] ?? '—') ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="<?= BASE_URL ?>/modules/cotizaciones/form.php?id=<?= $c['id'] ?>"
                               class="btn btn-sm btn-tx-outline" style="padding:3px 8px" title="Ver / Editar">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if ($c['pdf_ruta']): ?>
                            <a href="<?= UPLOADS_URL ?>/<?= e($c['pdf_ruta']) ?>" target="_blank"
                               class="btn btn-sm" style="padding:3px 8px;background:var(--tx-bg);border:1px solid var(--tx-border)" title="Descargar PDF">
                                <i class="bi bi-download"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include CORE_PATH . '/layout/footer.php'; ?>
