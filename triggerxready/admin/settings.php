<?php
define('TRIGGERX', true);
require_once __DIR__ . '/../core/config/config.php';
requireLogin();
requireRole('admin');
securityHeaders();
Config::load();

$db = Database::getInstance();

if (isPost()) {
    verifyCsrf();
    $action = post('action');

    if ($action === 'guardar_config') {
        $campos = [
            'empresa_nombre','empresa_slogan','empresa_email','empresa_telefono','empresa_web',
            'color_primario','color_secundario','color_navbar',
            'sla_dias_maximos','sla_alerta_preventiva','sla_alerta_importante','sla_alerta_critica',
            'smtp_host','smtp_port','smtp_usuario','smtp_cifrado','smtp_from_name','smtp_activo',
            'ticket_prefijo','ticket_auto_asignar','ticket_cerrar_dias',
            'constancia_horas_edicion','constancia_max_ediciones','constancia_prefijo',
            'modo_mantenimiento',
        ];

        foreach ($campos as $campo) {
            if (isset($_POST[$campo])) {
                Config::set($campo, post($campo));
            }
        }

        // Logo upload
        if (!empty($_FILES['empresa_logo']['name'])) {
            $up = processUpload($_FILES['empresa_logo'], 'logos');
            if ($up['success']) {
                Config::set('empresa_logo', $up['nombre_archivo']);
            }
        }

        // Contraseña SMTP separada (no vaciarla si está vacía)
        if (!empty(post('smtp_password'))) {
            Config::set('smtp_password', post('smtp_password'));
        }

        auditLog('configuracion_guardar', 'admin', 'Configuración del sistema actualizada');
        redirectWith(BASE_URL . '/admin/settings.php', 'success', 'Configuración guardada correctamente');
    }
}

// Cargar todos los valores actuales
$cfg = $db->fetchAll("SELECT clave, valor FROM configuracion");
$vals = [];
foreach ($cfg as $c) { $vals[$c['clave']] = $c['valor']; }

$pageTitle    = 'Configuración del Sistema';
$activeModule = 'dashboard';
include CORE_PATH . '/layout/header.php';
?>

<div class="container-fluid" style="max-width:900px">
    <div class="tx-page-header">
        <div>
            <h1 class="tx-page-title"><i class="bi bi-gear-fill me-2" style="color:var(--tx-primary)"></i>Configuración del Sistema</h1>
            <div class="tx-breadcrumb"><a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a> › Configuración</div>
        </div>
    </div>

    <?= getFlashAlert() ?>

    <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="guardar_config">

        <!-- Tabs -->
        <ul class="nav nav-pills mb-4" id="configTabs">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="pill" href="#tabGeneral"><i class="bi bi-building me-1"></i>General</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="pill" href="#tabApariencia"><i class="bi bi-palette me-1"></i>Apariencia</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="pill" href="#tabSLA"><i class="bi bi-clock me-1"></i>SLA</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="pill" href="#tabSMTP"><i class="bi bi-envelope me-1"></i>SMTP</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="pill" href="#tabTickets"><i class="bi bi-ticket me-1"></i>Tickets</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="pill" href="#tabConstancias"><i class="bi bi-file-earmark-text me-1"></i>Constancias</a></li>
        </ul>

        <div class="tab-content">

            <!-- GENERAL -->
            <div class="tab-pane fade show active" id="tabGeneral">
                <div class="tx-card mb-3">
                    <div class="tx-card-header"><h6 class="tx-card-title"><i class="bi bi-building"></i>Información de la Empresa</h6></div>
                    <div class="tx-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="tx-label">Nombre de la Empresa</label>
                                <input type="text" name="empresa_nombre" class="form-control" value="<?= e($vals['empresa_nombre'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="tx-label">Slogan</label>
                                <input type="text" name="empresa_slogan" class="form-control" value="<?= e($vals['empresa_slogan'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="tx-label">Email de Soporte</label>
                                <input type="email" name="empresa_email" class="form-control" value="<?= e($vals['empresa_email'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="tx-label">Teléfono</label>
                                <input type="text" name="empresa_telefono" class="form-control" value="<?= e($vals['empresa_telefono'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="tx-label">Sitio Web</label>
                                <input type="url" name="empresa_web" class="form-control" value="<?= e($vals['empresa_web'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="tx-label">Logo de la Empresa <small class="text-muted">(PNG/JPG recomendado)</small></label>
                                <input type="file" name="empresa_logo" class="form-control" accept=".png,.jpg,.jpeg,.webp">
                                <?php if (!empty($vals['empresa_logo'])): ?>
                                <small class="text-muted">Logo actual: <?= e($vals['empresa_logo']) ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="tx-label">Modo Mantenimiento</label>
                                <select name="modo_mantenimiento" class="form-select">
                                    <option value="0" <?= ($vals['modo_mantenimiento']??'0') == '0' ? 'selected' : '' ?>>Desactivado (Normal)</option>
                                    <option value="1" <?= ($vals['modo_mantenimiento']??'0') == '1' ? 'selected' : '' ?>>Activado (Solo admins)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- APARIENCIA -->
            <div class="tab-pane fade" id="tabApariencia">
                <div class="tx-card mb-3">
                    <div class="tx-card-header"><h6 class="tx-card-title"><i class="bi bi-palette"></i>Colores del Sistema</h6></div>
                    <div class="tx-card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="tx-label">Color Principal</label>
                                <div class="d-flex gap-2 align-items-center">
                                    <input type="color" name="color_primario" class="form-control form-control-color" value="<?= e($vals['color_primario'] ?? '#F4A020') ?>" style="width:60px;height:40px">
                                    <input type="text" id="txtColorPrimario" class="form-control" value="<?= e($vals['color_primario'] ?? '#F4A020') ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="tx-label">Color Navbar</label>
                                <div class="d-flex gap-2 align-items-center">
                                    <input type="color" name="color_navbar" class="form-control form-control-color" value="<?= e($vals['color_navbar'] ?? '#F4A020') ?>" style="width:60px;height:40px">
                                    <input type="text" id="txtColorNavbar" class="form-control" value="<?= e($vals['color_navbar'] ?? '#F4A020') ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="tx-label">Color Secundario</label>
                                <div class="d-flex gap-2 align-items-center">
                                    <input type="color" name="color_secundario" class="form-control form-control-color" value="<?= e($vals['color_secundario'] ?? '#1a1a2e') ?>" style="width:60px;height:40px">
                                    <input type="text" id="txtColorSec" class="form-control" value="<?= e($vals['color_secundario'] ?? '#1a1a2e') ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SLA -->
            <div class="tab-pane fade" id="tabSLA">
                <div class="tx-card mb-3">
                    <div class="tx-card-header"><h6 class="tx-card-title"><i class="bi bi-clock-history"></i>Configuración SLA</h6></div>
                    <div class="tx-card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="tx-label">Máximo días ticket abierto</label>
                                <input type="number" name="sla_dias_maximos" class="form-control" min="1" max="90" value="<?= e($vals['sla_dias_maximos'] ?? '15') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="tx-label">Alerta preventiva (días)</label>
                                <input type="number" name="sla_alerta_preventiva" class="form-control" min="1" value="<?= e($vals['sla_alerta_preventiva'] ?? '5') ?>">
                                <small class="text-success">● Verde → Amarillo</small>
                            </div>
                            <div class="col-md-3">
                                <label class="tx-label">Alerta importante (días)</label>
                                <input type="number" name="sla_alerta_importante" class="form-control" min="1" value="<?= e($vals['sla_alerta_importante'] ?? '10') ?>">
                                <small class="text-warning">● Amarillo → Naranja</small>
                            </div>
                            <div class="col-md-3">
                                <label class="tx-label">Alerta crítica (días)</label>
                                <input type="number" name="sla_alerta_critica" class="form-control" min="1" value="<?= e($vals['sla_alerta_critica'] ?? '15') ?>">
                                <small class="text-danger">● Naranja → Rojo</small>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3 mb-0" style="font-size:13px">
                            <i class="bi bi-info-circle me-2"></i>
                            Configura el Cron Job en cPanel:
                            <code>0 8 * * * php <?= ROOT_PATH ?>/cron/sla_check.php</code>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SMTP -->
            <div class="tab-pane fade" id="tabSMTP">
                <div class="tx-card mb-3">
                    <div class="tx-card-header"><h6 class="tx-card-title"><i class="bi bi-envelope-fill"></i>Configuración de Correo</h6></div>
                    <div class="tx-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="tx-label">Usar SMTP</label>
                                <select name="smtp_activo" class="form-select">
                                    <option value="0" <?= ($vals['smtp_activo']??'0')=='0'?'selected':'' ?>>No (usar mail() de PHP)</option>
                                    <option value="1" <?= ($vals['smtp_activo']??'0')=='1'?'selected':'' ?>>Sí (SMTP personalizado)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="tx-label">Servidor SMTP</label>
                                <input type="text" name="smtp_host" class="form-control" placeholder="smtp.gmail.com" value="<?= e($vals['smtp_host']??'') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="tx-label">Puerto</label>
                                <input type="number" name="smtp_port" class="form-control" value="<?= e($vals['smtp_port']??'587') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="tx-label">Cifrado</label>
                                <select name="smtp_cifrado" class="form-select">
                                    <option value="tls" <?= ($vals['smtp_cifrado']??'tls')==='tls'?'selected':'' ?>>TLS</option>
                                    <option value="ssl" <?= ($vals['smtp_cifrado']??'')==='ssl'?'selected':'' ?>>SSL</option>
                                    <option value="" <?= empty($vals['smtp_cifrado'])?'selected':'' ?>>Ninguno</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="tx-label">Usuario SMTP</label>
                                <input type="text" name="smtp_usuario" class="form-control" value="<?= e($vals['smtp_usuario']??'') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="tx-label">Contraseña SMTP <small class="text-muted">(vacío = no cambiar)</small></label>
                                <input type="password" name="smtp_password" class="form-control" placeholder="••••••••">
                            </div>
                            <div class="col-md-6">
                                <label class="tx-label">Nombre del Remitente</label>
                                <input type="text" name="smtp_from_name" class="form-control" value="<?= e($vals['smtp_from_name']??'') ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TICKETS -->
            <div class="tab-pane fade" id="tabTickets">
                <div class="tx-card mb-3">
                    <div class="tx-card-header"><h6 class="tx-card-title"><i class="bi bi-ticket"></i>Opciones de Tickets</h6></div>
                    <div class="tx-card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="tx-label">Prefijo de Tickets</label>
                                <input type="text" name="ticket_prefijo" class="form-control" maxlength="10" value="<?= e($vals['ticket_prefijo']??'TRG') ?>">
                                <small class="text-muted">Ej: TRG → TRG-000001</small>
                            </div>
                            <div class="col-md-4">
                                <label class="tx-label">Auto-asignar Tickets</label>
                                <select name="ticket_auto_asignar" class="form-select">
                                    <option value="0" <?= ($vals['ticket_auto_asignar']??'0')=='0'?'selected':'' ?>>No (manual)</option>
                                    <option value="1" <?= ($vals['ticket_auto_asignar']??'0')=='1'?'selected':'' ?>>Sí (balanceo carga)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="tx-label">Días para cierre auto (resueltos)</label>
                                <input type="number" name="ticket_cerrar_dias" class="form-control" min="0" value="<?= e($vals['ticket_cerrar_dias']??'3') ?>">
                                <small class="text-muted">0 = no cerrar automáticamente</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CONSTANCIAS TÉCNICAS -->
            <div class="tab-pane fade" id="tabConstancias">
                <div class="tx-card mb-3">
                    <div class="tx-card-header"><h6 class="tx-card-title"><i class="bi bi-file-earmark-text"></i>Constancias Técnicas</h6></div>
                    <div class="tx-card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="tx-label">Prefijo de Constancia</label>
                                <input type="text" name="constancia_prefijo" class="form-control" maxlength="10" value="<?= e($vals['constancia_prefijo']??'CT') ?>">
                                <small class="text-muted">Ej: CT → CT-000001</small>
                            </div>
                            <div class="col-md-4">
                                <label class="tx-label">Horas límite para editar borrador</label>
                                <input type="number" name="constancia_horas_edicion" class="form-control" min="1" value="<?= e($vals['constancia_horas_edicion']??'24') ?>">
                                <small class="text-muted">Tiempo máximo desde la creación para que un técnico siga editando</small>
                            </div>
                            <div class="col-md-4">
                                <label class="tx-label">Máximo de ediciones del borrador</label>
                                <input type="number" name="constancia_max_ediciones" class="form-control" min="1" value="<?= e($vals['constancia_max_ediciones']??'5') ?>">
                                <small class="text-muted">Número de veces que un técnico puede guardar el borrador</small>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3 mb-0" style="font-size:13px">
                            <i class="bi bi-info-circle me-2"></i>
                            Estos límites aplican solo a técnicos. Los administradores pueden editar o eliminar
                            cualquier borrador sin restricción.
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="d-flex justify-content-end mt-3">
            <button type="submit" class="btn-tx btn-lg">
                <i class="bi bi-save-fill me-2"></i>Guardar Configuración
            </button>
        </div>
    </form>
</div>

<?php
$extraScripts = '<script>
// Sincronizar inputs color con texto
document.querySelectorAll("input[type=color]").forEach(inp => {
    inp.addEventListener("input", function(){
        const txt = this.nextElementSibling;
        if (txt) txt.value = this.value;
    });
});
</script>';
include CORE_PATH . '/layout/footer.php';
?>
