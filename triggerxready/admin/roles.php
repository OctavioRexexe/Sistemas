<?php
define('TRIGGERX', true);
require_once __DIR__ . '/../core/config/config.php';
requireLogin();
requireRole('superadmin');
securityHeaders();

$db = Database::getInstance();

if (isPost()) {
    verifyCsrf();

    if (post('action') === 'guardar') {
        $id   = (int)post('id');
        $data = [
            'nombre'      => post('nombre'),
            'descripcion' => post('descripcion'),
            'nivel'       => (int)post('nivel'),
        ];
        if ($id) {
            // No permitir editar superadmin (id=1)
            if ($id === 1) jsonResponse(false, 'No se puede editar el rol SuperAdmin');
            $db->update('roles', $data, 'id=?', [$id]);
        } else {
            $data['slug'] = preg_replace('/[^a-z0-9_]/', '', strtolower(post('slug')));
            $db->insert('roles', $data);
        }
        auditLog('rol_guardar', 'admin', 'Rol guardado: ' . post('nombre'));
        jsonResponse(true, 'Rol guardado');
    }
}

$roles = $db->fetchAll("SELECT r.*, COUNT(u.id) AS total_usuarios FROM roles r LEFT JOIN usuarios u ON u.rol_id=r.id GROUP BY r.id ORDER BY r.nivel DESC");

$pageTitle    = 'Gestión de Roles';
$activeModule = 'dashboard';
include CORE_PATH . '/layout/header.php';
?>
<div class="container-fluid">
    <div class="tx-page-header">
        <div>
            <h1 class="tx-page-title"><i class="bi bi-shield-lock-fill me-2" style="color:var(--tx-primary)"></i>Gestión de Roles</h1>
            <div class="tx-breadcrumb"><a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a> › Roles</div>
        </div>
        <button class="btn-tx" onclick="nuevoRol()">
            <i class="bi bi-plus-circle-fill"></i> Nuevo Rol
        </button>
    </div>

    <div class="row g-3">
        <?php
        $colores = [4=>'#dc3545',3=>'#F4A020',2=>'#0d6efd',1=>'#198754'];
        foreach ($roles as $r):
            $col = $colores[$r['nivel']] ?? '#6c757d';
        ?>
        <div class="col-md-6 col-lg-3">
            <div class="tx-card h-100">
                <div class="tx-card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div style="width:46px;height:46px;border-radius:12px;background:<?= $col ?>22;
                                    display:grid;place-items:center;font-size:20px;color:<?= $col ?>">
                            <i class="bi bi-shield-fill"></i>
                        </div>
                        <span class="badge" style="background:<?= $col ?>;font-size:11px">Nivel <?= $r['nivel'] ?></span>
                    </div>
                    <div class="fw-700 fs-6 mb-1"><?= e($r['nombre']) ?></div>
                    <div class="text-muted mb-3" style="font-size:12px"><?= e($r['descripcion'] ?? '') ?></div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted" style="font-size:12px">
                            <i class="bi bi-people me-1"></i><?= $r['total_usuarios'] ?> usuario(s)
                        </span>
                        <?php if ($r['id'] != 1): ?>
                        <button class="btn btn-sm btn-tx-outline" style="padding:3px 10px;font-size:12px"
                                onclick="editarRol(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Rol -->
<div class="modal fade" id="modalRol" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--tx-primary);color:#fff">
                <h5 class="modal-title" id="tituloModalRol">Rol</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formRol">
                <div class="modal-body">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="guardar">
                    <input type="hidden" name="id" id="rId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="tx-label">Nombre <span class="req">*</span></label>
                            <input type="text" name="nombre" id="rNombre" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="tx-label">Slug</label>
                            <input type="text" name="slug" id="rSlug" class="form-control" placeholder="ej: supervisor">
                        </div>
                        <div class="col-md-4">
                            <label class="tx-label">Nivel (1-4)</label>
                            <input type="number" name="nivel" id="rNivel" class="form-control" min="1" max="3" value="1">
                            <small class="text-muted">1=cliente, 2=técnico, 3=admin</small>
                        </div>
                        <div class="col-12">
                            <label class="tx-label">Descripción</label>
                            <textarea name="descripcion" id="rDesc" class="form-control" rows="2"></textarea>
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
function nuevoRol() {
    document.getElementById('tituloModalRol').textContent = 'Nuevo Rol';
    document.getElementById('formRol').reset();
    document.getElementById('rId').value = '';
    new bootstrap.Modal(document.getElementById('modalRol')).show();
}
function editarRol(r) {
    document.getElementById('tituloModalRol').textContent = 'Editar: ' + r.nombre;
    document.getElementById('rId').value    = r.id;
    document.getElementById('rNombre').value = r.nombre;
    document.getElementById('rSlug').value  = r.slug;
    document.getElementById('rNivel').value = r.nivel;
    document.getElementById('rDesc').value  = r.descripcion || '';
    new bootstrap.Modal(document.getElementById('modalRol')).show();
}
document.getElementById('formRol').addEventListener('submit', function(e){
    e.preventDefault();
    TriggerX.post(TX.baseUrl + '/admin/roles.php', Object.fromEntries(new FormData(this)), () => {
        bootstrap.Modal.getInstance(document.getElementById('modalRol')).hide();
        TriggerX.toast('Rol guardado', 'success');
        setTimeout(() => location.reload(), 1200);
    });
});
</script>
JS;
include CORE_PATH . '/layout/footer.php';
?>
