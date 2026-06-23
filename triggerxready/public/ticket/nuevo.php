<?php
/**
 * TRIGGERX — Formulario Público de Nuevo Ticket
 * Corregido: guardado, uploads, serie_equipo + modelo_equipo separados
 */
define('TRIGGERX', true);
require_once __DIR__ . '/../../core/config/config.php';
securityHeaders();
try { Config::load(); } catch (Throwable $e) {}

$success = false;
$codigo  = '';
$errores = [];

// ============================================================
// PROCESAR FORMULARIO
// ============================================================
if (isPost()) {
    verifyCsrf();

    $nombre   = post('nombre');
    $empresa  = post('empresa');
    $telefono = post('telefono');
    $email    = post('email');
    $serie    = post('serie_equipo');
    $modelo   = post('modelo_equipo');
    $catId    = (int)post('categoria_id');
    $asunto   = post('asunto');
    $desc     = trim($_POST['descripcion'] ?? '');

    // Validaciones
    if (!notEmpty($nombre))  $errores[] = 'El nombre es obligatorio.';
    if (!notEmpty($email))   $errores[] = 'El correo es obligatorio.';
    if (!isValidEmail($email)) $errores[] = 'El correo electrónico no es válido.';
    if (!notEmpty($asunto))  $errores[] = 'El asunto es obligatorio.';
    if (!notEmpty($desc))    $errores[] = 'La descripción es obligatoria.';

    if (empty($errores)) {
        try {
            $db = Database::getInstance();
            $db->beginTransaction();

            // Generar código único
            $codigo = generarCodigoTicket();

            // Obtener prioridad media
            $prioridad = $db->fetch("SELECT * FROM prioridades_ticket WHERE slug = 'media'");
            if (!$prioridad) {
                $prioridad = ['id' => 2, 'sla_horas' => 72];
            }
            $slaLimite = date('Y-m-d H:i:s', strtotime('+' . (int)$prioridad['sla_horas'] . ' hours'));

            // Insertar ticket
            $ticketId = $db->insert('tickets', [
                'codigo'           => $codigo,
                'estado_id'        => 1,
                'prioridad_id'     => (int)$prioridad['id'],
                'categoria_id'     => $catId ?: null,
                'cliente_nombre'   => $nombre,
                'cliente_empresa'  => $empresa,
                'cliente_telefono' => $telefono,
                'cliente_email'    => $email,
                'asunto'           => $asunto,
                'descripcion'      => $desc,
                'serie_equipo'     => $serie,
                'modelo_equipo'    => $modelo,
                'sla_limite'       => $slaLimite,
                'sla_estado'       => 'verde',
                'sla_vencido'      => 0,
                'origen'           => 'publico',
            ]);

            if (!$ticketId) {
                throw new RuntimeException('No se pudo insertar el ticket en la base de datos.');
            }

            // ============================================================
            // PROCESAR ADJUNTOS
            // ============================================================
            if (!empty($_FILES['adjuntos']['name'][0])) {
                $totalFiles = count($_FILES['adjuntos']['name']);
                for ($i = 0; $i < $totalFiles; $i++) {
                    // Saltar si no se subió
                    if ($_FILES['adjuntos']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    if (empty($_FILES['adjuntos']['name'][$i])) continue;

                    $fileData = [
                        'name'     => $_FILES['adjuntos']['name'][$i],
                        'type'     => $_FILES['adjuntos']['type'][$i],
                        'tmp_name' => $_FILES['adjuntos']['tmp_name'][$i],
                        'error'    => $_FILES['adjuntos']['error'][$i],
                        'size'     => $_FILES['adjuntos']['size'][$i],
                    ];

                    $up = processUpload($fileData, 'tickets');
                    if ($up['success']) {
                        $db->insert('adjuntos_ticket', [
                            'ticket_id'       => $ticketId,
                            'nombre_original' => $up['nombre_original'],
                            'nombre_archivo'  => $up['nombre_archivo'],
                            'ruta'            => $up['ruta'],
                            'mime_type'       => $up['mime_type'],
                            'tamano'          => $up['tamano'],
                            'tipo'            => 'adjunto',
                        ]);
                    }
                    // Si falla el upload del archivo no cancelamos el ticket
                }
            }

            // Historial
            $db->insert('historial_ticket', [
                'ticket_id' => $ticketId,
                'accion'    => 'creado',
                'detalle'   => "Ticket creado públicamente por: {$email}",
                'ip'        => getUserIp(),
            ]);

            // Notificar admins
            try {
                $admins = $db->fetchAll(
                    "SELECT u.id FROM usuarios u JOIN roles r ON u.rol_id=r.id WHERE r.nivel>=3 AND u.activo=1"
                );
                foreach ($admins as $a) {
                    crearNotificacion(
                        $a['id'],
                        'ticket_nuevo',
                        "Nuevo ticket público {$codigo}",
                        "De: {$nombre} — {$asunto}",
                        BASE_URL . "/modules/tickets/detalle.php?id={$ticketId}"
                    );
                }
            } catch (Throwable $ne) {
                // No cancelar por error de notificación
                error_log('Notif error: ' . $ne->getMessage());
            }

            $db->commit();
            $success = true;

        } catch (Throwable $e) {
            try { $db->rollback(); } catch (Throwable $re) {}
            error_log('Ticket nuevo.php error: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());

            if (DEBUG_MODE) {
                $errores[] = 'Error técnico: ' . $e->getMessage() . ' (línea ' . $e->getLine() . ')';
            } else {
                $errores[] = 'Error al guardar el ticket. Por favor intenta nuevamente.';
            }
        }
    }
}

// Cargar categorías
$categorias = [];
try {
    $db = Database::getInstance();
    $categorias = $db->fetchAll("SELECT id, nombre FROM categorias_ticket WHERE activo=1 ORDER BY nombre");
} catch (Throwable $e) {}

$empresa_cfg   = Config::get('empresa_nombre', APP_NAME);
$colorPrimario = Config::get('color_primario', '#F4A020');
$logo          = Config::get('empresa_logo', '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abrir Ticket — <?= e($empresa_cfg) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= ASSETS_URL ?>/css/triggerx.css" rel="stylesheet">
    <style>
        :root {
            --tx-primary:       <?= e($colorPrimario) ?>;
            --tx-primary-dark:  color-mix(in srgb, <?= e($colorPrimario) ?> 80%, black);
            --tx-primary-light: color-mix(in srgb, <?= e($colorPrimario) ?> 15%, white);
        }
        body { padding-top: 0 !important; background: #f4f5f7; }

        /* Upload zone mejorado */
        .upload-zone {
            border: 2px dashed #ced4da;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all .2s;
            background: #f8f9fa;
            position: relative;
        }
        .upload-zone:hover { border-color: var(--tx-primary); background: var(--tx-primary-light); }
        .upload-zone.has-files { border-color: #198754; background: #f0fdf4; }
        .upload-zone input[type=file] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }
        .file-list { margin-top: 10px; text-align: left; }
        .file-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 4px;
            font-size: 12.5px;
        }
        .file-item .remove-file {
            margin-left: auto;
            color: #dc3545;
            cursor: pointer;
            font-size: 14px;
            border: none;
            background: none;
            padding: 0 2px;
        }
        .req { color: #dc3545; }
    </style>
</head>
<body>

<!-- Topbar -->
<div style="background:var(--tx-primary);padding:13px 20px;text-align:center">
    <a href="<?= BASE_URL ?>/login.php"
       style="color:#fff;text-decoration:none;font-weight:800;font-size:17px">
        <?php if ($logo): ?>
        <img src="<?= ASSETS_URL ?>/img/<?= e($logo) ?>" height="32" style="vertical-align:middle;margin-right:8px">
        <?php else: ?>
        <i class="bi bi-lightning-charge-fill me-2"></i>
        <?php endif; ?>
        <?= e($empresa_cfg) ?>
    </a>
</div>

<div class="container py-4" style="max-width:720px">

<?php if ($success): ?>
<!-- ============================================================
     PANTALLA ÉXITO
     ============================================================ -->
<div class="card border-0 shadow-sm rounded-4 text-center p-5">
    <div style="width:80px;height:80px;background:#dcfce7;border-radius:50%;
                display:grid;place-items:center;margin:0 auto 20px;
                font-size:36px;color:#16a34a">
        <i class="bi bi-check-circle-fill"></i>
    </div>
    <h3 class="fw-800 mb-2">¡Ticket Registrado!</h3>
    <p class="text-muted mb-4">Tu solicitud fue recibida. Nos pondremos en contacto contigo a la brevedad.</p>

    <div class="p-4 rounded-3 mb-4 mx-auto" style="max-width:320px;
         background:var(--tx-primary-light);border:2px solid var(--tx-primary)">
        <div class="text-muted mb-1" style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px">
            Tu número de ticket
        </div>
        <div class="fw-900" style="font-size:32px;color:var(--tx-primary);font-family:monospace;letter-spacing:2px">
            <?= e($codigo) ?>
        </div>
        <div class="text-muted mt-1" style="font-size:11px">
            Guarda este número — lo necesitarás para consultar el estado
        </div>
    </div>

    <div class="d-flex gap-2 justify-content-center flex-wrap">
        <a href="<?= BASE_URL ?>/public/ticket/consultar.php?codigo=<?= urlencode($codigo) ?>"
           class="btn-tx">
            <i class="bi bi-search me-1"></i>Ver estado de mi ticket
        </a>
        <a href="<?= BASE_URL ?>/public/ticket/nuevo.php"
           class="btn btn-outline-secondary">
            <i class="bi bi-plus-circle me-1"></i>Abrir otro ticket
        </a>
    </div>
</div>

<?php else: ?>
<!-- ============================================================
     FORMULARIO
     ============================================================ -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden">

    <!-- Header -->
    <div style="background:var(--tx-primary);padding:24px 32px;color:#fff;text-align:center">
        <i class="bi bi-headset" style="font-size:36px;display:block;margin-bottom:10px;opacity:.9"></i>
        <h4 class="fw-800 mb-1">Abrir Ticket de Soporte</h4>
        <p class="mb-0" style="font-size:13px;opacity:.85">Completa el formulario y te atenderemos a la brevedad</p>
    </div>

    <div class="p-4">

        <!-- Errores -->
        <?php if (!empty($errores)): ?>
        <div class="alert alert-danger alert-dismissible fade show" style="font-size:13px">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Por favor corrige los siguientes errores:</strong>
            <ul class="mb-0 mt-1 ps-3">
                <?php foreach ($errores as $err): ?>
                <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <form method="POST"
              action="<?= BASE_URL ?>/public/ticket/nuevo.php"
              enctype="multipart/form-data"
              id="formTicket"
              novalidate>

            <?= csrfField() ?>

            <!-- ---- DATOS PERSONALES ---- -->
            <h6 class="fw-700 mb-3 pb-2 border-bottom"
                style="color:var(--tx-primary);font-size:13px;text-transform:uppercase;letter-spacing:.5px">
                <i class="bi bi-person-fill me-2"></i>Datos de Contacto
            </h6>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-600" style="font-size:13px">
                        Nombre Completo <span class="req">*</span>
                    </label>
                    <input type="text" name="nombre" class="form-control"
                           placeholder="Juan Pérez"
                           value="<?= e($_POST['nombre'] ?? '') ?>"
                           required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-600" style="font-size:13px">Empresa / Organización</label>
                    <input type="text" name="empresa" class="form-control"
                           placeholder="Mi Empresa S.A."
                           value="<?= e($_POST['empresa'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-600" style="font-size:13px">
                        Correo Electrónico <span class="req">*</span>
                    </label>
                    <input type="email" name="email" class="form-control"
                           placeholder="correo@empresa.com"
                           value="<?= e($_POST['email'] ?? '') ?>"
                           required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-600" style="font-size:13px">Teléfono / Celular</label>
                    <input type="tel" name="telefono" class="form-control"
                           placeholder="+51 999 999 999"
                           value="<?= e($_POST['telefono'] ?? '') ?>">
                </div>
            </div>

            <!-- ---- DATOS DEL EQUIPO ---- -->
            <h6 class="fw-700 mb-3 pb-2 border-bottom"
                style="color:var(--tx-primary);font-size:13px;text-transform:uppercase;letter-spacing:.5px">
                <i class="bi bi-pc-display me-2"></i>Datos del Equipo
            </h6>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-600" style="font-size:13px">
                        Serie del Equipo
                    </label>
                    <input type="text" name="serie_equipo" class="form-control"
                           placeholder="Ej: SN123456789"
                           value="<?= e($_POST['serie_equipo'] ?? '') ?>">
                    <div class="form-text">Número de serie en la etiqueta del equipo</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-600" style="font-size:13px">
                        Modelo del Equipo
                    </label>
                    <input type="text" name="modelo_equipo" class="form-control"
                           placeholder="Ej: HP LaserJet Pro M404n"
                           value="<?= e($_POST['modelo_equipo'] ?? '') ?>">
                    <div class="form-text">Marca y modelo del equipo afectado</div>
                </div>
            </div>

            <!-- ---- DETALLE DEL PROBLEMA ---- -->
            <h6 class="fw-700 mb-3 pb-2 border-bottom"
                style="color:var(--tx-primary);font-size:13px;text-transform:uppercase;letter-spacing:.5px">
                <i class="bi bi-exclamation-circle-fill me-2"></i>Detalle del Problema
            </h6>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-600" style="font-size:13px">Categoría</label>
                    <select name="categoria_id" class="form-select">
                        <option value="">— Seleccionar categoría —</option>
                        <?php foreach ($categorias as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"
                            <?= (isset($_POST['categoria_id']) && $_POST['categoria_id'] == $c['id']) ? 'selected' : '' ?>>
                            <?= e($c['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-600" style="font-size:13px">
                        Asunto <span class="req">*</span>
                    </label>
                    <input type="text" name="asunto" class="form-control"
                           placeholder="Ej: Impresora no enciende"
                           value="<?= e($_POST['asunto'] ?? '') ?>"
                           required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-600" style="font-size:13px">
                        Descripción Detallada <span class="req">*</span>
                    </label>
                    <textarea name="descripcion" class="form-control" rows="5" required
                              placeholder="Describe el problema con el mayor detalle posible: qué ocurrió, cuándo comenzó, qué intentaste hacer para solucionarlo..."><?= e($_POST['descripcion'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- ---- ADJUNTOS ---- -->
            <h6 class="fw-700 mb-3 pb-2 border-bottom"
                style="color:var(--tx-primary);font-size:13px;text-transform:uppercase;letter-spacing:.5px">
                <i class="bi bi-paperclip me-2"></i>Archivos Adjuntos
                <span class="text-muted fw-400" style="text-transform:none;letter-spacing:0">
                    (opcional — JPG, PNG, PDF · máx. 10MB c/u)
                </span>
            </h6>

            <div class="mb-4">
                <div class="upload-zone" id="uploadZone">
                    <input type="file"
                           name="adjuntos[]"
                           id="fileInput"
                           multiple
                           accept=".jpg,.jpeg,.png,.pdf,.webp">
                    <i class="bi bi-cloud-upload" style="font-size:32px;color:#adb5bd;display:block;margin-bottom:8px"></i>
                    <div style="font-size:13px;color:#6c757d">
                        <strong>Haz clic aquí</strong> o arrastra tus archivos
                    </div>
                    <div style="font-size:11px;color:#adb5bd;margin-top:4px">
                        Imágenes del problema, capturas de pantalla, documentos PDF
                    </div>
                </div>
                <div class="file-list" id="fileList"></div>
            </div>

            <!-- ---- BOTONES ---- -->
            <div class="d-flex justify-content-between align-items-center pt-3 border-top flex-wrap gap-2">
                <a href="<?= BASE_URL ?>/public/ticket/consultar.php"
                   class="text-muted" style="font-size:13px">
                    <i class="bi bi-search me-1"></i>Consultar ticket existente
                </a>
                <button type="submit" class="btn-tx px-4" id="btnSubmit">
                    <i class="bi bi-send-fill me-1"></i>Enviar Solicitud
                </button>
            </div>

        </form>
    </div>
</div>
<?php endif; ?>

    <div class="text-center mt-3 text-muted" style="font-size:11px">
        <?= e($empresa_cfg) ?> · Sistema de Mesa de Ayuda ·
        <a href="<?= BASE_URL ?>/login.php" class="text-muted">Acceso Staff</a>
    </div>

</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const zone    = document.getElementById('uploadZone');
    const input   = document.getElementById('fileInput');
    const list    = document.getElementById('fileList');
    const btnSend = document.getElementById('btnSubmit');

    if (!zone || !input) return;

    // ── Actualizar lista visual de archivos ──────────────────
    function renderFiles() {
        list.innerHTML = '';
        if (!input.files || input.files.length === 0) {
            zone.classList.remove('has-files');
            return;
        }
        zone.classList.add('has-files');
        Array.from(input.files).forEach((file, idx) => {
            const ext  = file.name.split('.').pop().toLowerCase();
            const icon = ['jpg','jpeg','png','webp','gif'].includes(ext)
                       ? 'bi-image text-primary'
                       : 'bi-file-pdf text-danger';
            const size = file.size >= 1048576
                       ? (file.size/1048576).toFixed(1) + ' MB'
                       : Math.round(file.size/1024) + ' KB';

            const div = document.createElement('div');
            div.className = 'file-item';
            div.innerHTML =
                `<i class="bi ${icon}"></i>
                 <span class="text-truncate" style="max-width:320px">${file.name}</span>
                 <span class="text-muted ms-1">(${size})</span>`;
            list.appendChild(div);
        });
    }

    // ── Drag & Drop ──────────────────────────────────────────
    zone.addEventListener('dragover', function (e) {
        e.preventDefault();
        zone.style.borderColor = 'var(--tx-primary)';
        zone.style.background  = 'var(--tx-primary-light)';
    });
    zone.addEventListener('dragleave', function () {
        zone.style.borderColor = '';
        zone.style.background  = '';
    });
    zone.addEventListener('drop', function (e) {
        e.preventDefault();
        zone.style.borderColor = '';
        zone.style.background  = '';
        // Asignar archivos al input via DataTransfer
        const dt = new DataTransfer();
        Array.from(e.dataTransfer.files).forEach(f => dt.items.add(f));
        input.files = dt.files;
        renderFiles();
    });

    // ── Cambio de input ──────────────────────────────────────
    input.addEventListener('change', renderFiles);

    // ── Submit: deshabilitar botón para evitar doble envío ───
    document.getElementById('formTicket')?.addEventListener('submit', function () {
        if (btnSend) {
            btnSend.disabled = true;
            btnSend.innerHTML =
                '<span class="spinner-border spinner-border-sm me-1"></span>Enviando...';
            // Re-habilitar tras 15s por si hay error
            setTimeout(() => {
                btnSend.disabled = false;
                btnSend.innerHTML = '<i class="bi bi-send-fill me-1"></i>Enviar Solicitud';
            }, 15000);
        }
    });
})();
</script>

<?php if (ob_get_level()) ob_end_flush(); ?>
</body>
</html>
