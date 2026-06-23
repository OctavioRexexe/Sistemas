<?php
define('TRIGGERX', true);
require_once __DIR__ . '/../../core/config/config.php';
Config::load();
securityHeaders();

$ticket   = null;
$error    = '';
$busqueda = false;

if (isPost()) {
    verifyCsrf();
    $codigo = strtoupper(trim(post('codigo')));
    $email  = strtolower(trim(post('email')));

    if (!$codigo && !$email) {
        $error = 'Ingresa el código del ticket o tu correo electrónico.';
    } else {
        try {
            $db = Database::getInstance();
            $busqueda = true;

            if ($codigo) {
                $ticket = $db->fetch(
                    "SELECT * FROM v_tickets_dashboard WHERE codigo = ?",
                    [$codigo]
                );
            } elseif ($email) {
                $ticket = $db->fetch(
                    "SELECT * FROM v_tickets_dashboard WHERE cliente_email = ? ORDER BY id DESC LIMIT 1",
                    [$email]
                );
            }

            if ($ticket) {
                // Verificar que el email coincide (si se ingresó)
                if ($email && strtolower($ticket['cliente_email']) !== $email) {
                    $ticket = null;
                    $error = 'No se encontró un ticket con esos datos.';
                }
            } else {
                $error = 'No se encontró el ticket. Verifica el código o correo.';
            }
        } catch (Throwable) {
            $error = 'Error al consultar. Intenta nuevamente.';
        }
    }
}

// Si viene código directo por GET (link de confirmación)
if (!$ticket && get_param('codigo')) {
    try {
        $db = Database::getInstance();
        $ticket = $db->fetch("SELECT * FROM v_tickets_dashboard WHERE codigo = ?", [strtoupper(get_param('codigo'))]);
    } catch (Throwable) {}
}

// Comentarios públicos del ticket
$comentarios = [];
$constanciasPublicas = [];
if ($ticket) {
    try {
        $db = Database::getInstance();
        $comentarios = $db->fetchAll(
            "SELECT * FROM comentarios_ticket WHERE ticket_id = ? AND tipo IN ('publico','solucion') ORDER BY created_at",
            [$ticket['id']]
        );
        $constanciasPublicas = $db->fetchAll(
            "SELECT ct.codigo, ct.created_at, ct.calificacion, a.ruta AS pdf_ruta
             FROM constancias_tecnicas ct
             JOIN adjuntos_ticket a ON ct.adjunto_id = a.id
             WHERE ct.ticket_id = ? AND ct.estado = 'final'
             ORDER BY ct.created_at DESC",
            [$ticket['id']]
        );
    } catch (Throwable) {}
}

$empresa_cfg   = Config::empresa();
$colorPrimario = Config::colorPrimario();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultar Ticket — <?= e($empresa_cfg) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= ASSETS_URL ?>/css/triggerx.css" rel="stylesheet">
    <style>
        :root { --tx-primary: <?= e($colorPrimario) ?>; --tx-primary-dark: color-mix(in srgb, <?= e($colorPrimario) ?> 80%, black); --tx-primary-light: color-mix(in srgb, <?= e($colorPrimario) ?> 15%, white); }
        body { padding-top: 0 !important; background: #f4f5f7; }
    </style>
</head>
<body>
<div style="background:var(--tx-primary);padding:14px 20px;text-align:center">
    <a href="<?= BASE_URL ?>/login.php" style="color:#fff;text-decoration:none;font-weight:700;font-size:17px">
        <i class="bi bi-lightning-charge-fill me-2"></i><?= e($empresa_cfg) ?>
    </a>
</div>

<div class="container py-4" style="max-width:680px">

    <!-- Formulario de búsqueda -->
    <div class="tx-public-card mb-4">
        <div class="tx-public-header">
            <i class="bi bi-search fs-2 mb-2 d-block"></i>
            <h4 class="fw-800 mb-1">Consultar Ticket</h4>
            <p class="mb-0 opacity-80" style="font-size:13px">Ingresa tu código o correo para ver el estado</p>
        </div>
        <div class="p-4">
            <?php if ($error): ?>
            <div class="alert alert-warning" style="font-size:13px">
                <i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?>
            </div>
            <?php endif; ?>
            <form method="POST" novalidate>
                <?= csrfField() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="tx-label">Código del Ticket</label>
                        <input type="text" name="codigo" class="form-control"
                               placeholder="TRG-000001" style="font-family:monospace;letter-spacing:1px"
                               value="<?= e(strtoupper(post('codigo'))) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="tx-label">— O — Correo Electrónico</label>
                        <input type="email" name="email" class="form-control" placeholder="tu@email.com"
                               value="<?= e(post('email')) ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn-tx w-100">
                            <i class="bi bi-search me-1"></i>Consultar Estado
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Resultado del ticket -->
    <?php if ($ticket): ?>
    <?php
    $diasAbierto = (int)$ticket['dias_abierto'];
    $slaColors = ['verde'=>'#198754','amarillo'=>'#ffc107','naranja'=>'#fd7e14','rojo'=>'#dc3545'];
    $slaColor  = $slaColors[$ticket['sla_estado']] ?? '#198754';
    ?>
    <div class="tx-public-card">

        <!-- Hero -->
        <div style="background:var(--tx-secondary);padding:24px;color:#fff">
            <div style="font-family:monospace;font-size:12px;background:rgba(255,255,255,.1);
                        padding:3px 10px;border-radius:6px;display:inline-block;color:var(--tx-primary);
                        font-weight:700;margin-bottom:10px"><?= e($ticket['codigo']) ?></div>
            <h5 class="fw-700 mb-2"><?= e($ticket['asunto']) ?></h5>
            <div class="d-flex flex-wrap gap-3" style="font-size:13px;color:rgba(255,255,255,.7)">
                <span><i class="bi bi-calendar3 me-1"></i><?= fdate($ticket['created_at']) ?></span>
                <span class="badge fs-6" style="background:<?= e($ticket['estado_color']) ?>">
                    <?= e($ticket['estado_nombre']) ?>
                </span>
                <span class="badge border fs-6"
                      style="color:<?= e($ticket['prioridad_color']) ?>;border-color:<?= e($ticket['prioridad_color']) ?>!important;background:transparent">
                    <?= e($ticket['prioridad_nombre']) ?>
                </span>
            </div>
        </div>

        <div class="p-4">

            <!-- Info técnico asignado -->
            <?php if ($ticket['tecnico_nombre']): ?>
            <div class="p-3 rounded mb-3" style="background:var(--tx-primary-light);border:1px solid var(--tx-primary)44">
                <div style="font-size:12px;color:var(--tx-muted)">Técnico asignado</div>
                <div class="fw-600"><?= e($ticket['tecnico_nombre']) ?></div>
            </div>
            <?php endif; ?>

            <!-- SLA visual -->
            <div class="mb-4">
                <div class="d-flex justify-content-between mb-1">
                    <span style="font-size:12px;color:var(--tx-muted)">Tiempo de atención (SLA)</span>
                    <span style="font-size:12px;font-weight:600;color:<?= $slaColor ?>">
                        <?= $diasAbierto ?> día(s)
                    </span>
                </div>
                <div class="tx-sla-bar">
                    <?php $pct = min(100, round(($diasAbierto / Config::sla()['max_dias']) * 100)); ?>
                    <div class="tx-sla-bar-fill" style="width:<?= $pct ?>%;background:<?= $slaColor ?>"></div>
                </div>
            </div>

            <!-- Timelime comentarios públicos -->
            <h6 class="fw-700 mb-3"><i class="bi bi-chat-dots me-2" style="color:var(--tx-primary)"></i>Actualizaciones</h6>

            <?php if (empty($comentarios)): ?>
            <div class="text-center text-muted py-3" style="font-size:13px">
                <i class="bi bi-clock-history fs-3 d-block mb-2 opacity-25"></i>
                Sin actualizaciones todavía. Serás notificado por correo.
            </div>
            <?php else: ?>
            <div class="tx-timeline">
                <?php foreach ($comentarios as $com): ?>
                <div class="tx-timeline-item">
                    <div class="tx-timeline-dot dot-<?= e($com['tipo']) ?>"></div>
                    <div class="tx-timeline-meta">
                        <strong><?= e($com['autor_nombre'] ?? 'Soporte Técnico') ?></strong>
                        · <?= fdatetime($com['created_at']) ?>
                        <?php if ($com['tipo'] === 'solucion'): ?>
                        <span class="badge bg-success ms-1" style="font-size:10px">✓ Solución</span>
                        <?php endif; ?>
                    </div>
                    <div class="tx-timeline-content <?= e($com['tipo']) ?>">
                        <?= nl2br(e($com['contenido'])) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($constanciasPublicas)): ?>
            <hr class="my-4">
            <h6 class="fw-700 mb-3"><i class="bi bi-file-earmark-text-fill me-2" style="color:var(--tx-primary)"></i>Constancias Técnicas</h6>
            <div class="d-flex flex-column gap-2">
                <?php foreach ($constanciasPublicas as $c): ?>
                <a href="<?= UPLOADS_URL ?>/<?= e($c['pdf_ruta']) ?>" target="_blank"
                   class="d-flex align-items-center justify-content-between p-3 rounded text-decoration-none"
                   style="background:var(--tx-primary-light);border:1px solid var(--tx-primary)44">
                    <div>
                        <div class="fw-600" style="color:#1a1a2e"><?= e($c['codigo']) ?></div>
                        <small class="text-muted"><?= fdate($c['created_at']) ?></small>
                    </div>
                    <i class="bi bi-download fs-5" style="color:var(--tx-primary)"></i>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (in_array($ticket['estado_slug'], ['resuelto','cerrado'])): ?>
            <div class="mt-3 p-3 rounded" style="background:#f0fdf4;border:1px solid #a7f3d0">
                <i class="bi bi-check-circle-fill text-success me-2"></i>
                <strong>Ticket finalizado</strong> el <?= fdatetime($ticket['fecha_resolucion']) ?>
            </div>
            <?php endif; ?>

        </div>
    </div>
    <?php endif; ?>

    <div class="text-center mt-3 d-flex gap-3 justify-content-center">
        <a href="<?= BASE_URL ?>/public/ticket/nuevo.php" class="text-muted" style="font-size:12px">
            <i class="bi bi-plus-circle me-1"></i>Abrir nuevo ticket
        </a>
        <a href="<?= BASE_URL ?>/login.php" class="text-muted" style="font-size:12px">
            <i class="bi bi-box-arrow-in-right me-1"></i>Acceso Staff
        </a>
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php if (ob_get_level()) ob_end_flush(); ?>
