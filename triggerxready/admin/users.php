<?php
define('TRIGGERX', true);
require_once __DIR__ . '/../core/config/config.php';
requireLogin();
requireRole('admin');
securityHeaders();

$db = Database::getInstance();

// POST: crear/editar/eliminar usuario
if (isPost()) {
    verifyCsrf();
    $action = post('action');

    if ($action === 'crear' || $action === 'editar') {
        $campos = ['nombre','apellido','email','rol_id'];
        foreach ($campos as $c) {
            if (!notEmpty(post($c))) jsonResponse(false, "Campo requerido: {$c}");
        }
        if (!isValidEmail(post('email'))) jsonResponse(false, 'Email inválido');

        $data = [
            'nombre'   => post('nombre'),
            'apellido' => post('apellido'),
            'email'    => post('email'),
            'rol_id'   => (int)post('rol_id'),
            'empresa'  => post('empresa'),
            'telefono' => post('telefono'),
            'activo'   => (int)(post('activo','1') === '1'),
        ];

        if ($action === 'crear') {
            $pass = post('password');
            if (strlen($pass) < 6) jsonResponse(false, 'La contraseña debe tener al menos 6 caracteres');
            // Verificar email único
            $existe = $db->fetchColumn("SELECT COUNT(*) FROM usuarios WHERE email=?", [$data['email']]);
            if ($existe) jsonResponse(false, 'El correo ya está registrado');
            $data['password'] = password_hash($pass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            $id = $db->insert('usuarios', $data);
            auditLog('usuario_crear', 'admin', "Usuario creado: {$data['email']}");
            jsonResponse(true, 'Usuario creado exitosamente', ['id' => $id]);
        } else {
            $id = (int)post('id');
            if (!$id) jsonResponse(false, 'ID inválido');
            if (!empty(post('password')) && strlen(post('password')) >= 6) {
                $data['password'] = password_hash(post('password'), PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            }
            $db->update('usuarios', $data, 'id=?', [$id]);
            auditLog('usuario_editar', 'admin', "Usuario editado: #{$id}");
            jsonResponse(true, 'Usuario actualizado');
        }
    }

    if ($action === 'eliminar') {
        requireRole('superadmin');
        $id = (int)post('id');
        if ($id === Session::userId()) jsonResponse(false, 'No puedes eliminarte a ti mismo');
        $db->update('usuarios', ['activo' => 0], 'id=?', [$id]);
        auditLog('usuario_desactivar', 'admin', "Usuario desactivado: #{$id}");
        jsonResponse(true, 'Usuario desactivado');
    }

    if ($action === 'guardar_modulos') {
        $userId  = (int)post('usuario_id');
        $modulos = $_POST['modulos'] ?? [];

        // Limpiar permisos existentes del usuario
        $db->delete('permisos_modulos', 'usuario_id=?', [$userId]);

        foreach ($modulos as $moduloId => $perms) {
            if (!empty($perms['ver'])) {
                $db->insert('permisos_modulos', [
                    'usuario_id'     => $userId,
                    'modulo_id'      => (int)$moduloId,
                    'puede_ver'      => 1,
                    'puede_crear'    => !empty($perms['crear']) ? 1 : 0,
                    'puede_editar'   => !empty($perms['editar']) ? 1 : 0,
                    'puede_eliminar' => !empty($perms['eliminar']) ? 1 : 0,
                ]);
            }
        }
        auditLog('permisos_modulos', 'admin', "Permisos actualizados para usuario #{$userId}");
        jsonResponse(true, 'Permisos guardados');
    }
}

// GET: listar usuarios
$usuarios = $db->fetchAll(
    "SELECT u.*, r.nombre AS rol_nombre, r.slug AS rol_slug
     FROM usuarios u JOIN roles r ON u.rol_id=r.id
     ORDER BY u.created_at DESC"
);
$roles   = $db->fetchAll("SELECT * FROM roles ORDER BY nivel DESC");
$modulos = $db->fetchAll("SELECT * FROM modulos ORDER BY orden");

$pageTitle    = 'Gestión de Usuarios';
$activeModule = 'dashboard';
include CORE_PATH . '/layout/header.php';
?>

<div class="container-fluid">
    <div class="tx-page-header">
        <div>
            <h1 class="tx-page-title"><i class="bi bi-people-fill me-2" style="color:var(--tx-primary)"></i>Gestión de Usuarios</h1>
            <div class="tx-breadcrumb"><a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a> › Usuarios</div>
        </div>
        <?php if (Session::hasRole('superadmin')): ?>
        <button class="btn-tx" onclick="abrirModal()">
            <i class="bi bi-person-plus-fill"></i> Nuevo Usuario
        </button>
        <?php endif; ?>
    </div>

    <?= getFlashAlert() ?>

    <div class="tx-card">
        <div class="table-responsive">
            <table id="tablaUsuarios" class="tx-table table mb-0">
                <thead>
                    <tr>
                        <th>#</th><th>Nombre</th><th>Email</th><th>Rol</th>
                        <th>Empresa</th><th>Último Login</th><th>Estado</th><th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="tx-avatar" style="background:var(--tx-primary);width:32px;height:32px;font-size:11px">
                                    <?= mb_substr($u['nombre'],0,1) . mb_substr($u['apellido'],0,1) ?>
                                </div>
                                <div>
                                    <div class="fw-600"><?= e($u['nombre'] . ' ' . $u['apellido']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?= e($u['email']) ?></td>
                        <td>
                            <span class="badge" style="background:<?= ['superadmin'=>'#dc3545','admin'=>'#F4A020','tecnico'=>'#0d6efd','cliente'=>'#198754'][$u['rol_slug']] ?? '#6c757d' ?>">
                                <?= e($u['rol_nombre']) ?>
                            </span>
                        </td>
                        <td class="text-muted"><?= e($u['empresa'] ?: '—') ?></td>
                        <td class="text-muted" style="font-size:12px"><?= $u['ultimo_login'] ? fdatetime($u['ultimo_login']) : '—' ?></td>
                        <td>
                            <?php if ($u['activo']): ?>
                            <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-tx-outline" style="padding:3px 8px"
                                        onclick="editarUsuario(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm" style="padding:3px 8px;background:var(--tx-bg);border:1px solid var(--tx-border)"
                                        onclick="gestionarModulos(<?= $u['id'] ?>, '<?= e($u['nombre']) ?>')">
                                    <i class="bi bi-grid"></i>
                                </button>
                                <?php if (Session::hasRole('superadmin') && $u['id'] != Session::userId()): ?>
                                <button class="btn btn-sm btn-outline-danger" style="padding:3px 8px"
                                        data-confirm="¿Desactivar al usuario <?= e($u['nombre']) ?>?"
                                        onclick="eliminarUsuario(<?= $u['id'] ?>)">
                                    <i class="bi bi-trash"></i>
                                </button>
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

<!-- Modal Usuario -->
<div class="modal fade" id="modalUsuario" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--tx-primary);color:#fff">
                <h5 class="modal-title" id="modalUsuarioTitulo">Nuevo Usuario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formUsuario">
                <div class="modal-body">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" id="usuarioAction" value="crear">
                    <input type="hidden" name="id" id="usuarioId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="tx-label">Nombre <span class="req">*</span></label>
                            <input type="text" name="nombre" id="fNombre" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="tx-label">Apellido <span class="req">*</span></label>
                            <input type="text" name="apellido" id="fApellido" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="tx-label">Email <span class="req">*</span></label>
                            <input type="email" name="email" id="fEmail" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="tx-label">Rol <span class="req">*</span></label>
                            <select name="rol_id" id="fRol" class="form-select">
                                <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= e($r['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="tx-label">Empresa</label>
                            <input type="text" name="empresa" id="fEmpresa" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="tx-label">Teléfono</label>
                            <input type="tel" name="telefono" id="fTelefono" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="tx-label">Contraseña <small class="text-muted">(mín. 6 chars)</small></label>
                            <input type="password" name="password" id="fPassword" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="tx-label">Estado</label>
                            <select name="activo" id="fActivo" class="form-select">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
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

<!-- Modal Módulos -->
<div class="modal fade" id="modalModulos" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--tx-secondary);color:#fff">
                <h5 class="modal-title" id="modalModulosTitulo">Acceso a Módulos</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formModulos">
                <div class="modal-body" id="bodyModulos">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="guardar_modulos">
                    <input type="hidden" name="usuario_id" id="modUsuarioId">
                    <?php foreach ($modulos as $m): ?>
                    <div class="p-3 mb-2 rounded" style="background:var(--tx-bg);border:1px solid var(--tx-border)">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="fw-600"><i class="bi <?= e($m['icono']) ?> me-2" style="color:var(--tx-primary)"></i><?= e($m['nombre']) ?></div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="modulos[<?= $m['id'] ?>][ver]" value="1" id="mod_<?= $m['id'] ?>">
                                <label class="form-check-label" for="mod_<?= $m['id'] ?>">Ver</label>
                            </div>
                        </div>
                        <div class="d-flex gap-3" style="font-size:12px">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="modulos[<?= $m['id'] ?>][crear]" value="1">
                                <label class="form-check-label">Crear</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="modulos[<?= $m['id'] ?>][editar]" value="1">
                                <label class="form-check-label">Editar</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="modulos[<?= $m['id'] ?>][eliminar]" value="1">
                                <label class="form-check-label">Eliminar</label>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-tx">Guardar Permisos</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraScripts = <<<JS
<script>
$(document).ready(function(){ txDataTable('#tablaUsuarios', { order:[[0,'desc']] }); });

function abrirModal() {
    document.getElementById('modalUsuarioTitulo').textContent = 'Nuevo Usuario';
    document.getElementById('usuarioAction').value = 'crear';
    document.getElementById('formUsuario').reset();
    new bootstrap.Modal(document.getElementById('modalUsuario')).show();
}

function editarUsuario(u) {
    document.getElementById('modalUsuarioTitulo').textContent = 'Editar Usuario';
    document.getElementById('usuarioAction').value = 'editar';
    document.getElementById('usuarioId').value = u.id;
    document.getElementById('fNombre').value = u.nombre;
    document.getElementById('fApellido').value = u.apellido;
    document.getElementById('fEmail').value = u.email;
    document.getElementById('fRol').value = u.rol_id;
    document.getElementById('fEmpresa').value = u.empresa || '';
    document.getElementById('fTelefono').value = u.telefono || '';
    document.getElementById('fActivo').value = u.activo;
    document.getElementById('fPassword').value = '';
    document.getElementById('fPassword').placeholder = 'Dejar vacío para no cambiar';
    new bootstrap.Modal(document.getElementById('modalUsuario')).show();
}

function eliminarUsuario(id) {
    TriggerX.confirm('Desactivar Usuario', '¿Seguro que deseas desactivar este usuario?', () => {
        TriggerX.post(TX.baseUrl + '/admin/users.php', { action:'eliminar', id }, () => {
            TriggerX.toast('Usuario desactivado', 'success');
            setTimeout(() => location.reload(), 1200);
        });
    });
}

function gestionarModulos(userId, nombre) {
    document.getElementById('modUsuarioId').value = userId;
    document.getElementById('modalModulosTitulo').textContent = 'Módulos — ' + nombre;
    // Cargar permisos actuales del usuario
    $.get(TX.baseUrl + '/admin/ajax/get_permisos.php', { usuario_id: userId }, function(res) {
        if (res.success) {
            res.data.forEach(p => {
                const modId = p.modulo_id;
                const ver = document.querySelector(`input[name="modulos[\${modId}][ver]"]`);
                const crear = document.querySelector(`input[name="modulos[\${modId}][crear]"]`);
                const editar = document.querySelector(`input[name="modulos[\${modId}][editar]"]`);
                const elim = document.querySelector(`input[name="modulos[\${modId}][eliminar]"]`);
                if (ver) ver.checked = p.puede_ver == 1;
                if (crear) crear.checked = p.puede_crear == 1;
                if (editar) editar.checked = p.puede_editar == 1;
                if (elim) elim.checked = p.puede_eliminar == 1;
            });
        }
        new bootstrap.Modal(document.getElementById('modalModulos')).show();
    });
}

document.getElementById('formUsuario').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    const data = Object.fromEntries(fd);
    TriggerX.post(TX.baseUrl + '/admin/users.php', data, () => {
        bootstrap.Modal.getInstance(document.getElementById('modalUsuario')).hide();
        TriggerX.toast('Usuario guardado correctamente', 'success');
        setTimeout(() => location.reload(), 1200);
    });
});

document.getElementById('formModulos').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    const data = {};
    fd.forEach((val, key) => {
        // Manejar checkboxes anidados
        const match = key.match(/modulos\[(\d+)\]\[(\w+)\]/);
        if (match) {
            if (!data.modulos) data.modulos = {};
            const [,mid,perm] = match;
            if (!data.modulos[mid]) data.modulos[mid] = {};
            data.modulos[mid][perm] = val;
        } else {
            data[key] = val;
        }
    });
    TriggerX.post(TX.baseUrl + '/admin/users.php', Object.fromEntries(fd), () => {
        bootstrap.Modal.getInstance(document.getElementById('modalModulos')).hide();
        TriggerX.toast('Permisos guardados', 'success');
    });
});
</script>
JS;
include CORE_PATH . '/layout/footer.php';
?>
