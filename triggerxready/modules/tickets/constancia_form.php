<?php
/**
 * TRIGGERX - Constancia Técnica: Formulario (crear / continuar borrador / ver final)
 */
define('TRIGGERX', true);
require_once __DIR__ . '/../../core/config/config.php';
requireLogin();
requireModule('tickets');
requireRole('tecnico');
securityHeaders();

$db   = Database::getInstance();
$user = Session::getUser();

$ticketId    = (int)get_param('ticket_id');
$constanciaId = (int)get_param('id');

$ticket = null;
$constancia = null;

if ($constanciaId) {
    $constancia = $db->fetch("SELECT * FROM constancias_tecnicas WHERE id = ?", [$constanciaId]);
    if (!$constancia) redirectWith(BASE_URL . '/modules/tickets/', 'danger', 'Constancia no encontrada');
    $ticketId = (int)$constancia['ticket_id'];
}

$ticket = $db->fetch("SELECT * FROM v_tickets_dashboard WHERE id = ?", [$ticketId]);
if (!$ticket) redirectWith(BASE_URL . '/modules/tickets/', 'danger', 'Ticket no encontrado');

// Si no existe constancia todavía, se crea un borrador vacío al entrar al formulario
if (!$constancia) {
    $codigo = generarCodigoConstancia();
    $clienteTipo = ($ticket['origen'] ?? 'externo') === 'interno' ? 'interno' : 'externo';

    $newId = $db->insert('constancias_tecnicas', [
        'ticket_id'          => $ticketId,
        'tecnico_id'         => $user['id'],
        'codigo'             => $codigo,
        'cliente_nombre'     => $ticket['cliente_nombre'],
        'cliente_tipo'       => $clienteTipo,
        'ticket_codigo'      => $ticket['codigo'],
        'equipo_modelo'      => $ticket['modelo_equipo'],
        'equipo_serie'       => $ticket['serie_equipo'],
        'problema_reportado' => $ticket['descripcion'] ?? null,
        'hora_inicio'        => date('Y-m-d H:i:s'),
        'estado'             => 'borrador',
    ]);

    $db->insert('historial_ticket', [
        'ticket_id'  => $ticketId,
        'usuario_id' => $user['id'],
        'accion'     => 'constancia_creada',
        'detalle'    => "Constancia técnica {$codigo} iniciada (borrador)",
    ]);

    redirect(BASE_URL . '/modules/tickets/constancia_form.php?id=' . $newId);
}

// Permisos de edición (reutilizamos la misma lógica que el AJAX)
$esFinal = $constancia['estado'] === 'final';
$cfg     = Config::constancia();
$esAdmin = Session::hasRole('admin');
$puedeEditar = true;
$motivoBloqueo = '';

if ($esFinal) {
    $puedeEditar = false;
} elseif (!$esAdmin) {
    if ((int)$constancia['tecnico_id'] !== (int)$user['id']) {
        redirectWith(BASE_URL . '/modules/tickets/detalle.php?id=' . $ticketId, 'danger', 'No tienes permiso sobre esta constancia');
    }
    $horasTranscurridas = (time() - strtotime($constancia['created_at'])) / 3600;
    if ($horasTranscurridas > $cfg['horas_edicion']) {
        $puedeEditar = false;
        $motivoBloqueo = "El tiempo límite de edición ({$cfg['horas_edicion']}h) expiró.";
    } elseif ((int)$constancia['ediciones_borrador'] >= $cfg['max_ediciones']) {
        $puedeEditar = false;
        $motivoBloqueo = "Alcanzaste el máximo de {$cfg['max_ediciones']} ediciones permitidas.";
    }
}

// Firma guardada en el perfil del técnico (si existe)
$tecnicoPerfil = $db->fetch("SELECT firma_archivo, dni, nombre, apellido FROM usuarios WHERE id = ?", [$constancia['tecnico_id']]);

$pageTitle    = "Constancia {$constancia['codigo']} — {$ticket['codigo']}";
$activeModule = 'tickets';
$extraHead = '<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<style>
.tx-signpad { border: 2px dashed var(--tx-border); border-radius: 10px; background: #fff; cursor: crosshair; touch-action: none; width: 100%; height: 160px; }
.tx-star-rating { display:flex; gap:6px; font-size:28px; cursor:pointer; }
.tx-star-rating i { color:#e2e2e2; transition:.15s; }
.tx-star-rating i.active { color:#F4A020; }
.tx-readonly-field { background: var(--tx-bg); border-radius: 8px; padding: 8px 12px; font-size: 13.5px; }
#constanciaPdfPreview { background:#fff; padding:30px; max-width:760px; margin:0 auto; font-size:13px; color:#1a1a2e; }
#constanciaPdfPreview h2 { font-size:18px; }
#constanciaPdfPreview table { width:100%; border-collapse: collapse; margin-bottom: 14px; }
#constanciaPdfPreview table td { padding:5px 8px; border:1px solid #ddd; vertical-align:top; }
</style>';
include CORE_PATH . '/layout/header.php';
?>

<div class="container-fluid" style="max-width:980px">

    <div class="tx-page-header">
        <div>
            <h1 class="tx-page-title">
                <i class="bi bi-file-earmark-text-fill me-2" style="color:var(--tx-primary)"></i>
                Constancia Técnica <span class="text-muted" style="font-size:16px">— <?= e($constancia['codigo']) ?></span>
            </h1>
            <div class="tx-breadcrumb">
                <a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a> ›
                <a href="<?= BASE_URL ?>/modules/tickets/">Tickets</a> ›
                <a href="<?= BASE_URL ?>/modules/tickets/detalle.php?id=<?= $ticketId ?>"><?= e($ticket['codigo']) ?></a> ›
                Constancia
            </div>
        </div>
        <span class="badge fs-6" style="background:<?= $esFinal ? '#198754' : '#6c757d' ?>">
            <?= $esFinal ? 'FINAL' : 'BORRADOR' ?>
        </span>
    </div>

    <?= getFlashAlert() ?>

    <?php if (!$puedeEditar && !$esFinal): ?>
    <div class="alert alert-warning">
        <i class="bi bi-lock-fill me-2"></i><?= e($motivoBloqueo) ?>
        Contacta a un administrador para continuar editando este borrador.
    </div>
    <?php endif; ?>

    <?php if ($esFinal): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle-fill me-2"></i>Esta constancia fue finalizada el <?= fdatetime($constancia['finalizado_at']) ?> y ya está adjunta al ticket.
        <?php if ($constancia['adjunto_id']):
            $adj = $db->fetch("SELECT * FROM adjuntos_ticket WHERE id = ?", [$constancia['adjunto_id']]); ?>
        <a href="<?= UPLOADS_URL ?>/<?= e($adj['ruta'] ?? '') ?>" target="_blank" class="ms-2 fw-600">
            <i class="bi bi-download me-1"></i>Descargar PDF
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <form id="formConstancia" autocomplete="off">
        <input type="hidden" id="constanciaId" value="<?= $constancia['id'] ?>">
        <?= csrfField() ?>

        <!-- DATOS DEL CASO (read-only, jalados del ticket) -->
        <div class="tx-card mb-4">
            <div class="tx-card-header"><h6 class="tx-card-title"><i class="bi bi-clipboard-data"></i>Datos del Caso</h6></div>
            <div class="tx-card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="tx-label">Cliente</label>
                        <div class="tx-readonly-field"><?= e($constancia['cliente_nombre']) ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="tx-label">Tipo de Cliente</label>
                        <div class="tx-readonly-field"><?= $constancia['cliente_tipo'] === 'interno' ? 'Interno' : 'Externo' ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="tx-label">N° Ticket</label>
                        <div class="tx-readonly-field font-monospace"><?= e($constancia['ticket_codigo']) ?></div>
                    </div>
                    <div class="col-md-12">
                        <label class="tx-label">Dirección <?= $constancia['cliente_tipo'] === 'externo' ? '' : '(sede interna)' ?></label>
                        <input type="text" name="cliente_direccion" class="form-control"
                               value="<?= e($constancia['cliente_direccion'] ?? '') ?>"
                               placeholder="Dirección de atención" <?= $puedeEditar ? '' : 'disabled' ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="tx-label">Marca</label>
                        <input type="text" name="equipo_marca" class="form-control"
                               value="<?= e($constancia['equipo_marca'] ?? '') ?>" <?= $puedeEditar ? '' : 'disabled' ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="tx-label">Modelo</label>
                        <div class="tx-readonly-field"><?= e($constancia['equipo_modelo'] ?: '—') ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="tx-label">Serie</label>
                        <div class="tx-readonly-field"><?= e($constancia['equipo_serie'] ?: '—') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- DATOS DEL SERVICIO -->
        <div class="tx-card mb-4">
            <div class="tx-card-header"><h6 class="tx-card-title"><i class="bi bi-tools"></i>Datos del Servicio</h6></div>
            <div class="tx-card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="tx-label">Tipo de Servicio</label>
                        <select name="tipo_servicio" class="form-select" <?= $puedeEditar ? '' : 'disabled' ?>>
                            <?php $tipos = ['garantia'=>'Garantía','mantenimiento'=>'Mantenimiento','revision_diagnostico'=>'Revisión y Diagnóstico']; ?>
                            <?php foreach ($tipos as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $constancia['tipo_servicio'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="tx-label">Hora de Inicio</label>
                        <input type="time" name="hora_inicio_hora" id="horaInicioHora" class="form-control"
                               value="<?= $constancia['hora_inicio'] ? date('H:i', strtotime($constancia['hora_inicio'])) : date('H:i') ?>"
                               <?= $puedeEditar ? '' : 'disabled' ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="tx-label">Hora de Fin</label>
                        <div class="tx-readonly-field">
                            <?= $constancia['hora_fin'] ? fdatetime($constancia['hora_fin']) : 'Se registra automáticamente al finalizar' ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="tx-label">Problema Reportado</label>
                        <textarea name="problema_reportado" class="form-control" rows="3" <?= $puedeEditar ? '' : 'disabled' ?>><?= e($constancia['problema_reportado'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- REVISIÓN EXTERNA -->
        <div class="tx-card mb-4">
            <div class="tx-card-header"><h6 class="tx-card-title"><i class="bi bi-list-check"></i>Revisión Externa</h6></div>
            <div class="tx-card-body p-0">
                <div class="table-responsive">
                    <table class="tx-table table mb-0">
                        <thead><tr><th style="width:40%">Pregunta</th><th style="width:120px">Respuesta</th><th>Observaciones</th></tr></thead>
                        <tbody>
                        <?php
                        $preguntas = [
                            'rev_danos_fisicos'       => ['¿Daños físicos detectados?', 'rev_danos_fisicos_obs'],
                            'rev_tornillos_completos' => ['¿Tornillos, tapas, accesorios completos?', 'rev_tornillos_obs'],
                            'rev_ensamblado_correcto' => ['¿Se encuentra correctamente ensamblado?', 'rev_ensamblado_obs'],
                            'rev_signos_desgaste'     => ['¿Presenta signos de desgaste?', 'rev_desgaste_obs'],
                            'rev_signos_golpes'       => ['¿Presenta signos de golpes?', 'rev_golpes_obs'],
                            'rev_equipo_limpio'       => ['¿El equipo se encuentra limpio?', 'rev_limpio_obs'],
                        ];
                        foreach ($preguntas as $campo => [$texto, $obsCampo]):
                        ?>
                        <tr>
                            <td><?= $texto ?></td>
                            <td>
                                <select name="<?= $campo ?>" class="form-select form-select-sm" <?= $puedeEditar ? '' : 'disabled' ?>>
                                    <option value="">—</option>
                                    <option value="si" <?= $constancia[$campo] === 'si' ? 'selected' : '' ?>>Sí</option>
                                    <option value="no" <?= $constancia[$campo] === 'no' ? 'selected' : '' ?>>No</option>
                                </select>
                            </td>
                            <td>
                                <input type="text" name="<?= $obsCampo ?>" class="form-control form-control-sm"
                                       value="<?= e($constancia[$obsCampo] ?? '') ?>" <?= $puedeEditar ? '' : 'disabled' ?>>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TRABAJO REALIZADO -->
        <div class="tx-card mb-4">
            <div class="tx-card-header">
                <h6 class="tx-card-title"><i class="bi bi-wrench-adjustable"></i>Trabajo Realizado</h6>
            </div>
            <div class="tx-card-body">
                <textarea name="trabajo_realizado" id="trabajoRealizado" class="form-control" rows="5"
                          placeholder="Describe el trabajo técnico realizado..."
                          <?= $puedeEditar ? '' : 'disabled' ?>><?= e($constancia['trabajo_realizado'] ?? '') ?></textarea>
                <button type="button" class="btn btn-sm mt-2" id="btnMejorarIA"
                        style="background:var(--tx-bg);border:1px solid var(--tx-border)" <?= $puedeEditar ? '' : 'disabled' ?>>
                    <i class="bi bi-stars me-1" style="color:var(--tx-primary)"></i>Mejorar con IA
                </button>
            </div>
        </div>

        <!-- CONFORMIDAD DEL CLIENTE -->
        <div class="tx-card mb-4">
            <div class="tx-card-header"><h6 class="tx-card-title"><i class="bi bi-patch-check-fill"></i>Conformidad del Cliente</h6></div>
            <div class="tx-card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="tx-label">Calificación del Servicio</label>
                        <div class="tx-star-rating" id="starRating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="bi bi-star-fill" data-star="<?= $i ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="calificacion" id="calificacionInput" value="<?= e($constancia['calificacion'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="tx-label">DNI del Cliente</label>
                        <input type="text" name="cliente_conf_dni" class="form-control" maxlength="15"
                               value="<?= e($constancia['cliente_conf_dni'] ?? '') ?>" <?= $puedeEditar ? '' : 'disabled' ?>>
                    </div>
                    <div class="col-md-6">
                        <label class="tx-label">Nombre y Apellido del Cliente</label>
                        <input type="text" name="cliente_conf_nombre" class="form-control"
                               value="<?= e($constancia['cliente_conf_nombre'] ?? $constancia['cliente_nombre']) ?>" <?= $puedeEditar ? '' : 'disabled' ?>>
                    </div>
                    <div class="col-12">
                        <label class="tx-label">Firma del Cliente</label>
                        <?php if ($constancia['cliente_firma_ruta']): ?>
                        <div class="mb-2">
                            <img src="<?= UPLOADS_URL ?>/<?= e($constancia['cliente_firma_ruta']) ?>" style="max-height:100px;border:1px solid var(--tx-border);border-radius:8px;background:#fff" id="firmaClienteImg">
                        </div>
                        <?php endif; ?>
                        <?php if ($puedeEditar): ?>
                        <div class="d-flex gap-2 mb-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-tx-outline" data-firma-modal="cliente">
                                <i class="bi bi-pen me-1"></i>Firmar / Cambiar firma
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- DATOS DEL TÉCNICO -->
        <div class="tx-card mb-4">
            <div class="tx-card-header"><h6 class="tx-card-title"><i class="bi bi-person-badge-fill"></i>Datos del Técnico</h6></div>
            <div class="tx-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="tx-label">Técnico</label>
                        <div class="tx-readonly-field"><?= e($tecnicoPerfil['nombre'] . ' ' . $tecnicoPerfil['apellido']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="tx-label">DNI</label>
                        <div class="tx-readonly-field"><?= e($tecnicoPerfil['dni'] ?: 'No registrado en el perfil') ?></div>
                    </div>
                    <div class="col-12">
                        <label class="tx-label">Firma del Técnico</label>
                        <?php $firmaTec = $constancia['tecnico_firma_ruta'] ?: $tecnicoPerfil['firma_archivo']; ?>
                        <?php if ($firmaTec): ?>
                        <div class="mb-2">
                            <img src="<?= UPLOADS_URL ?>/<?= e($firmaTec) ?>" style="max-height:100px;border:1px solid var(--tx-border);border-radius:8px;background:#fff" id="firmaTecnicoImg">
                        </div>
                        <?php endif; ?>
                        <?php if ($puedeEditar): ?>
                        <div class="d-flex gap-2 mb-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-tx-outline" data-firma-modal="tecnico">
                                <i class="bi bi-pen me-1"></i><?= $firmaTec ? 'Cambiar firma' : 'Firmar' ?>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($puedeEditar): ?>
        <div class="d-flex justify-content-between mb-5 flex-wrap gap-2">
            <a href="<?= BASE_URL ?>/modules/tickets/detalle.php?id=<?= $ticketId ?>" class="btn" style="background:var(--tx-bg);border:1px solid var(--tx-border)">
                <i class="bi bi-arrow-left me-1"></i>Volver al Ticket
            </a>
            <div class="d-flex gap-2">
                <button type="button" class="btn-tx-outline" id="btnGuardarBorrador">
                    <i class="bi bi-save me-1"></i>Guardar Borrador
                </button>
                <button type="button" class="btn-tx" id="btnFinalizar">
                    <i class="bi bi-check-circle-fill me-1"></i>Finalizar Constancia
                </button>
            </div>
        </div>
        <?php endif; ?>
    </form>
</div>

<!-- MODAL: FIRMA (canvas) -->
<div class="modal fade" id="modalFirma" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--tx-primary);color:#fff">
                <h6 class="modal-title"><i class="bi bi-pen me-2"></i>Firmar</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-pills mb-3" id="firmaTabs">
                    <li class="nav-item"><a class="nav-link active" data-bs-toggle="pill" href="#firmaPadTab">Dibujar</a></li>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="pill" href="#firmaUploadTab">Subir imagen</a></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="firmaPadTab">
                        <canvas id="firmaCanvas" class="tx-signpad"></canvas>
                        <button type="button" class="btn btn-sm mt-2" id="btnLimpiarFirma" style="background:var(--tx-bg);border:1px solid var(--tx-border)">
                            <i class="bi bi-eraser me-1"></i>Limpiar
                        </button>
                    </div>
                    <div class="tab-pane fade" id="firmaUploadTab">
                        <input type="file" id="firmaUploadInput" class="form-control" accept=".png,.jpg,.jpeg">
                        <div class="mt-2 text-center">
                            <img id="firmaUploadPreview" style="max-height:120px;display:none">
                        </div>
                    </div>
                </div>
                <div class="form-check mt-3" id="checkGuardarPerfilWrap" style="display:none">
                    <input class="form-check-input" type="checkbox" id="checkGuardarPerfil">
                    <label class="form-check-label" style="font-size:13px" for="checkGuardarPerfil">
                        Guardar esta firma en mi perfil para reutilizarla
                    </label>
                </div>
                <input type="hidden" id="firmaModalTipo">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn-tx btn-sm" id="btnGuardarFirma">Guardar Firma</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: CONFIRMACIÓN DE DATOS ANTES DE FINALIZAR -->
<div class="modal fade" id="modalConfirmar" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--tx-primary);color:#fff">
                <h6 class="modal-title"><i class="bi bi-clipboard-check me-2"></i>Confirma los datos antes de finalizar</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="background:#f4f5f7">
                <div id="constanciaPdfPreview"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Seguir editando</button>
                <button type="button" class="btn-tx btn-sm" id="btnConfirmarFinal">
                    <i class="bi bi-check-circle-fill me-1"></i>Confirmar y Generar PDF
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: ENVÍO DE CORREO -->
<div class="modal fade" id="modalEmail" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:#198754;color:#fff">
                <h6 class="modal-title"><i class="bi bi-check-circle-fill me-2"></i>Constancia finalizada</h6>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-envelope-paper-fill fs-1 mb-3 d-block" style="color:var(--tx-primary)"></i>
                <p class="mb-0">¿Deseas enviar esta constancia por correo al cliente?</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary btn-sm" id="btnOmitirEmail">No enviar</button>
                <button type="button" class="btn-tx btn-sm" id="btnEnviarEmail">
                    <i class="bi bi-send-fill me-1"></i>Sí, enviar por correo
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$constanciaJs = [
    'id'        => (int)$constancia['id'],
    'ticketId'  => $ticketId,
    'codigo'    => $constancia['codigo'],
    'tkCodigo'  => $constancia['ticket_codigo'],
    'cliente'   => $constancia['cliente_nombre'],
    'clienteTipo' => $constancia['cliente_tipo'],
    'marca'     => $constancia['equipo_marca'],
    'modelo'    => $constancia['equipo_modelo'],
    'serie'     => $constancia['equipo_serie'],
    'tecnicoNombre' => $tecnicoPerfil['nombre'] . ' ' . $tecnicoPerfil['apellido'],
    'tecnicoDni'    => $tecnicoPerfil['dni'],
    'puedeEditar'   => $puedeEditar,
];
$constanciaJsJson = json_encode($constanciaJs, JSON_UNESCAPED_UNICODE);

$extraScripts = <<<JS
<script>
const CONST_DATA = {$constanciaJsJson};
const AJAX_URL = TX.baseUrl + '/modules/tickets/ajax/constancias.php';

// ============================================================
// CALIFICACIÓN POR ESTRELLAS
// ============================================================
const starEls = document.querySelectorAll('#starRating i');
const califInput = document.getElementById('calificacionInput');

function pintarEstrellas(valor) {
    starEls.forEach(el => {
        el.classList.toggle('active', parseInt(el.dataset.star) <= valor);
    });
}
pintarEstrellas(parseInt(califInput.value || 0));

starEls.forEach(el => {
    el.addEventListener('click', function () {
        if (!CONST_DATA.puedeEditar) return;
        califInput.value = this.dataset.star;
        pintarEstrellas(parseInt(this.dataset.star));
    });
});

// ============================================================
// BOTÓN MEJORAR CON IA (placeholder, sin conectar todavía)
// ============================================================
document.getElementById('btnMejorarIA')?.addEventListener('click', function () {
    TriggerX.toast('Función de mejora con IA próximamente disponible', 'info');
});

// ============================================================
// RECOLECTAR DATOS DEL FORMULARIO
// ============================================================
function recolectarFormData(extra = {}) {
    const form = document.getElementById('formConstancia');
    const data = { id: CONST_DATA.id, action: 'guardar' };

    new FormData(form).forEach((value, key) => {
        if (key === '_csrf_token') return;
        data[key] = value;
    });

    // Combinar hora de inicio (solo hora) con la fecha de creación del registro
    const horaInicio = document.getElementById('horaInicioHora')?.value;
    if (horaInicio) {
        const hoy = new Date().toISOString().split('T')[0];
        data['hora_inicio'] = hoy + ' ' + horaInicio + ':00';
    }

    return { ...data, ...extra };
}

// ============================================================
// GUARDAR BORRADOR
// ============================================================
document.getElementById('btnGuardarBorrador')?.addEventListener('click', function () {
    const data = recolectarFormData({ contar_edicion: '1' });
    TriggerX.post(AJAX_URL, data, () => {
        TriggerX.toast('Borrador guardado', 'success');
    });
});

// ============================================================
// CANVAS DE FIRMA
// ============================================================
let firmaCanvas, firmaCtx, dibujando = false;

function initCanvas() {
    firmaCanvas = document.getElementById('firmaCanvas');
    if (!firmaCanvas) return;
    firmaCanvas.width = firmaCanvas.offsetWidth;
    firmaCanvas.height = firmaCanvas.offsetHeight;
    firmaCtx = firmaCanvas.getContext('2d');
    firmaCtx.strokeStyle = '#1a1a2e';
    firmaCtx.lineWidth = 2.2;
    firmaCtx.lineCap = 'round';

    const pos = (e) => {
        const rect = firmaCanvas.getBoundingClientRect();
        const t = e.touches ? e.touches[0] : e;
        return { x: t.clientX - rect.left, y: t.clientY - rect.top };
    };

    const start = (e) => { dibujando = true; const p = pos(e); firmaCtx.beginPath(); firmaCtx.moveTo(p.x, p.y); };
    const move = (e) => { if (!dibujando) return; const p = pos(e); firmaCtx.lineTo(p.x, p.y); firmaCtx.stroke(); e.preventDefault(); };
    const end = () => { dibujando = false; };

    firmaCanvas.addEventListener('mousedown', start);
    firmaCanvas.addEventListener('mousemove', move);
    firmaCanvas.addEventListener('mouseup', end);
    firmaCanvas.addEventListener('touchstart', start);
    firmaCanvas.addEventListener('touchmove', move);
    firmaCanvas.addEventListener('touchend', end);
}

document.getElementById('modalFirma')?.addEventListener('shown.bs.modal', initCanvas);

document.getElementById('btnLimpiarFirma')?.addEventListener('click', () => {
    firmaCtx?.clearRect(0, 0, firmaCanvas.width, firmaCanvas.height);
});

let firmaUploadDataUrl = null;
document.getElementById('firmaUploadInput')?.addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (e) => {
        firmaUploadDataUrl = e.target.result;
        const img = document.getElementById('firmaUploadPreview');
        img.src = firmaUploadDataUrl;
        img.style.display = 'block';
    };
    reader.readAsDataURL(file);
});

document.querySelectorAll('[data-firma-modal]').forEach(btn => {
    btn.addEventListener('click', function () {
        const tipo = this.dataset.firmaModal;
        document.getElementById('firmaModalTipo').value = tipo;
        document.getElementById('checkGuardarPerfilWrap').style.display = tipo === 'tecnico' ? 'block' : 'none';
        firmaUploadDataUrl = null;
        new bootstrap.Modal(document.getElementById('modalFirma')).show();
    });
});

document.getElementById('btnGuardarFirma')?.addEventListener('click', function () {
    const tipo = document.getElementById('firmaModalTipo').value;
    const activeTab = document.querySelector('#firmaTabs .nav-link.active').getAttribute('href');

    let dataUrl;
    if (activeTab === '#firmaUploadTab') {
        if (!firmaUploadDataUrl) { TriggerX.toast('Selecciona una imagen', 'warning'); return; }
        dataUrl = firmaUploadDataUrl;
    } else {
        const blank = document.createElement('canvas');
        blank.width = firmaCanvas.width; blank.height = firmaCanvas.height;
        if (firmaCanvas.toDataURL() === blank.toDataURL()) {
            TriggerX.toast('Dibuja una firma primero', 'warning'); return;
        }
        dataUrl = firmaCanvas.toDataURL('image/png');
    }

    const guardarPerfil = document.getElementById('checkGuardarPerfil')?.checked ? '1' : '0';

    TriggerX.post(AJAX_URL, {
        action: 'subir_firma', id: CONST_DATA.id, tipo: tipo,
        imagen_base64: dataUrl, guardar_perfil: guardarPerfil,
    }, (res) => {
        const imgId = tipo === 'cliente' ? 'firmaClienteImg' : 'firmaTecnicoImg';
        let img = document.getElementById(imgId);
        if (!img) {
            img = document.createElement('img');
            img.id = imgId;
            img.style.cssText = 'max-height:100px;border:1px solid var(--tx-border);border-radius:8px;background:#fff';
            document.querySelector(`[data-firma-modal="\${tipo}"]`).insertAdjacentElement('beforebegin', img);
        }
        img.src = res.data.ruta;
        TriggerX.toast('Firma guardada', 'success');
        bootstrap.Modal.getInstance(document.getElementById('modalFirma')).hide();
    });
});

// ============================================================
// CONSTRUIR PREVIEW / DOCUMENTO PARA EL PDF
// ============================================================
function construirHtmlConstancia() {
    const f = recolectarFormData();
    const calif = parseInt(f.calificacion || 0);
    const estrellas = '★'.repeat(calif) + '☆'.repeat(5 - calif);

    const firmaCliente = document.getElementById('firmaClienteImg')?.src || '';
    const firmaTecnico  = document.getElementById('firmaTecnicoImg')?.src || '';

    const tiposServicio = { garantia: 'Garantía', mantenimiento: 'Mantenimiento', revision_diagnostico: 'Revisión y Diagnóstico' };
    const preguntas = [
        ['rev_danos_fisicos', '¿Daños físicos detectados?', 'rev_danos_fisicos_obs'],
        ['rev_tornillos_completos', '¿Tornillos, tapas, accesorios completos?', 'rev_tornillos_obs'],
        ['rev_ensamblado_correcto', '¿Se encuentra correctamente ensamblado?', 'rev_ensamblado_obs'],
        ['rev_signos_desgaste', '¿Presenta signos de desgaste?', 'rev_desgaste_obs'],
        ['rev_signos_golpes', '¿Presenta signos de golpes?', 'rev_golpes_obs'],
        ['rev_equipo_limpio', '¿El equipo se encuentra limpio?', 'rev_limpio_obs'],
    ];

    let filasRevision = '';
    preguntas.forEach(([campo, texto, obsCampo]) => {
        const resp = (f[campo] || '—').toUpperCase();
        const obs = f[obsCampo] || '';
        filasRevision += `<tr><td>\${texto}</td><td style="text-align:center">\${resp}</td><td>\${obs}</td></tr>`;
    });

    return `
        <h2 style="text-align:center;margin-bottom:4px">CONSTANCIA TÉCNICA</h2>
        <p style="text-align:center;color:#666;margin-bottom:18px">\${CONST_DATA.codigo} — Ticket \${CONST_DATA.tkCodigo}</p>
        <table>
            <tr><td><strong>Cliente</strong></td><td>\${CONST_DATA.cliente}</td><td><strong>Tipo</strong></td><td>\${CONST_DATA.clienteTipo === 'interno' ? 'Interno' : 'Externo'}</td></tr>
            <tr><td><strong>Dirección</strong></td><td colspan="3">\${f.cliente_direccion || '—'}</td></tr>
            <tr><td><strong>Marca</strong></td><td>\${f.equipo_marca || CONST_DATA.marca || '—'}</td><td><strong>Modelo</strong></td><td>\${CONST_DATA.modelo || '—'}</td></tr>
            <tr><td><strong>Serie</strong></td><td>\${CONST_DATA.serie || '—'}</td><td><strong>Tipo Servicio</strong></td><td>\${tiposServicio[f.tipo_servicio] || '—'}</td></tr>
            <tr><td><strong>Hora Inicio</strong></td><td>\${document.getElementById('horaInicioHora').value}</td><td><strong>Hora Fin</strong></td><td>\${new Date().toLocaleTimeString('es-PE')}</td></tr>
        </table>

        <p><strong>Problema Reportado:</strong></p>
        <p style="white-space:pre-wrap">\${f.problema_reportado || '—'}</p>

        <p><strong>Revisión Externa:</strong></p>
        <table>
            <tr><td><strong>Pregunta</strong></td><td style="text-align:center"><strong>Resp.</strong></td><td><strong>Observación</strong></td></tr>
            \${filasRevision}
        </table>

        <p><strong>Trabajo Realizado:</strong></p>
        <p style="white-space:pre-wrap">\${f.trabajo_realizado || '—'}</p>

        <p><strong>Conformidad del Cliente:</strong> <span style="color:#F4A020;font-size:16px">\${estrellas}</span></p>
        <table>
            <tr><td><strong>DNI Cliente</strong></td><td>\${f.cliente_conf_dni || '—'}</td><td><strong>Nombre</strong></td><td>\${f.cliente_conf_nombre || '—'}</td></tr>
        </table>

        <div style="display:flex;justify-content:space-between;margin-top:20px;gap:20px">
            <div style="text-align:center;flex:1">
                \${firmaCliente ? `<img src="\${firmaCliente}" style="max-height:70px">` : '<div style="height:70px"></div>'}
                <hr style="margin:4px 0">
                <small>Firma Cliente<br>\${f.cliente_conf_nombre || ''}</small>
            </div>
            <div style="text-align:center;flex:1">
                \${firmaTecnico ? `<img src="\${firmaTecnico}" style="max-height:70px">` : '<div style="height:70px"></div>'}
                <hr style="margin:4px 0">
                <small>Firma Técnico<br>\${CONST_DATA.tecnicoNombre}\${CONST_DATA.tecnicoDni ? ' — DNI ' + CONST_DATA.tecnicoDni : ''}</small>
            </div>
        </div>
    `;
}

// ============================================================
// ABRIR MODAL DE CONFIRMACIÓN
// ============================================================
document.getElementById('btnFinalizar')?.addEventListener('click', function () {
    const f = recolectarFormData();
    if (!f.trabajo_realizado || !f.trabajo_realizado.trim()) {
        TriggerX.toast('Completa el trabajo realizado antes de finalizar', 'warning'); return;
    }
    if (!document.getElementById('firmaClienteImg')) {
        TriggerX.toast('Falta la firma del cliente', 'warning'); return;
    }
    if (!document.getElementById('firmaTecnicoImg')) {
        TriggerX.toast('Falta la firma del técnico', 'warning'); return;
    }
    document.getElementById('constanciaPdfPreview').innerHTML = construirHtmlConstancia();
    new bootstrap.Modal(document.getElementById('modalConfirmar')).show();
});

// ============================================================
// CONFIRMAR -> GENERAR PDF -> FINALIZAR
// ============================================================
document.getElementById('btnConfirmarFinal')?.addEventListener('click', async function () {
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generando PDF...';

    try {
        // Guardar el borrador final antes de generar el PDF
        const dataGuardar = recolectarFormData({ contar_edicion: '1' });
        await new Promise((resolve, reject) => {
            TriggerX.post(AJAX_URL, dataGuardar, resolve, reject);
        });

        const elemento = document.getElementById('constanciaPdfPreview');
        const canvas = await html2canvas(elemento, { scale: 2 });
        const imgData = canvas.toDataURL('image/png');

        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF('p', 'mm', 'a4');
        const pageWidth = pdf.internal.pageSize.getWidth();
        const imgHeight = (canvas.height * pageWidth) / canvas.width;
        pdf.addImage(imgData, 'PNG', 0, 0, pageWidth, imgHeight);

        const pdfBase64 = pdf.output('datauristring');

        TriggerX.post(AJAX_URL, {
            action: 'finalizar', id: CONST_DATA.id, pdf_base64: pdfBase64,
        }, () => {
            bootstrap.Modal.getInstance(document.getElementById('modalConfirmar')).hide();
            TriggerX.toast('Constancia finalizada y adjuntada al ticket', 'success');
            new bootstrap.Modal(document.getElementById('modalEmail')).show();
        }, () => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>Confirmar y Generar PDF';
        });

    } catch (err) {
        TriggerX.toast('Error al generar el PDF', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>Confirmar y Generar PDF';
    }
});

// ============================================================
// MODAL DE ENVÍO DE CORREO
// ============================================================
document.getElementById('btnEnviarEmail')?.addEventListener('click', function () {
    TriggerX.post(AJAX_URL, { action: 'enviar_email', id: CONST_DATA.id }, () => {
        TriggerX.toast('Constancia enviada por correo', 'success');
        setTimeout(() => window.location.href = TX.baseUrl + '/modules/tickets/detalle.php?id=' + CONST_DATA.ticketId, 1200);
    });
});

document.getElementById('btnOmitirEmail')?.addEventListener('click', function () {
    TriggerX.post(AJAX_URL, { action: 'omitir_email', id: CONST_DATA.id }, () => {
        window.location.href = TX.baseUrl + '/modules/tickets/detalle.php?id=' + CONST_DATA.ticketId;
    });
});
</script>
JS;
include CORE_PATH . '/layout/footer.php';
?>
