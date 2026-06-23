<?php
/**
 * TRIGGERX - Cotizaciones: Formulario (crear / editar borrador / ver)
 */
define('TRIGGERX', true);
require_once __DIR__ . '/../../core/config/config.php';
requireLogin();
requireModule('cotizaciones');
requireRole('tecnico');
securityHeaders();

$db   = Database::getInstance();
$user = Session::getUser();

$cotizacionId = (int)get_param('id');
$ticketId     = (int)get_param('ticket_id');

$cot = null;
if ($cotizacionId) {
    $cot = $db->fetch("SELECT * FROM cotizaciones WHERE id = ?", [$cotizacionId]);
    if (!$cot) redirectWith(BASE_URL . '/modules/cotizaciones/', 'danger', 'Cotización no encontrada');
    if (!Session::hasRole('admin') && (int)$cot['usuario_id'] !== (int)$user['id']) {
        redirectWith(BASE_URL . '/modules/cotizaciones/', 'danger', 'No tienes permiso sobre esta cotización');
    }
}

// Si viene de un ticket, traer datos para pre-cargar empresa/cliente
$ticketOrigen = null;
if ($ticketId && !$cot) {
    $ticketOrigen = $db->fetch("SELECT * FROM tickets WHERE id = ?", [$ticketId]);
}

// Si no existe cotización todavía, crear un borrador vacío al entrar al formulario
if (!$cot) {
    $codigo = generarCodigoCotizacion();
    $diasValidez = Config::cotizacion()['dias_validez'];

    $empresaRazon = $ticketOrigen ? ($ticketOrigen['cliente_empresa'] ?: $ticketOrigen['cliente_nombre']) : '';
    $clienteNombre = $ticketOrigen['cliente_nombre'] ?? '';

    $newId = $db->insert('cotizaciones', [
        'codigo'              => $codigo,
        'usuario_id'          => $user['id'],
        'ticket_id'           => $ticketId ?: null,
        'empresa_razon_social'=> $empresaRazon,
        'cliente_nombre'      => $clienteNombre,
        'fecha_emision'       => date('Y-m-d'),
        'fecha_vencimiento'   => date('Y-m-d', strtotime("+{$diasValidez} days")),
        'estado'              => 'borrador',
    ]);

    redirect(BASE_URL . '/modules/cotizaciones/form.php?id=' . $newId);
}

$puedeEditar = $cot['estado'] === 'borrador' && (Session::hasRole('admin') || (int)$cot['usuario_id'] === (int)$user['id']);
$items = $db->fetchAll("SELECT * FROM cotizacion_items WHERE cotizacion_id = ? ORDER BY orden", [$cot['id']]);
[$estNombre, $estColor] = badgeEstadoCotizacion($cot['estado']);
$igvPct = Config::cotizacion()['igv_pct'];

$pageTitle    = "Cotización {$cot['codigo']}";
$activeModule = 'cotizaciones';
$extraHead = '<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<style>
#cotizacionPdfPreview { background:#fff; padding:30px; max-width:760px; margin:0 auto; font-size:13px; color:#1a1a2e; }
#cotizacionPdfPreview table { width:100%; border-collapse: collapse; margin-bottom: 14px; }
#cotizacionPdfPreview table td, #cotizacionPdfPreview table th { padding:6px 8px; border:1px solid #ddd; vertical-align:top; }
.tx-autocomplete-results { position:absolute; z-index:50; background:#fff; border:1px solid var(--tx-border); border-radius:8px; max-height:220px; overflow-y:auto; width:100%; box-shadow:0 6px 20px rgba(0,0,0,.1); display:none; }
.tx-autocomplete-results .item { padding:8px 12px; cursor:pointer; font-size:13px; border-bottom:1px solid #f1f1f1; }
.tx-autocomplete-results .item:hover { background:var(--tx-bg); }
.tx-autocomplete-wrap { position:relative; }
</style>';
include CORE_PATH . '/layout/header.php';
?>

<div class="container-fluid" style="max-width:980px">

    <div class="tx-page-header">
        <div>
            <h1 class="tx-page-title">
                <i class="bi bi-file-earmark-spreadsheet-fill me-2" style="color:var(--tx-primary)"></i>
                Cotización <span class="text-muted" style="font-size:16px">— <?= e($cot['codigo']) ?></span>
            </h1>
            <div class="tx-breadcrumb">
                <a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a> ›
                <a href="<?= BASE_URL ?>/modules/cotizaciones/">Cotizaciones</a> ›
                <?= e($cot['codigo']) ?>
            </div>
        </div>
        <span class="badge fs-6" style="background:<?= $estColor ?>"><?= $estNombre ?></span>
    </div>

    <?= getFlashAlert() ?>

    <?php if ($cot['estado'] !== 'borrador'): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle-fill me-2"></i>
        Esta cotización ya fue generada y no puede editarse. Si necesitas cambios, crea una nueva.
        <?php if ($cot['pdf_ruta']): ?>
        <a href="<?= UPLOADS_URL ?>/<?= e($cot['pdf_ruta']) ?>" target="_blank" class="ms-2 fw-600">
            <i class="bi bi-download me-1"></i>Descargar PDF
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <form id="formCabecera" autocomplete="off">
        <input type="hidden" id="cotizacionId" value="<?= $cot['id'] ?>">
        <?= csrfField() ?>

        <!-- DATOS DE LA COTIZACIÓN -->
        <div class="tx-card mb-4">
            <div class="tx-card-header"><h6 class="tx-card-title"><i class="bi bi-info-circle"></i>Datos Generales</h6></div>
            <div class="tx-card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="tx-label">Fecha de Emisión</label>
                        <div class="tx-readonly-field" style="background:var(--tx-bg);border-radius:8px;padding:8px 12px"><?= fdate($cot['fecha_emision']) ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="tx-label">Fecha de Vencimiento</label>
                        <div class="tx-readonly-field" style="background:var(--tx-bg);border-radius:8px;padding:8px 12px"><?= fdate($cot['fecha_vencimiento']) ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="tx-label">Ticket Relacionado</label>
                        <div class="tx-readonly-field" style="background:var(--tx-bg);border-radius:8px;padding:8px 12px">
                            <?php if ($cot['ticket_id']):
                                $tk = $db->fetch("SELECT codigo FROM tickets WHERE id = ?", [$cot['ticket_id']]); ?>
                            <a href="<?= BASE_URL ?>/modules/tickets/detalle.php?id=<?= $cot['ticket_id'] ?>"><?= e($tk['codigo'] ?? '—') ?></a>
                            <?php else: ?>
                            <span class="text-muted">— Cotización libre —</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- EMPRESA / CLIENTE -->
        <div class="tx-card mb-4">
            <div class="tx-card-header"><h6 class="tx-card-title"><i class="bi bi-building"></i>Empresa / Cliente</h6></div>
            <div class="tx-card-body">
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="tx-label">Empresa / Razón Social</label>
                        <div class="tx-autocomplete-wrap">
                            <input type="text" name="empresa_razon_social" id="empresaInput" class="form-control"
                                   value="<?= e($cot['empresa_razon_social']) ?>" placeholder="Buscar empresa registrada o escribir nueva..."
                                   <?= $puedeEditar ? '' : 'disabled' ?>>
                            <div class="tx-autocomplete-results" id="empresaResults"></div>
                        </div>
                        <input type="hidden" id="empresaIdInput" value="<?= e($cot['empresa_id'] ?? '') ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="tx-label">RUC</label>
                        <input type="text" name="empresa_ruc" id="rucInput" class="form-control" maxlength="15"
                               value="<?= e($cot['empresa_ruc'] ?? '') ?>" <?= $puedeEditar ? '' : 'disabled' ?>>
                    </div>
                    <div class="col-md-12">
                        <label class="tx-label">Dirección</label>
                        <input type="text" name="empresa_direccion" id="direccionInput" class="form-control"
                               value="<?= e($cot['empresa_direccion'] ?? '') ?>" <?= $puedeEditar ? '' : 'disabled' ?>>
                    </div>
                    <div class="col-md-12">
                        <label class="tx-label">Cliente (Nombre y Apellido)</label>
                        <input type="text" name="cliente_nombre" id="clienteNombreInput" class="form-control"
                               value="<?= e($cot['cliente_nombre']) ?>" <?= $puedeEditar ? '' : 'disabled' ?>>
                    </div>
                </div>
                <?php if ($puedeEditar): ?>
                <button type="button" class="btn btn-sm mt-3" id="btnGuardarCabecera" style="background:var(--tx-bg);border:1px solid var(--tx-border)">
                    <i class="bi bi-save me-1"></i>Guardar Datos
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- ITEMS -->
        <div class="tx-card mb-4">
            <div class="tx-card-header"><h6 class="tx-card-title"><i class="bi bi-list-ul"></i>Detalle de la Cotización</h6></div>
            <div class="tx-card-body">
                <div class="table-responsive">
                    <table class="tx-table table mb-0" id="tablaItems">
                        <thead>
                            <tr><th>Código</th><th>Descripción</th><th style="width:90px">Cant.</th><th style="width:120px">P. Unit.</th><th style="width:120px">Subtotal</th><th style="width:50px"></th></tr>
                        </thead>
                        <tbody id="tbodyItems">
                            <?php foreach ($items as $it): ?>
                            <tr data-item-id="<?= $it['id'] ?>">
                                <td><?= e($it['codigo'] ?: '—') ?></td>
                                <td><?= e($it['descripcion']) ?></td>
                                <td><?= number_format((float)$it['cantidad'], 2) ?></td>
                                <td>S/ <?= number_format((float)$it['precio_unitario'], 2) ?></td>
                                <td class="fw-600">S/ <?= number_format((float)$it['subtotal_linea'], 2) ?></td>
                                <td>
                                    <?php if ($puedeEditar): ?>
                                    <button type="button" class="btn btn-sm text-danger btnEliminarItem" data-id="<?= $it['id'] ?>" style="padding:2px 6px">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($puedeEditar): ?>
                <hr class="my-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3 tx-autocomplete-wrap">
                        <label class="tx-label">Código</label>
                        <input type="text" id="itemCodigo" class="form-control form-control-sm" placeholder="Buscar producto...">
                        <div class="tx-autocomplete-results" id="productoResults"></div>
                        <input type="hidden" id="itemProductoId">
                    </div>
                    <div class="col-md-4">
                        <label class="tx-label">Descripción</label>
                        <input type="text" id="itemDescripcion" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-2">
                        <label class="tx-label">Cantidad</label>
                        <input type="number" id="itemCantidad" class="form-control form-control-sm" value="1" min="0.01" step="0.01">
                    </div>
                    <div class="col-md-2">
                        <label class="tx-label">P. Unitario</label>
                        <input type="number" id="itemPrecio" class="form-control form-control-sm" value="0" min="0" step="0.01">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn-tx btn-sm w-100" id="btnAgregarItem"><i class="bi bi-plus"></i></button>
                    </div>
                </div>
                <?php endif; ?>

                <div class="d-flex justify-content-end mt-4">
                    <div style="min-width:280px">
                        <div class="d-flex justify-content-between py-1" style="font-size:13.5px">
                            <span>Subtotal</span><span id="lblSubtotal">S/ <?= number_format((float)$cot['subtotal'], 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-1" style="font-size:13.5px">
                            <span>IGV (<?= $igvPct ?>%)</span><span id="lblIgv">S/ <?= number_format((float)$cot['igv'], 2) ?></span>
                        </div>
                        <hr class="my-1">
                        <div class="d-flex justify-content-between py-1 fw-700 fs-5">
                            <span>Total</span><span id="lblTotal" style="color:var(--tx-primary)">S/ <?= number_format((float)$cot['total'], 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- NOTAS -->
        <div class="tx-card mb-4">
            <div class="tx-card-header"><h6 class="tx-card-title"><i class="bi bi-card-text"></i>Notas / Condiciones</h6></div>
            <div class="tx-card-body">
                <textarea name="notas" id="notasInput" class="form-control" rows="3"
                          placeholder="Condiciones de pago, validez de la oferta, etc." <?= $puedeEditar ? '' : 'disabled' ?>><?= e($cot['notas'] ?? '') ?></textarea>
            </div>
        </div>

        <?php if ($puedeEditar): ?>
        <div class="d-flex justify-content-between mb-5 flex-wrap gap-2">
            <a href="<?= BASE_URL ?>/modules/cotizaciones/" class="btn" style="background:var(--tx-bg);border:1px solid var(--tx-border)">
                <i class="bi bi-arrow-left me-1"></i>Volver
            </a>
            <button type="button" class="btn-tx" id="btnGenerarPdf">
                <i class="bi bi-file-earmark-pdf-fill me-1"></i>Generar Cotización (PDF)
            </button>
        </div>
        <?php else: ?>
        <div class="d-flex justify-content-between mb-5 flex-wrap gap-2">
            <a href="<?= BASE_URL ?>/modules/cotizaciones/" class="btn" style="background:var(--tx-bg);border:1px solid var(--tx-border)">
                <i class="bi bi-arrow-left me-1"></i>Volver
            </a>
            <div class="d-flex gap-2">
                <?php if ($cot['estado'] === 'enviada'): ?>
                <button type="button" class="btn btn-sm btn-outline-success" data-estado="aprobada">
                    <i class="bi bi-check-circle me-1"></i>Marcar Aprobada
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" data-estado="rechazada">
                    <i class="bi bi-x-circle me-1"></i>Marcar Rechazada
                </button>
                <button type="button" class="btn-tx-outline btn-sm" id="btnAbrirEnvioCorreo">
                    <i class="bi bi-envelope me-1"></i>Enviar por Correo
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </form>
</div>

<!-- MODAL: CONFIRMACIÓN ANTES DE GENERAR PDF -->
<div class="modal fade" id="modalConfirmar" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--tx-primary);color:#fff">
                <h6 class="modal-title"><i class="bi bi-clipboard-check me-2"></i>Confirma los datos antes de generar</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="background:#f4f5f7">
                <div id="cotizacionPdfPreview"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Seguir editando</button>
                <button type="button" class="btn-tx btn-sm" id="btnConfirmarGenerar">
                    <i class="bi bi-check-circle-fill me-1"></i>Confirmar y Generar PDF
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: ENVÍO DE CORREO -->
<div class="modal fade" id="modalEmail" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--tx-primary);color:#fff">
                <h6 class="modal-title"><i class="bi bi-envelope-fill me-2"></i>Enviar Cotización por Correo</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="tx-label">Correo de destino</label>
                <input type="email" id="emailDestinoInput" class="form-control" placeholder="cliente@empresa.com">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn-tx btn-sm" id="btnConfirmarEnvioEmail">
                    <i class="bi bi-send-fill me-1"></i>Enviar
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$cotJsData = [
    'id'        => (int)$cot['id'],
    'codigo'    => $cot['codigo'],
    'fechaEmision' => fdate($cot['fecha_emision']),
    'fechaVencimiento' => $cot['fecha_vencimiento'] ? fdate($cot['fecha_vencimiento']) : '—',
    'igvPct'    => $igvPct,
    'puedeEditar' => $puedeEditar,
];
$cotJsJson = json_encode($cotJsData, JSON_UNESCAPED_UNICODE);

$extraScripts = <<<JS
<script>
const COT_DATA = {$cotJsJson};
const AJAX_URL = TX.baseUrl + '/modules/cotizaciones/ajax/acciones.php';

// ============================================================
// AUTOCOMPLETADO GENÉRICO
// ============================================================
function setupAutocomplete(inputEl, resultsEl, action, renderItem, onSelect) {
    let timeoutId;
    inputEl.addEventListener('input', function () {
        clearTimeout(timeoutId);
        const q = this.value.trim();
        if (q.length < 2) { resultsEl.style.display = 'none'; return; }
        timeoutId = setTimeout(() => {
            TriggerX.post(AJAX_URL, { action: action, q: q }, (res) => {
                const items = res.data || [];
                if (!items.length) { resultsEl.style.display = 'none'; return; }
                resultsEl.innerHTML = items.map((it, idx) => `<div class="item" data-idx="\${idx}">\${renderItem(it)}</div>`).join('');
                resultsEl.style.display = 'block';
                resultsEl.querySelectorAll('.item').forEach((el, idx) => {
                    el.addEventListener('click', () => { onSelect(items[idx]); resultsEl.style.display = 'none'; });
                });
            });
        }, 300);
    });
    document.addEventListener('click', (e) => {
        if (!inputEl.contains(e.target) && !resultsEl.contains(e.target)) resultsEl.style.display = 'none';
    });
}

if (COT_DATA.puedeEditar) {
    // Autocompletado de empresa
    setupAutocomplete(
        document.getElementById('empresaInput'),
        document.getElementById('empresaResults'),
        'buscar_empresa',
        (e) => `<strong>\${e.razon_social}</strong>\${e.ruc ? ' — RUC ' + e.ruc : ''}`,
        (e) => {
            document.getElementById('empresaInput').value = e.razon_social;
            document.getElementById('empresaIdInput').value = e.id;
            document.getElementById('rucInput').value = e.ruc || '';
            document.getElementById('direccionInput').value = e.direccion || '';
            if (e.contacto_nombre && !document.getElementById('clienteNombreInput').value) {
                document.getElementById('clienteNombreInput').value = e.contacto_nombre;
            }
        }
    );

    // Autocompletado de producto
    setupAutocomplete(
        document.getElementById('itemCodigo'),
        document.getElementById('productoResults'),
        'buscar_producto',
        (p) => `<strong>\${p.codigo}</strong> — \${p.descripcion} (S/ \${parseFloat(p.precio_unitario).toFixed(2)})`,
        (p) => {
            document.getElementById('itemCodigo').value = p.codigo;
            document.getElementById('itemProductoId').value = p.id;
            document.getElementById('itemDescripcion').value = p.descripcion;
            document.getElementById('itemPrecio').value = p.precio_unitario;
        }
    );

    // Guardar cabecera (empresa/cliente/notas)
    document.getElementById('btnGuardarCabecera')?.addEventListener('click', function () {
        const data = {
            action: 'guardar_cabecera', id: COT_DATA.id,
            empresa_razon_social: document.getElementById('empresaInput').value,
            empresa_ruc: document.getElementById('rucInput').value,
            empresa_direccion: document.getElementById('direccionInput').value,
            cliente_nombre: document.getElementById('clienteNombreInput').value,
            notas: document.getElementById('notasInput').value,
        };
        TriggerX.post(AJAX_URL, data, () => TriggerX.toast('Datos guardados', 'success'));
    });

    // Agregar ítem
    document.getElementById('btnAgregarItem')?.addEventListener('click', function () {
        const descripcion = document.getElementById('itemDescripcion').value.trim();
        const cantidad = parseFloat(document.getElementById('itemCantidad').value || '0');
        const precio = parseFloat(document.getElementById('itemPrecio').value || '0');

        if (!descripcion) { TriggerX.toast('Ingresa una descripción', 'warning'); return; }
        if (cantidad <= 0) { TriggerX.toast('La cantidad debe ser mayor a 0', 'warning'); return; }

        TriggerX.post(AJAX_URL, {
            action: 'agregar_item', id: COT_DATA.id,
            producto_id: document.getElementById('itemProductoId').value,
            codigo: document.getElementById('itemCodigo').value,
            descripcion: descripcion, cantidad: cantidad, precio_unitario: precio,
        }, (res) => {
            const subtotal = (cantidad * precio).toFixed(2);
            const tr = document.createElement('tr');
            tr.dataset.itemId = res.data.item_id;
            tr.innerHTML = `
                <td>\${document.getElementById('itemCodigo').value || '—'}</td>
                <td>\${descripcion}</td>
                <td>\${cantidad.toFixed(2)}</td>
                <td>S/ \${precio.toFixed(2)}</td>
                <td class="fw-600">S/ \${subtotal}</td>
                <td><button type="button" class="btn btn-sm text-danger btnEliminarItem" data-id="\${res.data.item_id}" style="padding:2px 6px"><i class="bi bi-trash"></i></button></td>
            `;
            document.getElementById('tbodyItems').appendChild(tr);
            actualizarTotalesUI(res.data.totales);
            bindEliminarItem(tr.querySelector('.btnEliminarItem'));

            document.getElementById('itemCodigo').value = '';
            document.getElementById('itemProductoId').value = '';
            document.getElementById('itemDescripcion').value = '';
            document.getElementById('itemCantidad').value = '1';
            document.getElementById('itemPrecio').value = '0';
        });
    });

    function bindEliminarItem(btn) {
        btn?.addEventListener('click', function () {
            const itemId = this.dataset.id;
            TriggerX.confirm('Eliminar ítem', '¿Quitar este ítem de la cotización?', () => {
                TriggerX.post(AJAX_URL, { action: 'eliminar_item', item_id: itemId }, (res) => {
                    this.closest('tr').remove();
                    actualizarTotalesUI(res.data.totales);
                });
            });
        });
    }
    document.querySelectorAll('.btnEliminarItem').forEach(bindEliminarItem);

    function actualizarTotalesUI(totales) {
        document.getElementById('lblSubtotal').textContent = 'S/ ' + parseFloat(totales.subtotal).toFixed(2);
        document.getElementById('lblIgv').textContent = 'S/ ' + parseFloat(totales.igv).toFixed(2);
        document.getElementById('lblTotal').textContent = 'S/ ' + parseFloat(totales.total).toFixed(2);
    }

    // ============================================================
    // GENERAR PDF
    // ============================================================
    function construirHtmlCotizacion() {
        let filas = '';
        document.querySelectorAll('#tbodyItems tr').forEach(tr => {
            const tds = tr.querySelectorAll('td');
            filas += `<tr><td>\${tds[0].textContent}</td><td>\${tds[1].textContent}</td><td style="text-align:center">\${tds[2].textContent}</td><td style="text-align:right">\${tds[3].textContent}</td><td style="text-align:right">\${tds[4].textContent}</td></tr>`;
        });

        return `
            <h2 style="text-align:center;margin-bottom:4px">COTIZACIÓN</h2>
            <p style="text-align:center;color:#666;margin-bottom:18px">\${COT_DATA.codigo}</p>
            <table>
                <tr><td><strong>Empresa</strong></td><td>\${document.getElementById('empresaInput').value}</td><td><strong>RUC</strong></td><td>\${document.getElementById('rucInput').value || '—'}</td></tr>
                <tr><td><strong>Dirección</strong></td><td colspan="3">\${document.getElementById('direccionInput').value || '—'}</td></tr>
                <tr><td><strong>Cliente</strong></td><td>\${document.getElementById('clienteNombreInput').value}</td><td><strong>Fecha Emisión</strong></td><td>\${COT_DATA.fechaEmision}</td></tr>
                <tr><td><strong>Válido hasta</strong></td><td colspan="3">\${COT_DATA.fechaVencimiento}</td></tr>
            </table>
            <table>
                <tr><th>Código</th><th>Descripción</th><th>Cant.</th><th>P. Unit.</th><th>Subtotal</th></tr>
                \${filas}
            </table>
            <div style="display:flex;justify-content:flex-end">
                <table style="width:280px">
                    <tr><td>Subtotal</td><td style="text-align:right">\${document.getElementById('lblSubtotal').textContent}</td></tr>
                    <tr><td>IGV (\${COT_DATA.igvPct}%)</td><td style="text-align:right">\${document.getElementById('lblIgv').textContent}</td></tr>
                    <tr><td><strong>Total</strong></td><td style="text-align:right"><strong>\${document.getElementById('lblTotal').textContent}</strong></td></tr>
                </table>
            </div>
            \${document.getElementById('notasInput').value ? `<p style="margin-top:16px"><strong>Notas:</strong><br>\${document.getElementById('notasInput').value}</p>` : ''}
        `;
    }

    document.getElementById('btnGenerarPdf')?.addEventListener('click', function () {
        const filas = document.querySelectorAll('#tbodyItems tr').length;
        if (filas === 0) { TriggerX.toast('Agrega al menos un ítem', 'warning'); return; }
        if (!document.getElementById('empresaInput').value.trim()) { TriggerX.toast('Ingresa la empresa', 'warning'); return; }

        document.getElementById('cotizacionPdfPreview').innerHTML = construirHtmlCotizacion();
        new bootstrap.Modal(document.getElementById('modalConfirmar')).show();
    });

    document.getElementById('btnConfirmarGenerar')?.addEventListener('click', async function () {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generando...';

        try {
            // Guardar cabecera antes de generar
            await new Promise((resolve, reject) => {
                TriggerX.post(AJAX_URL, {
                    action: 'guardar_cabecera', id: COT_DATA.id,
                    empresa_razon_social: document.getElementById('empresaInput').value,
                    empresa_ruc: document.getElementById('rucInput').value,
                    empresa_direccion: document.getElementById('direccionInput').value,
                    cliente_nombre: document.getElementById('clienteNombreInput').value,
                    notas: document.getElementById('notasInput').value,
                }, resolve, reject);
            });

            const elemento = document.getElementById('cotizacionPdfPreview');
            const canvas = await html2canvas(elemento, { scale: 2 });
            const imgData = canvas.toDataURL('image/png');

            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF('p', 'mm', 'a4');
            const pageWidth = pdf.internal.pageSize.getWidth();
            const imgHeight = (canvas.height * pageWidth) / canvas.width;
            pdf.addImage(imgData, 'PNG', 0, 0, pageWidth, imgHeight);
            const pdfBase64 = pdf.output('datauristring');

            TriggerX.post(AJAX_URL, { action: 'finalizar', id: COT_DATA.id, pdf_base64: pdfBase64 }, () => {
                TriggerX.toast('Cotización generada correctamente', 'success');
                setTimeout(() => location.reload(), 1200);
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
}

// ============================================================
// CAMBIO DE ESTADO (aprobada / rechazada)
// ============================================================
document.querySelectorAll('[data-estado]').forEach(btn => {
    btn.addEventListener('click', function () {
        const estado = this.dataset.estado;
        TriggerX.confirm('Cambiar estado', `¿Marcar esta cotización como "\${estado}"?`, () => {
            TriggerX.post(AJAX_URL, { action: 'cambiar_estado', id: COT_DATA.id, estado: estado }, () => {
                TriggerX.toast('Estado actualizado', 'success');
                setTimeout(() => location.reload(), 1000);
            });
        });
    });
});

// ============================================================
// ENVÍO DE CORREO
// ============================================================
document.getElementById('btnAbrirEnvioCorreo')?.addEventListener('click', function () {
    new bootstrap.Modal(document.getElementById('modalEmail')).show();
});
document.getElementById('btnConfirmarEnvioEmail')?.addEventListener('click', function () {
    const email = document.getElementById('emailDestinoInput').value.trim();
    if (!email) { TriggerX.toast('Ingresa un correo válido', 'warning'); return; }
    TriggerX.post(AJAX_URL, { action: 'enviar_email', id: COT_DATA.id, email_destino: email }, () => {
        TriggerX.toast('Cotización enviada por correo', 'success');
        bootstrap.Modal.getInstance(document.getElementById('modalEmail')).hide();
    });
});
</script>
JS;
include CORE_PATH . '/layout/footer.php';
?>
