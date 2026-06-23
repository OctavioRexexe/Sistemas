<?php
define('TRIGGERX', true);
require_once __DIR__ . '/../core/config/config.php';
requireLogin();
securityHeaders();

$user = Session::getUser();
$db   = Database::getInstance();
$error = $success = '';

if (isPost()) {
    verifyCsrf();
    $action = post('action');

    if ($action === 'actualizar_perfil') {
        $nombre   = post('nombre');
        $apellido = post('apellido');
        $telefono = post('telefono');
        $empresa  = post('empresa');
        $dni      = post('dni');

        if (!notEmpty($nombre, $apellido)) {
            $error = 'Nombre y apellido son requeridos.';
        } else {
            $db->update('usuarios', [
                'nombre'   => $nombre,
                'apellido' => $apellido,
                'telefono' => $telefono,
                'empresa'  => $empresa,
                'dni'      => $dni ?: null,
            ], 'id=?', [$user['id']]);

            // Actualizar sesión
            $updated = $db->fetch(
                "SELECT u.*, r.slug AS rol_slug, r.nombre AS rol_nombre FROM usuarios u JOIN roles r ON u.rol_id=r.id WHERE u.id=?",
                [$user['id']]
            );
            Session::setUser($updated);
            $user = Session::getUser();
            auditLog('perfil_actualizar', 'perfil', 'Perfil actualizado');
            $success = 'Perfil actualizado correctamente.';
        }
    }

    if ($action === 'cambiar_password') {
        $actual  = $_POST['password_actual'] ?? '';
        $nueva   = $_POST['password_nueva']  ?? '';
        $confirma = $_POST['password_confirma'] ?? '';

        $userData = $db->fetch("SELECT password FROM usuarios WHERE id=?", [$user['id']]);

        if (!password_verify($actual, $userData['password'])) {
            $error = 'La contraseña actual es incorrecta.';
        } elseif (strlen($nueva) < 6) {
            $error = 'La nueva contraseña debe tener al menos 6 caracteres.';
        } elseif ($nueva !== $confirma) {
            $error = 'Las contraseñas nuevas no coinciden.';
        } else {
            $hash = password_hash($nueva, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            $db->update('usuarios', ['password' => $hash], 'id=?', [$user['id']]);
            auditLog('password_cambiar', 'perfil', 'Contraseña actualizada');
            $success = 'Contraseña actualizada correctamente.';
        }
    }

    if ($action === 'eliminar_firma') {
        $db->update('usuarios', ['firma_archivo' => null], 'id=?', [$user['id']]);
        auditLog('perfil_eliminar_firma', 'perfil', 'Firma de perfil eliminada');
        $success = 'Firma eliminada correctamente.';
    }
}

// Datos completos del usuario (incluye dni y firma_archivo, que no viven en la sesión)
$userFull = $db->fetch("SELECT * FROM usuarios WHERE id = ?", [$user['id']]);

// Estadísticas del usuario (para técnicos)
$misTickets = 0;
$misResueltos = 0;
if (Session::hasRole('tecnico')) {
    $misTickets   = (int)$db->fetchColumn("SELECT COUNT(*) FROM tickets WHERE tecnico_id=?", [$user['id']]);
    $misResueltos = (int)$db->fetchColumn(
        "SELECT COUNT(*) FROM tickets t JOIN estados_ticket e ON t.estado_id=e.id WHERE t.tecnico_id=? AND e.es_final=1",
        [$user['id']]
    );
}

$pageTitle    = 'Mi Perfil';
$activeModule = 'dashboard';
include CORE_PATH . '/layout/header.php';
?>
<div class="container-fluid" style="max-width:780px">
    <div class="tx-page-header">
        <div>
            <h1 class="tx-page-title"><i class="bi bi-person-circle me-2" style="color:var(--tx-primary)"></i>Mi Perfil</h1>
            <div class="tx-breadcrumb"><a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a> › Perfil</div>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Info card -->
        <div class="col-md-4">
            <div class="tx-card text-center p-4">
                <div class="tx-avatar mx-auto mb-3"
                     style="width:72px;height:72px;font-size:26px;background:var(--tx-primary)">
                    <?= mb_substr($user['nombre'],0,1) . mb_substr($user['apellido'],0,1) ?>
                </div>
                <div class="fw-700 fs-5"><?= e($user['nombre'] . ' ' . $user['apellido']) ?></div>
                <div class="text-muted mb-2"><?= e($user['email']) ?></div>
                <span class="badge" style="background:var(--tx-primary)"><?= e($user['rol_nombre']) ?></span>
                <?php if ($user['empresa']): ?>
                <div class="mt-2 text-muted" style="font-size:13px"><i class="bi bi-building me-1"></i><?= e($user['empresa']) ?></div>
                <?php endif; ?>

                <?php if (Session::hasRole('tecnico')): ?>
                <hr class="my-3">
                <div class="row g-2 text-center">
                    <div class="col-6">
                        <div class="fw-800 fs-4" style="color:var(--tx-primary)"><?= $misTickets ?></div>
                        <div style="font-size:11px;color:var(--tx-muted)">Tickets asignados</div>
                    </div>
                    <div class="col-6">
                        <div class="fw-800 fs-4 text-success"><?= $misResueltos ?></div>
                        <div style="font-size:11px;color:var(--tx-muted)">Resueltos</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Formularios -->
        <div class="col-md-8">
            <!-- Datos personales -->
            <div class="tx-card mb-4">
                <div class="tx-card-header">
                    <h6 class="tx-card-title"><i class="bi bi-person-fill"></i>Datos Personales</h6>
                </div>
                <div class="tx-card-body">
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="actualizar_perfil">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="tx-label">Nombre <span class="req">*</span></label>
                                <input type="text" name="nombre" class="form-control" value="<?= e($user['nombre']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="tx-label">Apellido <span class="req">*</span></label>
                                <input type="text" name="apellido" class="form-control" value="<?= e($user['apellido']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="tx-label">Teléfono</label>
                                <input type="tel" name="telefono" class="form-control" value="<?= e($user['telefono'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="tx-label">DNI</label>
                                <input type="text" name="dni" class="form-control" maxlength="15" value="<?= e($userFull['dni'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="tx-label">Empresa</label>
                                <input type="text" name="empresa" class="form-control" value="<?= e($user['empresa'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <button type="submit" class="btn-tx">
                                <i class="bi bi-save me-1"></i>Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Cambiar contraseña -->
            <div class="tx-card">
                <div class="tx-card-header">
                    <h6 class="tx-card-title"><i class="bi bi-lock-fill"></i>Cambiar Contraseña</h6>
                </div>
                <div class="tx-card-body">
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="cambiar_password">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="tx-label">Contraseña Actual <span class="req">*</span></label>
                                <input type="password" name="password_actual" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="tx-label">Nueva Contraseña <span class="req">*</span></label>
                                <input type="password" name="password_nueva" class="form-control" minlength="6" required>
                            </div>
                            <div class="col-md-6">
                                <label class="tx-label">Confirmar Nueva Contraseña <span class="req">*</span></label>
                                <input type="password" name="password_confirma" class="form-control" required>
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <button type="submit" class="btn-tx">
                                <i class="bi bi-shield-lock me-1"></i>Actualizar Contraseña
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (Session::hasRole('tecnico')): ?>
            <!-- Firma guardada para Constancias Técnicas -->
            <div class="tx-card mt-4">
                <div class="tx-card-header">
                    <h6 class="tx-card-title"><i class="bi bi-pen-fill"></i>Mi Firma (Constancias Técnicas)</h6>
                </div>
                <div class="tx-card-body">
                    <p class="text-muted" style="font-size:13px">
                        Esta firma se usará automáticamente al crear constancias técnicas, sin necesidad de firmar cada vez.
                    </p>
                    <?php if (!empty($userFull['firma_archivo'])): ?>
                    <div class="mb-3">
                        <img src="<?= UPLOADS_URL ?>/<?= e($userFull['firma_archivo']) ?>"
                             style="max-height:100px;border:1px solid var(--tx-border);border-radius:8px;background:#fff">
                    </div>
                    <form method="POST" onsubmit="return confirm('¿Eliminar tu firma guardada?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="eliminar_firma">
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash me-1"></i>Eliminar Firma
                        </button>
                    </form>
                    <?php else: ?>
                    <canvas id="firmaPerfilCanvas" class="tx-signpad" style="border:2px dashed var(--tx-border);border-radius:10px;background:#fff;cursor:crosshair;touch-action:none;width:100%;height:140px"></canvas>
                    <div class="d-flex gap-2 mt-2">
                        <button type="button" class="btn btn-sm" id="btnLimpiarFirmaPerfil" style="background:var(--tx-bg);border:1px solid var(--tx-border)">
                            <i class="bi bi-eraser me-1"></i>Limpiar
                        </button>
                        <button type="button" class="btn-tx btn-sm" id="btnGuardarFirmaPerfil">
                            <i class="bi bi-save me-1"></i>Guardar Firma
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
<?php
$extraScripts = <<<JS
<script>
(function() {
    const canvas = document.getElementById('firmaPerfilCanvas');
    if (!canvas) return;

    canvas.width = canvas.offsetWidth;
    canvas.height = canvas.offsetHeight;
    const ctx = canvas.getContext('2d');
    ctx.strokeStyle = '#1a1a2e';
    ctx.lineWidth = 2.2;
    ctx.lineCap = 'round';
    let dibujando = false;

    const pos = (e) => {
        const rect = canvas.getBoundingClientRect();
        const t = e.touches ? e.touches[0] : e;
        return { x: t.clientX - rect.left, y: t.clientY - rect.top };
    };
    const start = (e) => { dibujando = true; const p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); };
    const move = (e) => { if (!dibujando) return; const p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); };
    const end = () => { dibujando = false; };

    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    canvas.addEventListener('mouseup', end);
    canvas.addEventListener('touchstart', start);
    canvas.addEventListener('touchmove', move);
    canvas.addEventListener('touchend', end);

    document.getElementById('btnLimpiarFirmaPerfil')?.addEventListener('click', () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    });

    document.getElementById('btnGuardarFirmaPerfil')?.addEventListener('click', () => {
        const blank = document.createElement('canvas');
        blank.width = canvas.width; blank.height = canvas.height;
        if (canvas.toDataURL() === blank.toDataURL()) {
            TriggerX.toast('Dibuja tu firma primero', 'warning'); return;
        }
        const dataUrl = canvas.toDataURL('image/png');
        TriggerX.post(TX.baseUrl + '/modules/tickets/ajax/constancias.php', {
            action: 'subir_firma', id: '0', tipo: 'tecnico',
            imagen_base64: dataUrl, guardar_perfil: '1', solo_perfil: '1',
        }, () => {
            TriggerX.toast('Firma guardada en tu perfil', 'success');
            setTimeout(() => location.reload(), 1000);
        });
    });
})();
</script>
JS;
include CORE_PATH . '/layout/footer.php';
?>
