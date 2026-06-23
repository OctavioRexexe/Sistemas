<?php
define('TRIGGERX', true);
require_once __DIR__ . '/../../core/config/config.php';
requireLogin();
requireModule('tickets');
securityHeaders();

$id   = (int)get_param('id');
$db   = Database::getInstance();
$user = Session::getUser();

if (!$id) redirect(BASE_URL . '/modules/tickets/');

$ticket = $db->fetch("SELECT * FROM v_tickets_dashboard WHERE id = ?", [$id]);
if (!$ticket) {
    redirectWith(BASE_URL . '/modules/tickets/', 'danger', 'Ticket no encontrado');
}

// Control acceso: cliente solo sus tickets
if ($user['rol_slug'] === 'cliente') {
    if ($ticket['usuario_id'] != $user['id'] && $ticket['cliente_email'] !== $user['email']) {
        redirect(BASE_URL . '/modules/tickets/');
    }
}

// Comentarios
$comentarios = $db->fetchAll(
    "SELECT c.*, CONCAT(u.nombre,' ',u.apellido) AS nombre_usuario, r.slug AS rol_slug
     FROM comentarios_ticket c
     LEFT JOIN usuarios u ON c.usuario_id = u.id
     LEFT JOIN roles r ON u.rol_id = r.id
     WHERE c.ticket_id = ? ORDER BY c.created_at ASC",
    [$id]
);

// Adjuntos
$adjuntos = $db->fetchAll(
    "SELECT * FROM adjuntos_ticket WHERE ticket_id = ? ORDER BY created_at",
    [$id]
);

// Historial
$historial = $db->fetchAll(
    "SELECT h.*, CONCAT(u.nombre,' ',u.apellido) AS nombre_usuario
     FROM historial_ticket h
     LEFT JOIN usuarios u ON h.usuario_id = u.id
     WHERE h.ticket_id = ? ORDER BY h.created_at DESC LIMIT 50",
    [$id]
);

// Constancias técnicas del ticket
$constancias = $db->fetchAll(
    "SELECT ct.*, CONCAT(u.nombre,' ',u.apellido) AS tecnico_nombre, a.ruta AS pdf_ruta
     FROM constancias_tecnicas ct
     LEFT JOIN usuarios u ON ct.tecnico_id = u.id
     LEFT JOIN adjuntos_ticket a ON ct.adjunto_id = a.id
     WHERE ct.ticket_id = ? ORDER BY ct.created_at DESC",
    [$id]
);

// Cotizaciones relacionadas al ticket
$cotizacionesTicket = $db->fetchAll(
    "SELECT * FROM cotizaciones WHERE ticket_id = ? ORDER BY created_at DESC",
    [$id]
);

// Técnicos para asignar
$tecnicos = [];
if (Session::hasRole('admin')) {
    $tecnicos = $db->fetchAll(
        "SELECT u.id, CONCAT(u.nombre,' ',u.apellido) AS nombre FROM usuarios u
         JOIN roles r ON u.rol_id=r.id WHERE r.nivel>=2 AND u.activo=1 ORDER BY u.nombre"
    );
}
$estados    = $db->fetchAll("SELECT * FROM estados_ticket ORDER BY orden");
$prioridades = $db->fetchAll("SELECT * FROM prioridades_ticket ORDER BY nivel");

$diasAbierto = (int)$ticket['dias_abierto'];
$slaClass    = 'tx-sla-' . $ticket['sla_estado'];
$pageTitle   = $ticket['codigo'] . ' — Detalle Ticket';
$activeModule = 'tickets';
include CORE_PATH . '/layout/header.php';
?>

<div class="container-fluid">

    <!-- Breadcrumb -->
    <div class="tx-page-header">
        <div>
            <h1 class="tx-page-title">
                <i class="bi bi-ticket-perforated me-2" style="color:var(--tx-primary)"></i>
                Detalle del Ticket
            </h1>
            <div class="tx-breadcrumb">
                <a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a> ›
                <a href="<?= BASE_URL ?>/modules/tickets/">Tickets</a> ›
                <?= e($ticket['codigo']) ?>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if (Session::hasRole('tecnico')): ?>
            <a href="<?= BASE_URL ?>/modules/tickets/constancia_form.php?ticket_id=<?= $id ?>" class="btn-tx-outline btn-sm">
                <i class="bi bi-file-earmark-plus"></i> Nueva Constancia Técnica
            </a>
            <a href="<?= BASE_URL ?>/modules/cotizaciones/form.php?ticket_id=<?= $id ?>" class="btn-tx-outline btn-sm">
                <i class="bi bi-file-earmark-spreadsheet"></i> Nueva Cotización
            </a>
            <?php endif; ?>
            <?php if (Session::hasRole('admin')): ?>
            <button class="btn-tx-outline btn-sm" data-bs-toggle="modal" data-bs-target="#modalAsignar">
                <i class="bi bi-person-plus"></i> Asignar
            </button>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/modules/tickets/" class="btn btn-sm" style="background:var(--tx-bg);border:1px solid var(--tx-border)">
                <i class="bi bi-arrow-left me-1"></i>Volver
            </a>
        </div>
    </div>

    <?= getFlashAlert() ?>

    <!-- HERO TICKET -->
    <div class="tx-ticket-hero mb-4">
        <div class="row align-items-start">
            <div class="col-lg-8">
                <div class="tx-ticket-id"><?= e($ticket['codigo']) ?></div>
                <div class="tx-ticket-subject"><?= e($ticket['asunto']) ?></div>
                <div class="tx-ticket-meta">
                    <span class="tx-ticket-meta-item">
                        <i class="bi bi-calendar3"></i>
                        <?= fdatetime($ticket['created_at']) ?>
                    </span>
                    <span class="tx-ticket-meta-item">
                        <i class="bi bi-clock"></i>
                        <?= $diasAbierto ?> día(s) abierto
                    </span>
                    <?php if ($ticket['tecnico_nombre']): ?>
                    <span class="tx-ticket-meta-item">
                        <i class="bi bi-person-badge"></i>
                        <?= e($ticket['tecnico_nombre']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($ticket['categoria_nombre']): ?>
                    <span class="tx-ticket-meta-item">
                        <i class="bi bi-tag"></i>
                        <?= e($ticket['categoria_nombre']) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-4 mt-3 mt-lg-0 d-flex flex-wrap gap-2 justify-content-lg-end align-items-start">
                <span class="badge fs-6" style="background:<?= e($ticket['estado_color']) ?>">
                    <?= e($ticket['estado_nombre']) ?>
                </span>
                <span class="badge border fs-6"
                      style="color:<?= e($ticket['prioridad_color']) ?>;border-color:<?= e($ticket['prioridad_color']) ?>!important;background:transparent">
                    <?= e($ticket['prioridad_nombre']) ?>
                </span>
                <!-- SLA Indicator -->
                <div class="w-100 mt-1">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small style="color:rgba(255,255,255,.7)">SLA</small>
                        <small class="<?= $slaClass ?>"><?= $diasAbierto ?>d / <?= Config::sla()['max_dias'] ?>d</small>
                    </div>
                    <div class="tx-sla-bar">
                        <?php
                        $slaMax = Config::sla()['max_dias'];
                        $pct    = min(100, round(($diasAbierto / $slaMax) * 100));
                        $barColor = ['verde'=>'#198754','amarillo'=>'#ffc107','naranja'=>'#fd7e14','rojo'=>'#dc3545'][$ticket['sla_estado']] ?? '#198754';
                        ?>
                        <div class="tx-sla-bar-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- COLUMNA IZQUIERDA: Info + Comentarios -->
        <div class="col-lg-8">

            <!-- Datos del Cliente -->
            <div class="tx-card mb-4">
                <div class="tx-card-header">
                    <h6 class="tx-card-title"><i class="bi bi-person-fill"></i>Datos del Cliente</h6>
                </div>
                <div class="tx-card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="tx-label">Nombre</label>
                            <div class="fw-500"><?= e($ticket['cliente_nombre']) ?></div>
                        </div>
                        <?php if ($ticket['cliente_empresa']): ?>
                        <div class="col-md-6">
                            <label class="tx-label">Empresa</label>
                            <div><?= e($ticket['cliente_empresa']) ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-6">
                            <label class="tx-label">Correo</label>
                            <a href="mailto:<?= e($ticket['cliente_email']) ?>"><?= e($ticket['cliente_email']) ?></a>
                        </div>
                        <?php if ($ticket['cliente_telefono'] ?? null): ?>
                        <div class="col-md-6">
                            <label class="tx-label">Teléfono</label>
                            <div><?= e($ticket['cliente_telefono']) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php
                        $raw = $db->fetch("SELECT serie_equipo, modelo_equipo FROM tickets WHERE id=?",[$id]);
                        if (!empty($raw['serie_equipo'])): ?>
                        <div class="col-md-6">
                            <label class="tx-label">Serie del Equipo</label>
                            <code><?= e($raw['serie_equipo']) ?></code>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($raw['modelo_equipo'])): ?>
                        <div class="col-md-6">
                            <label class="tx-label">Modelo del Equipo</label>
                            <div><?= e($raw['modelo_equipo']) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Descripción del Problema -->
            <div class="tx-card mb-4">
                <div class="tx-card-header">
                    <h6 class="tx-card-title"><i class="bi bi-file-text-fill"></i>Descripción del Problema</h6>
                </div>
                <div class="tx-card-body">
                    <div style="white-space:pre-wrap;font-size:14px;line-height:1.7"><?= e($ticket['descripcion']) ?></div>
                    <?php if (!empty($adjuntos)): ?>
                    <hr class="my-3">
                    <label class="tx-label mb-2">Adjuntos del Ticket</label>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($adjuntos as $adj): ?>
                        <?php if (!$adj['comentario_id']): ?>
                        <a href="<?= UPLOADS_URL ?>/<?= e($adj['ruta']) ?>" target="_blank"
                           class="d-flex align-items-center gap-2 p-2 rounded border text-decoration-none"
                           style="font-size:12px;background:var(--tx-bg)">
                            <i class="bi <?= str_contains($adj['mime_type'],'pdf') ? 'bi-file-pdf text-danger' : 'bi-image text-primary' ?> fs-5"></i>
                            <span><?= e(truncate($adj['nombre_original'], 30)) ?></span>
                            <span class="text-muted"><?= formatBytes($adj['tamano']) ?></span>
                        </a>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Constancias Técnicas -->
            <?php if (!empty($constancias)): ?>
            <div class="tx-card mb-4">
                <div class="tx-card-header">
                    <h6 class="tx-card-title"><i class="bi bi-file-earmark-text-fill"></i>Constancias Técnicas</h6>
                    <span class="badge bg-secondary"><?= count($constancias) ?></span>
                </div>
                <div class="tx-card-body p-0">
                    <div class="table-responsive">
                        <table class="tx-table table mb-0">
                            <thead>
                                <tr>
                                    <th>Código</th><th>Estado</th><th>Técnico</th>
                                    <th>Fecha</th><th>Calificación</th><th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($constancias as $c): ?>
                            <tr>
                                <td class="font-monospace fw-600"><?= e($c['codigo']) ?></td>
                                <td>
                                    <span class="badge" style="background:<?= $c['estado'] === 'final' ? '#198754' : '#6c757d' ?>">
                                        <?= $c['estado'] === 'final' ? 'Final' : 'Borrador' ?>
                                    </span>
                                </td>
                                <td style="font-size:12.5px"><?= e($c['tecnico_nombre'] ?? '—') ?></td>
                                <td style="font-size:12px;color:#6c757d"><?= fdatetime($c['created_at']) ?></td>
                                <td>
                                    <?php if ($c['calificacion']): ?>
                                    <span style="color:#F4A020"><?= str_repeat('★', (int)$c['calificacion']) . str_repeat('☆', 5 - (int)$c['calificacion']) ?></span>
                                    <?php else: ?>
                                    <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="<?= BASE_URL ?>/modules/tickets/constancia_form.php?id=<?= $c['id'] ?>"
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
            <?php endif; ?>

            <!-- Cotizaciones -->
            <?php if (!empty($cotizacionesTicket)): ?>
            <div class="tx-card mb-4">
                <div class="tx-card-header">
                    <h6 class="tx-card-title"><i class="bi bi-file-earmark-spreadsheet-fill"></i>Cotizaciones</h6>
                    <span class="badge bg-secondary"><?= count($cotizacionesTicket) ?></span>
                </div>
                <div class="tx-card-body p-0">
                    <div class="table-responsive">
                        <table class="tx-table table mb-0">
                            <thead>
                                <tr><th>Código</th><th>Estado</th><th>Total</th><th>Fecha</th><th>Acciones</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($cotizacionesTicket as $cz): [$czNombre, $czColor] = badgeEstadoCotizacion($cz['estado']); ?>
                            <tr>
                                <td class="font-monospace fw-600"><?= e($cz['codigo']) ?></td>
                                <td><span class="badge" style="background:<?= $czColor ?>"><?= $czNombre ?></span></td>
                                <td class="fw-600">S/ <?= number_format((float)$cz['total'], 2) ?></td>
                                <td style="font-size:12px;color:#6c757d"><?= fdate($cz['created_at']) ?></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="<?= BASE_URL ?>/modules/cotizaciones/form.php?id=<?= $cz['id'] ?>"
                                           class="btn btn-sm btn-tx-outline" style="padding:3px 8px" title="Ver / Editar">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if ($cz['pdf_ruta']): ?>
                                        <a href="<?= UPLOADS_URL ?>/<?= e($cz['pdf_ruta']) ?>" target="_blank"
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
            <?php endif; ?>

            <!-- Timeline / Comentarios -->
            <div class="tx-card mb-4">
                <div class="tx-card-header">
                    <h6 class="tx-card-title"><i class="bi bi-chat-left-text-fill"></i>Historial de Comunicación</h6>
                    <span class="badge bg-secondary"><?= count($comentarios) ?></span>
                </div>
                <div class="tx-card-body">

                    <?php if (empty($comentarios)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-chat-dots fs-2 d-block mb-2 opacity-25"></i>
                        Sin comentarios todavía
                    </div>
                    <?php else: ?>
                    <div class="tx-timeline" id="timelineComentarios">
                        <?php foreach ($comentarios as $com): ?>
                        <div class="tx-timeline-item">
                            <div class="tx-timeline-dot dot-<?= e($com['tipo']) ?>"></div>
                            <div class="tx-timeline-meta">
                                <strong><?= e($com['autor_nombre'] ?? $com['nombre_usuario'] ?? 'Sistema') ?></strong>
                                <?php if ($com['tipo'] === 'interno'): ?>
                                <span class="badge" style="background:#6f42c1;font-size:10px">Interno</span>
                                <?php elseif ($com['tipo'] === 'solucion'): ?>
                                <span class="badge bg-success" style="font-size:10px">✓ Solución</span>
                                <?php endif; ?>
                                · <?= fdatetime($com['created_at']) ?>
                            </div>
                            <div class="tx-timeline-content <?= $com['tipo'] ?>">
                                <div style="white-space:pre-wrap;line-height:1.7"><?= e($com['contenido']) ?></div>
                                <?php
                                // Adjuntos del comentario
                                $adjCom = array_filter($adjuntos, fn($a) => $a['comentario_id'] == $com['id']);
                                if (!empty($adjCom)): ?>
                                <div class="mt-2 d-flex flex-wrap gap-1">
                                    <?php foreach ($adjCom as $a): ?>
                                    <a href="<?= UPLOADS_URL ?>/<?= e($a['ruta']) ?>" target="_blank"
                                       class="badge" style="background:var(--tx-border);color:var(--tx-text);font-size:11px;text-decoration:none">
                                        <i class="bi bi-paperclip me-1"></i><?= e(truncate($a['nombre_original'],20)) ?>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Formulario de respuesta -->
                    <?php if (!in_array($ticket['estado_slug'], ['cerrado'])): ?>
                    <hr class="my-3">
                    <form id="formComentario" enctype="multipart/form-data">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="comentar">
                        <input type="hidden" name="ticket_id" value="<?= $id ?>">
                        <div class="mb-3">
                            <textarea name="contenido" class="form-control" rows="4"
                                      placeholder="Escribe tu respuesta..." required></textarea>
                        </div>
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex gap-2 align-items-center">
                                <?php if (Session::hasRole('tecnico')): ?>
                                <select name="tipo" class="form-select form-select-sm" style="width:auto">
                                    <option value="publico">Público (visible al cliente)</option>
                                    <option value="interno">Interno (solo staff)</option>
                                    <option value="solucion">✓ Marcar como Solución</option>
                                </select>
                                <?php else: ?>
                                <input type="hidden" name="tipo" value="publico">
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-2">
                                <label class="btn btn-sm" style="background:var(--tx-bg);border:1px solid var(--tx-border);cursor:pointer;font-size:12px">
                                    <i class="bi bi-paperclip me-1"></i>Adjuntar
                                    <input type="file" name="adjuntos[]" multiple accept=".jpg,.jpeg,.png,.pdf" style="display:none" id="adjComInput">
                                </label>
                                <span id="adjComLabel" class="text-muted" style="font-size:12px;align-self:center"></span>
                                <button type="submit" class="btn-tx" id="btnEnviar">
                                    <i class="bi bi-send-fill me-1"></i>Enviar
                                </button>
                            </div>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- COLUMNA DERECHA: Sidebar -->
        <div class="col-lg-4">

            <!-- Cambiar Estado -->
            <?php if (Session::hasRole('tecnico') && !in_array($ticket['estado_slug'],['cerrado'])): ?>
            <div class="tx-card mb-4">
                <div class="tx-card-header">
                    <h6 class="tx-card-title"><i class="bi bi-sliders"></i>Gestión</h6>
                </div>
                <div class="tx-card-body">
                    <label class="tx-label">Cambiar Estado</label>
                    <div class="d-flex flex-column gap-2 mb-3">
                        <?php foreach ($estados as $e): ?>
                        <?php if ($e['id'] != $ticket['estado_id']): ?>
                        <button class="btn btn-sm w-100 text-start btnCambiarEstado"
                                data-id="<?= $e['id'] ?>" data-nombre="<?= e($e['nombre']) ?>"
                                style="background:<?= e($e['color']) ?>22;color:<?= e($e['color']) ?>;border:1px solid <?= e($e['color']) ?>44;border-radius:8px">
                            <i class="bi <?= e($e['icono']) ?> me-2"></i><?= e($e['nombre']) ?>
                        </button>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <?php if (Session::hasRole('admin')): ?>
                    <label class="tx-label mt-2">Cambiar Prioridad</label>
                    <select class="form-select form-select-sm" id="selPrioridad">
                        <?php foreach ($prioridades as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $p['nombre'] === $ticket['prioridad_nombre'] ? 'selected' : '' ?>>
                            <?= e($p['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn-tx btn-sm mt-2 w-100" id="btnCambiarPrioridad" style="font-size:12px">
                        Actualizar Prioridad
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Info SLA -->
            <div class="tx-card mb-4">
                <div class="tx-card-header">
                    <h6 class="tx-card-title"><i class="bi bi-shield-check"></i>SLA</h6>
                </div>
                <div class="tx-card-body">
                    <div class="row g-2 text-center">
                        <div class="col-6">
                            <div class="p-2 rounded" style="background:var(--tx-bg)">
                                <div class="fw-800 fs-4 <?= $slaClass ?>"><?= $diasAbierto ?></div>
                                <div style="font-size:11px;color:var(--tx-muted)">Días abierto</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 rounded" style="background:var(--tx-bg)">
                                <div class="fw-800 fs-4" style="color:#6c757d">
                                    <?= max(0, Config::sla()['max_dias'] - $diasAbierto) ?>
                                </div>
                                <div style="font-size:11px;color:var(--tx-muted)">Días restantes</div>
                            </div>
                        </div>
                    </div>
                    <?php if ($ticket['primera_respuesta']): ?>
                    <div class="mt-3 p-2 rounded" style="background:var(--tx-bg)">
                        <div style="font-size:11px;color:var(--tx-muted)">Primera respuesta</div>
                        <div class="fw-500" style="font-size:13px"><?= fdatetime($ticket['primera_respuesta']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($ticket['fecha_resolucion']): ?>
                    <div class="mt-2 p-2 rounded" style="background:#f0fdf4;border:1px solid #a7f3d0">
                        <div style="font-size:11px;color:#166534">Resuelto el</div>
                        <div class="fw-500" style="font-size:13px;color:#166534"><?= fdatetime($ticket['fecha_resolucion']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Historial -->
            <div class="tx-card">
                <div class="tx-card-header">
                    <h6 class="tx-card-title"><i class="bi bi-journal-text"></i>Historial</h6>
                </div>
                <div class="tx-card-body p-0">
                    <div style="max-height:280px;overflow-y:auto">
                        <?php foreach ($historial as $h): ?>
                        <div class="px-3 py-2 border-bottom" style="font-size:12px">
                            <div class="d-flex justify-content-between">
                                <span class="fw-600"><?= e($h['accion']) ?></span>
                                <span class="text-muted"><?= timeAgo($h['created_at']) ?></span>
                            </div>
                            <?php if ($h['detalle']): ?>
                            <div class="text-muted"><?= e($h['detalle']) ?></div>
                            <?php endif; ?>
                            <?php if ($h['nombre_usuario']): ?>
                            <div style="color:var(--tx-primary);font-size:11px"><?= e($h['nombre_usuario']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($historial)): ?>
                        <div class="text-center text-muted p-3" style="font-size:12px">Sin historial</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Modal Asignar -->
<?php if (Session::hasRole('admin')): ?>
<div class="modal fade" id="modalAsignar" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--tx-primary);color:#fff">
                <h6 class="modal-title"><i class="bi bi-person-plus me-2"></i>Asignar Técnico</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?= csrfField() ?>
                <select id="selectTecnicoModal" class="form-select">
                    <?php foreach ($tecnicos as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= ($t['id'] == $ticket['tecnico_id'] ?? 0) ? 'selected' : '' ?>>
                        <?= e($t['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn-tx btn-sm" id="btnConfirmarAsignar">Asignar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$extraScripts = <<<JS
<script>
const TICKET_ID = $id;

// Adjunto label
document.getElementById('adjComInput')?.addEventListener('change', function(){
    document.getElementById('adjComLabel').textContent = this.files.length + ' archivo(s)';
});

// Cambiar estado
document.querySelectorAll('.btnCambiarEstado').forEach(btn => {
    btn.addEventListener('click', function(){
        const nombre = this.dataset.nombre;
        const estadoId = this.dataset.id;
        TriggerX.confirm('Cambiar estado', `¿Cambiar estado a "\${nombre}"?`, () => {
            TriggerX.post(TX.baseUrl + '/modules/tickets/ajax/acciones.php', {
                action: 'cambiar_estado', ticket_id: TICKET_ID, estado_id: estadoId
            }, () => { TriggerX.toast('Estado actualizado', 'success'); setTimeout(()=>location.reload(),1200); });
        });
    });
});

// Cambiar prioridad
document.getElementById('btnCambiarPrioridad')?.addEventListener('click', function(){
    const val = document.getElementById('selPrioridad').value;
    TriggerX.post(TX.baseUrl + '/modules/tickets/ajax/acciones.php', {
        action: 'cambiar_prioridad', ticket_id: TICKET_ID, prioridad_id: val
    }, () => { TriggerX.toast('Prioridad actualizada', 'success'); setTimeout(()=>location.reload(),1200); });
});

// Asignar técnico
document.getElementById('btnConfirmarAsignar')?.addEventListener('click', function(){
    const tecId = document.getElementById('selectTecnicoModal').value;
    TriggerX.post(TX.baseUrl + '/modules/tickets/ajax/acciones.php', {
        action: 'asignar', ticket_id: TICKET_ID, tecnico_id: tecId
    }, () => { TriggerX.toast('Técnico asignado', 'success'); setTimeout(()=>location.reload(),1200); });
    bootstrap.Modal.getInstance(document.getElementById('modalAsignar')).hide();
});

// Comentar
document.getElementById('formComentario')?.addEventListener('submit', function(e){
    e.preventDefault();
    const btn = document.getElementById('btnEnviar');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

    const fd = new FormData(this);
    fd.append('_csrf_token', TX.csrfToken);

    fetch(TX.baseUrl + '/modules/tickets/ajax/acciones.php', {
        method: 'POST',
        headers: {'X-Requested-With':'XMLHttpRequest','X-CSRF-Token':TX.csrfToken},
        body: fd,
    })
    .then(r=>r.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send-fill me-1"></i>Enviar';
        if (res.success) {
            TriggerX.toast('Respuesta enviada', 'success');
            setTimeout(()=>location.reload(), 1000);
        } else {
            TriggerX.toast(res.message || 'Error', 'error');
        }
    });
});
</script>
JS;
include CORE_PATH . '/layout/footer.php';
?>
