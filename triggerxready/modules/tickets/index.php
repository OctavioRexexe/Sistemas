<?php
/**
 * TRIGGERX - Módulo Tickets: Listado
 */
define('TRIGGERX', true);
require_once __DIR__ . '/../../core/config/config.php';
requireLogin();
requireModule('tickets');
securityHeaders();

$user = Session::getUser();
$db   = Database::getInstance();

// Filtros
$filtroEstado   = get_param('estado', '');
$filtroPrioridad = get_param('prioridad', '');
$filtroSLA      = get_param('sla', '');
$filtroTecnico  = get_param('tecnico', '');

// Listas para filtros
$estados     = $db->fetchAll("SELECT * FROM estados_ticket ORDER BY orden");
$prioridades = $db->fetchAll("SELECT * FROM prioridades_ticket ORDER BY nivel");
$tecnicos    = $db->fetchAll(
    "SELECT u.id, CONCAT(u.nombre,' ',u.apellido) AS nombre FROM usuarios u 
     JOIN roles r ON u.rol_id = r.id WHERE r.nivel >= 2 AND u.activo = 1 ORDER BY u.nombre"
);

$pageTitle    = 'Mesa de Ayuda — Tickets';
$activeModule = 'tickets';
include CORE_PATH . '/layout/header.php';
?>

<div class="container-fluid">

    <!-- Page Header -->
    <div class="tx-page-header">
        <div>
            <h1 class="tx-page-title">
                <i class="bi bi-headset me-2" style="color:var(--tx-primary)"></i>
                Mesa de Ayuda
            </h1>
            <div class="tx-breadcrumb">
                <a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a>
                <span class="mx-1">›</span> Tickets
            </div>
        </div>
        <?php if (Session::hasRole('tecnico')): ?>
        <div class="d-flex gap-2">
            <button class="btn-tx" data-bs-toggle="modal" data-bs-target="#modalNuevoTicket">
                <i class="bi bi-plus-circle-fill"></i> Nuevo Ticket
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Flash -->
    <?= getFlashAlert() ?>

    <!-- ============================================================
         FILTROS
         ============================================================ -->
    <div class="tx-card mb-4">
        <div class="tx-card-body py-3">
            <form method="GET" id="formFiltros" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="tx-label mb-1">Estado</label>
                    <select name="estado" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($estados as $e): ?>
                        <option value="<?= e($e['slug']) ?>" <?= $filtroEstado === $e['slug'] ? 'selected' : '' ?>>
                            <?= e($e['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="tx-label mb-1">Prioridad</label>
                    <select name="prioridad" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <?php foreach ($prioridades as $p): ?>
                        <option value="<?= e($p['slug']) ?>" <?= $filtroPrioridad === $p['slug'] ? 'selected' : '' ?>>
                            <?= e($p['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="tx-label mb-1">SLA</label>
                    <select name="sla" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="verde"    <?= $filtroSLA === 'verde' ? 'selected' : '' ?>>Verde (Ok)</option>
                        <option value="amarillo" <?= $filtroSLA === 'amarillo' ? 'selected' : '' ?>>Amarillo (Atención)</option>
                        <option value="naranja"  <?= $filtroSLA === 'naranja' ? 'selected' : '' ?>>Naranja (Urgente)</option>
                        <option value="rojo"     <?= $filtroSLA === 'rojo' ? 'selected' : '' ?>>Rojo (Vencido)</option>
                    </select>
                </div>
                <?php if (Session::hasRole('admin')): ?>
                <div class="col-md-3">
                    <label class="tx-label mb-1">Técnico</label>
                    <select name="tecnico" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($tecnicos as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $filtroTecnico == $t['id'] ? 'selected' : '' ?>>
                            <?= e($t['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-2">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn-tx btn-sm" style="font-size:12px">
                            <i class="bi bi-funnel-fill"></i> Filtrar
                        </button>
                        <a href="<?= BASE_URL ?>/modules/tickets/" class="btn btn-sm" 
                           style="background:var(--tx-bg);border:1px solid var(--tx-border);font-size:12px">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================
         TABLA TICKETS
         ============================================================ -->
    <div class="tx-card">
        <div class="tx-card-header">
            <h6 class="tx-card-title">
                <i class="bi bi-list-ul"></i>
                Listado de Tickets
            </h6>
            <div class="d-flex gap-2 align-items-center">
                <span id="totalTickets" class="badge bg-secondary"></span>
                <?php if (Session::hasRole('admin')): ?>
                <a href="<?= BASE_URL ?>/modules/tickets/ajax/export.php" class="btn btn-sm" 
                   style="background:var(--tx-bg);border:1px solid var(--tx-border);font-size:12px">
                    <i class="bi bi-download me-1"></i>Exportar
                </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="table-responsive">
            <table id="tablaTickets" class="tx-table table mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Cliente</th>
                        <th>Asunto</th>
                        <th>Categoría</th>
                        <th>Estado</th>
                        <th>Prioridad</th>
                        <th>SLA</th>
                        <th>Técnico</th>
                        <th>Creado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbodyTickets"></tbody>
            </table>
        </div>
    </div>

</div>

<!-- ============================================================
     MODAL: NUEVO TICKET INTERNO
     ============================================================ -->
<?php if (Session::hasRole('tecnico')): ?>
<div class="modal fade" id="modalNuevoTicket" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--tx-primary);color:#fff">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Nuevo Ticket Interno
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNuevoTicket" enctype="multipart/form-data">
                <div class="modal-body">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="crear_interno">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="tx-label">Nombre Cliente <span class="req">*</span></label>
                            <input type="text" name="cliente_nombre" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="tx-label">Empresa</label>
                            <input type="text" name="cliente_empresa" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="tx-label">Correo <span class="req">*</span></label>
                            <input type="email" name="cliente_email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="tx-label">Teléfono</label>
                            <input type="tel" name="cliente_telefono" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="tx-label">Asunto <span class="req">*</span></label>
                            <input type="text" name="asunto" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="tx-label">Prioridad</label>
                            <select name="prioridad_id" class="form-select">
                                <?php foreach ($prioridades as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $p['slug'] === 'media' ? 'selected' : '' ?>>
                                    <?= e($p['nombre']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="tx-label">Categoría</label>
                            <select name="categoria_id" class="form-select">
                                <option value="">Sin categoría</option>
                                <?php
                                $cats = $db->fetchAll("SELECT * FROM categorias_ticket WHERE activo = 1");
                                foreach ($cats as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= e($c['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="tx-label">Asignar a</label>
                            <select name="tecnico_id" class="form-select">
                                <option value="">Sin asignar</option>
                                <?php foreach ($tecnicos as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= e($t['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="tx-label">Serie del Equipo</label>
                            <input type="text" name="serie_equipo" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="tx-label">Descripción <span class="req">*</span></label>
                            <textarea name="descripcion" class="form-control" rows="4" required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="tx-label">Adjuntos (JPG, PNG, PDF — máx 10MB c/u)</label>
                            <div class="tx-upload-zone">
                                <i class="bi bi-cloud-upload"></i>
                                <p class="mb-0 tx-upload-label text-muted" style="font-size:13px">
                                    Arrastra archivos aquí o haz clic para seleccionar
                                </p>
                                <input type="file" name="adjuntos[]" multiple accept=".jpg,.jpeg,.png,.pdf"
                                       style="display:none">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-tx" id="btnCrearTicket">
                        <i class="bi bi-save me-1"></i>Crear Ticket
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Pre-renderizar select de técnicos para JS
$selectTecnicosHtml = '<select id="swalTecnico" class="swal2-input" style="width:auto">';
foreach ($tecnicos as $t) {
    $selectTecnicosHtml .= '<option value="' . (int)$t['id'] . '">' . e($t['nombre']) . '</option>';
}
$selectTecnicosHtml .= '</select>';

// Escapar filtros para uso seguro dentro del heredoc JS
$filtroEstadoJs    = e($filtroEstado);
$filtroPrioridadJs = e($filtroPrioridad);
$filtroSLAJs       = e($filtroSLA);
$filtroTecnicoJs   = e($filtroTecnico);

$extraScripts = <<<JS
<script>
const opcionesTecnicos = `{$selectTecnicosHtml}`;
// ============================================================
// CARGA DE TICKETS VÍA AJAX + DATATABLES SERVER-SIDE SIMULADO
// ============================================================

let dtTickets;

function cargarTickets() {
    const params = {
        estado:    '{$filtroEstadoJs}',
        prioridad: '{$filtroPrioridadJs}',
        sla:       '{$filtroSLAJs}',
        tecnico:   '{$filtroTecnicoJs}',
    };

    if (dtTickets) {
        dtTickets.destroy();
        $('#tbodyTickets').empty();
    }

    TriggerX.showLoading();

    $.get(TX.baseUrl + '/modules/tickets/ajax/listar.php', params, function (res) {
        TriggerX.hideLoading();
        if (!res.success) {
            TriggerX.toast(res.message || 'Error al cargar tickets', 'error');
            return;
        }

        const tickets = res.data || [];
        document.getElementById('totalTickets').textContent = tickets.length + ' ticket(s)';

        const tbody = document.getElementById('tbodyTickets');
        tbody.innerHTML = '';

        tickets.forEach(t => {
            const tr = document.createElement('tr');
            tr.innerHTML = renderTicketRow(t);
            tbody.appendChild(tr);
        });

        dtTickets = txDataTable('#tablaTickets', {
            order: [[8, 'desc']],
            columnDefs: [{ orderable: false, targets: [9] }],
            pageLength: 25,
        });

    }).fail(() => {
        TriggerX.hideLoading();
        TriggerX.toast('Error de conexión', 'error');
    });
}

function renderTicketRow(t) {
    const slaColors = { verde: '#198754', amarillo: '#ffc107', naranja: '#fd7e14', rojo: '#dc3545' };
    const slaColor  = slaColors[t.sla_estado] || '#6c757d';
    const tecnico   = t.tecnico_nombre || '<span class="text-muted">—</span>';
    const empresa   = t.cliente_empresa ? `<small class="text-muted d-block">\${t.cliente_empresa}</small>` : '';

    return `
        <td><span class="tx-ticket-code">\${e(t.codigo)}</span></td>
        <td>
            <div class="fw-500">\${e(t.cliente_nombre)}\${empresa}</div>
        </td>
        <td style="max-width:220px">
            <div class="text-truncate" title="\${e(t.asunto)}">\${e(t.asunto)}</div>
        </td>
        <td class="text-muted" style="font-size:12px">\${e(t.categoria_nombre || '—')}</td>
        <td><span class="badge" style="background:\${t.estado_color}">\${e(t.estado_nombre)}</span></td>
        <td><span class="badge border" style="color:\${t.prioridad_color};border-color:\${t.prioridad_color}!important;background:transparent">\${e(t.prioridad_nombre)}</span></td>
        <td>
            <span style="color:\${slaColor};font-weight:600;font-size:12.5px">
                <i class="bi bi-clock me-1"></i>\${t.dias_abierto}d
            </span>
        </td>
        <td style="font-size:12.5px">\${tecnico}</td>
        <td style="font-size:12px;white-space:nowrap;color:#6c757d">\${formatDate(t.created_at)}</td>
        <td>
            <div class="d-flex gap-1">
                <a href="\${TX.baseUrl}/modules/tickets/detalle.php?id=\${t.id}"
                   class="btn btn-sm btn-tx-outline" style="padding:3px 8px"
                   data-bs-toggle="tooltip" title="Ver detalle">
                    <i class="bi bi-eye"></i>
                </a>
                \${canEdit(t) ? `<button class="btn btn-sm" style="padding:3px 8px;background:var(--tx-bg);border:1px solid var(--tx-border)"
                   onclick="asignarTicket(\${t.id})" data-bs-toggle="tooltip" title="Asignar técnico">
                    <i class="bi bi-person-plus"></i>
                </button>` : ''}
            </div>
        </td>
    `;
}

function e(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function formatDate(d) {
    if (!d) return '—';
    const dt = new Date(d);
    return dt.toLocaleDateString('es-PE') + ' ' + dt.toLocaleTimeString('es-PE',{hour:'2-digit',minute:'2-digit'});
}

function canEdit(t) {
    const role = TX.userRole;
    return ['superadmin','admin','tecnico'].includes(role);
}

// ============================================================
// MODAL ASIGNAR
// ============================================================
function asignarTicket(ticketId) {
    Swal.fire({
        title: 'Asignar Técnico',
        html: opcionesTecnicos,
        confirmButtonText: 'Asignar',
        confirmButtonColor: TX.colorPrimario,
        showCancelButton: true,
        cancelButtonText: 'Cancelar',
        preConfirm: () => document.getElementById('swalTecnico').value,
    }).then(r => {
        if (r.isConfirmed) {
            TriggerX.post(
                TX.baseUrl + '/modules/tickets/ajax/acciones.php',
                { action: 'asignar', ticket_id: ticketId, tecnico_id: r.value },
                () => {
                    TriggerX.toast('Ticket asignado correctamente', 'success');
                    cargarTickets();
                }
            );
        }
    });
}

// ============================================================
// FORMULARIO NUEVO TICKET
// ============================================================
document.getElementById('formNuevoTicket')?.addEventListener('submit', function (e) {
    e.preventDefault();
    const btn = document.getElementById('btnCrearTicket');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

    const formData = new FormData(this);

    fetch(TX.baseUrl + '/modules/tickets/ajax/acciones.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': TX.csrfToken },
        body: formData,
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-save me-1"></i>Crear Ticket';
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalNuevoTicket')).hide();
            this.reset();
            TriggerX.toast('Ticket ' + res.data?.codigo + ' creado exitosamente', 'success');
            cargarTickets();
        } else {
            TriggerX.toast(res.message || 'Error al crear ticket', 'error');
        }
    });
});

// Inicializar
cargarTickets();
</script>
JS;

include CORE_PATH . '/layout/footer.php';
?>
