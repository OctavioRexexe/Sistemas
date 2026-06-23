<?php
/**
 * TRIGGERX - Cotizaciones: Gestión de Empresas/Clientes (catálogo reutilizable)
 */
define('TRIGGERX', true);
require_once __DIR__ . '/../../core/config/config.php';
requireLogin();
requireModule('cotizaciones');
requireRole('tecnico');
securityHeaders();

$db = Database::getInstance();

if (isPost()) {
    verifyCsrf();
    $action = post('action');

    if ($action === 'guardar') {
        $id    = (int)post('id');
        $razon = post('razon_social');
        $ruc   = post('ruc');

        if (!notEmpty($razon)) {
            redirectWith(BASE_URL . '/modules/cotizaciones/empresas.php', 'danger', 'La razón social es requerida');
        }

        $data = [
            'razon_social'      => $razon,
            'ruc'               => $ruc ?: null,
            'direccion'         => post('direccion') ?: null,
            'contacto_nombre'   => post('contacto_nombre') ?: null,
            'contacto_telefono' => post('contacto_telefono') ?: null,
            'contacto_email'    => post('contacto_email') ?: null,
        ];

        try {
            if ($id) {
                $db->update('empresas_clientes', $data, 'id = ?', [$id]);
                auditLog('empresa_actualizar', 'cotizaciones', "Empresa #{$id} actualizada");
                redirectWith(BASE_URL . '/modules/cotizaciones/empresas.php', 'success', 'Empresa actualizada');
            } else {
                $newId = $db->insert('empresas_clientes', $data);
                auditLog('empresa_crear', 'cotizaciones', "Empresa #{$newId} creada: {$razon}");
                redirectWith(BASE_URL . '/modules/cotizaciones/empresas.php', 'success', 'Empresa registrada');
            }
        } catch (Throwable $e) {
            redirectWith(BASE_URL . '/modules/cotizaciones/empresas.php', 'danger', 'Error al guardar (¿RUC duplicado?)');
        }
    }

    if ($action === 'desactivar') {
        $id = (int)post('id');
        $db->update('empresas_clientes', ['activo' => 0], 'id = ?', [$id]);
        redirectWith(BASE_URL . '/modules/cotizaciones/empresas.php', 'success', 'Empresa desactivada');
    }

    if ($action === 'activar') {
        $id = (int)post('id');
        $db->update('empresas_clientes', ['activo' => 1], 'id = ?', [$id]);
        redirectWith(BASE_URL . '/modules/cotizaciones/empresas.php', 'success', 'Empresa activada');
    }
}

$empresas = $db->fetchAll("SELECT * FROM empresas_clientes ORDER BY razon_social");

$pageTitle    = 'Empresas / Clientes';
$activeModule = 'cotizaciones';
include CORE_PATH . '/layout/header.php';
?>

<div class="container-fluid">
    <div class="tx-page-header">
        <div>
            <h1 class="tx-page-title"><i class="bi bi-building me-2" style="color:var(--tx-primary)"></i>Empresas / Clientes</h1>
            <div class="tx-breadcrumb">
                <a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a> ›
                <a href="<?= BASE_URL ?>/modules/cotizaciones/">Cotizaciones</a> › Empresas
            </div>
        </div>
        <button class="btn-tx" data-bs-toggle="modal" data-bs-target="#modalEmpresa" onclick="abrirModalNueva()">
            <i class="bi bi-plus-circle-fill"></i> Nueva Empresa
        </button>
    </div>

    <?= getFlashAlert() ?>

    <div class="tx-card">
        <div class="table-responsive">
            <table class="tx-table table mb-0">
                <thead>
                    <tr><th>Razón Social</th><th>RUC</th><th>Contacto</th><th>Estado</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                <?php if (empty($empresas)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">Sin empresas registradas</td></tr>
                <?php endif; ?>
                <?php foreach ($empresas as $e): ?>
                <tr>
                    <td class="fw-500"><?= e($e['razon_social']) ?></td>
                    <td class="font-monospace"><?= e($e['ruc'] ?: '—') ?></td>
                    <td style="font-size:12.5px"><?= e($e['contacto_nombre'] ?: '—') ?></td>
                    <td>
                        <span class="badge" style="background:<?= $e['activo'] ? '#198754' : '#6c757d' ?>">
                            <?= $e['activo'] ? 'Activa' : 'Inactiva' ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-tx-outline" style="padding:3px 8px"
                                    onclick='abrirModalEditar(<?= json_encode($e, JSON_HEX_APOS) ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿<?= $e['activo'] ? 'Desactivar' : 'Activar' ?> esta empresa?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="<?= $e['activo'] ? 'desactivar' : 'activar' ?>">
                                <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                <button type="submit" class="btn btn-sm" style="padding:3px 8px;background:var(--tx-bg);border:1px solid var(--tx-border)">
                                    <i class="bi bi-<?= $e['activo'] ? 'pause-circle' : 'play-circle' ?>"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL CREAR/EDITAR -->
<div class="modal fade" id="modalEmpresa" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header" style="background:var(--tx-primary);color:#fff">
                    <h6 class="modal-title" id="modalEmpresaTitulo"><i class="bi bi-building me-2"></i>Nueva Empresa</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="guardar">
                    <input type="hidden" name="id" id="empId" value="">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="tx-label">Razón Social <span class="req">*</span></label>
                            <input type="text" name="razon_social" id="empRazon" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="tx-label">RUC</label>
                            <input type="text" name="ruc" id="empRuc" class="form-control" maxlength="15">
                        </div>
                        <div class="col-md-6">
                            <label class="tx-label">Teléfono Contacto</label>
                            <input type="text" name="contacto_telefono" id="empTelefono" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="tx-label">Dirección</label>
                            <input type="text" name="direccion" id="empDireccion" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="tx-label">Contacto (Nombre)</label>
                            <input type="text" name="contacto_nombre" id="empContacto" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="tx-label">Email Contacto</label>
                            <input type="email" name="contacto_email" id="empEmail" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-tx btn-sm"><i class="bi bi-save me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script>
function abrirModalNueva() {
    document.getElementById("modalEmpresaTitulo").innerHTML = \'<i class="bi bi-building me-2"></i>Nueva Empresa\';
    document.getElementById("empId").value = "";
    ["empRazon","empRuc","empTelefono","empDireccion","empContacto","empEmail"].forEach(id => document.getElementById(id).value = "");
}
function abrirModalEditar(e) {
    document.getElementById("modalEmpresaTitulo").innerHTML = \'<i class="bi bi-building me-2"></i>Editar Empresa\';
    document.getElementById("empId").value = e.id;
    document.getElementById("empRazon").value = e.razon_social || "";
    document.getElementById("empRuc").value = e.ruc || "";
    document.getElementById("empTelefono").value = e.contacto_telefono || "";
    document.getElementById("empDireccion").value = e.direccion || "";
    document.getElementById("empContacto").value = e.contacto_nombre || "";
    document.getElementById("empEmail").value = e.contacto_email || "";
    new bootstrap.Modal(document.getElementById("modalEmpresa")).show();
}
</script>';
include CORE_PATH . '/layout/footer.php';
?>
