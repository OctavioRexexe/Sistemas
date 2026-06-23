<?php
define('TRIGGERX', true);
require_once __DIR__ . '/../core/config/config.php';
requireLogin();
requireRole('superadmin');
securityHeaders();

$db = Database::getInstance();

if (isPost()) {
    verifyCsrf();
    $action = post('action');

    if ($action === 'toggle') {
        $id     = (int)post('id');
        $activo = (int)post('activo');
        $db->update('modulos', ['activo' => $activo], 'id=?', [$id]);
        auditLog('modulo_toggle', 'admin', "Módulo #{$id} → activo={$activo}");
        jsonResponse(true, 'Estado actualizado');
    }

    if ($action === 'guardar') {
        $id = (int)post('id');
        $data = [
            'nombre'      => post('nombre'),
            'descripcion' => post('descripcion'),
            'icono'       => post('icono'),
            'url'         => post('url'),
            'orden'       => (int)post('orden'),
        ];
        if ($id) {
            $db->update('modulos', $data, 'id=?', [$id]);
        } else {
            $data['slug']   = preg_replace('/[^a-z0-9_]/', '', strtolower(post('slug')));
            $data['activo'] = 1;
            $db->insert('modulos', $data);
        }
        auditLog('modulo_guardar', 'admin', "Módulo guardado: " . post('nombre'));
        jsonResponse(true, 'Módulo guardado');
    }
}

$modulos = $db->fetchAll("SELECT * FROM modulos ORDER BY orden");
$pageTitle = 'Gestión de Módulos';
$activeModule = 'dashboard';
include CORE_PATH . '/layout/header.php';
?>
<div class="container-fluid">
    <div class="tx-page-header">
        <div>
            <h1 class="tx-page-title"><i class="bi bi-grid-fill me-2" style="color:var(--tx-primary)"></i>Gestión de Módulos</h1>
            <div class="tx-breadcrumb"><a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a> › Módulos</div>
        </div>
        <button class="btn-tx" onclick="nuevoModulo()">
            <i class="bi bi-plus-circle-fill"></i> Nuevo Módulo
        </button>
    </div>

    <div class="row g-3">
        <?php foreach ($modulos as $m): ?>
        <div class="col-md-4">
            <div class="tx-card">
                <div class="tx-card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div style="width:46px;height:46px;border-radius:12px;background:var(--tx-primary-light);
                                        display:grid;place-items:center;font-size:22px;color:var(--tx-primary)">
                                <i class="bi <?= e($m['icono']) ?>"></i>
                            </div>
                            <div>
                                <div class="fw-700"><?= e($m['nombre']) ?></div>
                                <code style="font-size:11px"><?= e($m['slug']) ?></code>
                            </div>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="mod<?= $m['id'] ?>"
                                   <?= $m['activo'] ? 'checked' : '' ?>
                                   onchange="toggleModulo(<?= $m['id'] ?>, this.checked)">
                        </div>
                    </div>
                    <p class="text-muted mb-2" style="font-size:12px"><?= e($m['descripcion'] ?? '') ?></p>
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted"><i class="bi bi-link-45deg"></i> <?= e($m['url']) ?></small>
                        <button class="btn btn-sm btn-tx-outline" style="padding:3px 10px;font-size:12px"
                                onclick="editarModulo(<?= htmlspecialchars(json_encode($m), ENT_QUOTES) ?>)">
                            <i class="bi bi-pencil"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Módulo -->
<div class="modal fade" id="modalModulo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--tx-primary);color:#fff">
                <h5 class="modal-title" id="modalModuloTitulo">Módulo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formModulo">
                <div class="modal-body">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="guardar">
                    <input type="hidden" name="id" id="mId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="tx-label">Nombre <span class="req">*</span></label>
                            <input type="text" name="nombre" id="mNombre" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="tx-label">Slug (ID único)</label>
                            <input type="text" name="slug" id="mSlug" class="form-control" placeholder="ej: inventario">
                        </div>
                        <div class="col-md-8">
                            <label class="tx-label">URL del módulo</label>
                            <input type="text" name="url" id="mUrl" class="form-control" placeholder="/modules/nombre/">
                        </div>
                        <div class="col-md-4">
                            <label class="tx-label">Orden</label>
                            <input type="number" name="orden" id="mOrden" class="form-control" value="1">
                        </div>
                        <div class="col-md-6">
                            <label class="tx-label">Icono Bootstrap Icons</label>
                            <input type="text" name="icono" id="mIcono" class="form-control" placeholder="bi-box-seam">
                            <small><a href="https://icons.getbootstrap.com" target="_blank">Ver iconos →</a></small>
                        </div>
                        <div class="col-12">
                            <label class="tx-label">Descripción</label>
                            <textarea name="descripcion" id="mDesc" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-tx">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraScripts = <<<JS
<script>
function toggleModulo(id, activo) {
    TriggerX.post(TX.baseUrl + '/admin/modules.php',
        { action:'toggle', id, activo: activo?1:0 },
        () => TriggerX.toast('Estado actualizado', 'success'),
        () => { document.getElementById('mod'+id).checked = !activo; }
    );
}
function nuevoModulo() {
    document.getElementById('modalModuloTitulo').textContent = 'Nuevo Módulo';
    document.getElementById('formModulo').reset();
    document.getElementById('mId').value = '';
    new bootstrap.Modal(document.getElementById('modalModulo')).show();
}
function editarModulo(m) {
    document.getElementById('modalModuloTitulo').textContent = 'Editar: ' + m.nombre;
    document.getElementById('mId').value    = m.id;
    document.getElementById('mNombre').value = m.nombre;
    document.getElementById('mSlug').value  = m.slug;
    document.getElementById('mUrl').value   = m.url;
    document.getElementById('mOrden').value = m.orden;
    document.getElementById('mIcono').value = m.icono;
    document.getElementById('mDesc').value  = m.descripcion || '';
    new bootstrap.Modal(document.getElementById('modalModulo')).show();
}
document.getElementById('formModulo').addEventListener('submit', function(e){
    e.preventDefault();
    const data = Object.fromEntries(new FormData(this));
    TriggerX.post(TX.baseUrl + '/admin/modules.php', data, () => {
        bootstrap.Modal.getInstance(document.getElementById('modalModulo')).hide();
        TriggerX.toast('Módulo guardado', 'success');
        setTimeout(() => location.reload(), 1200);
    });
});
</script>
JS;
include CORE_PATH . '/layout/footer.php';
?>
