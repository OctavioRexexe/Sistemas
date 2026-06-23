<?php
/**
 * TRIGGERX - Funciones Helper Globales
 */
defined('TRIGGERX') or die('Acceso denegado');

// ============================================================
// SALIDA LIMPIA
// ============================================================

/** Escapar HTML para output seguro */
function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** Respuesta JSON estándar */
function jsonResponse(bool $success, string $message = '', mixed $data = null, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Redirigir */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/** Redirigir con mensaje flash */
function redirectWith(string $url, string $type, string $msg): never
{
    Session::flash('alert_type', $type);
    Session::flash('alert_msg', $msg);
    redirect($url);
}

// ============================================================
// FECHAS Y TIEMPO
// ============================================================

/** Formato fecha */
function fdate(mixed $datetime, string $format = null): string
{
    if (!$datetime) return '—';
    $format ??= DATE_FORMAT;
    $dt = $datetime instanceof DateTime ? $datetime : new DateTime((string)$datetime);
    return $dt->format($format);
}

/** Formato fecha y hora */
function fdatetime(mixed $datetime): string
{
    return fdate($datetime, DATETIME_FORMAT);
}

/** Diferencia tiempo en texto legible */
function timeAgo(string $datetime): string
{
    $time = time() - strtotime($datetime);
    return match(true) {
        $time < 60     => 'Hace un momento',
        $time < 3600   => 'Hace ' . floor($time/60) . ' min',
        $time < 86400  => 'Hace ' . floor($time/3600) . ' h',
        $time < 604800 => 'Hace ' . floor($time/86400) . ' días',
        default        => fdate($datetime),
    };
}

/** Diferencia en días */
function daysDiff(string $date1, string $date2 = 'now'): int
{
    $d1 = new DateTime($date1);
    $d2 = new DateTime($date2);
    return (int)$d1->diff($d2)->days;
}

// ============================================================
// TICKETS
// ============================================================

/** Generar código de ticket único */
function generarCodigoTicket(): string
{
    $db     = Database::getInstance();
    $prefijo = Config::prefijo();

    // Usar AUTO_INCREMENT para obtener el próximo número de forma segura
    // sin necesitar LOCK TABLES (que puede fallar en hosting compartido)
    $ultimo = (int)($db->fetchColumn("SELECT MAX(id) FROM tickets") ?? 0);
    $numero = $ultimo + 1;

    // Verificar que el código no exista (por si acaso)
    $intentos = 0;
    do {
        $codigo = $prefijo . '-' . str_pad($numero, 6, '0', STR_PAD_LEFT);
        $existe = (int)$db->fetchColumn("SELECT COUNT(*) FROM tickets WHERE codigo = ?", [$codigo]);
        if ($existe) $numero++;
        $intentos++;
    } while ($existe && $intentos < 100);

    return $codigo;
}

/** Generar código de constancia técnica único */
function generarCodigoConstancia(): string
{
    $db      = Database::getInstance();
    $prefijo = Config::constancia()['prefijo'];

    $ultimo = (int)($db->fetchColumn("SELECT MAX(id) FROM constancias_tecnicas") ?? 0);
    $numero = $ultimo + 1;

    $intentos = 0;
    do {
        $codigo = $prefijo . '-' . str_pad($numero, 6, '0', STR_PAD_LEFT);
        $existe = (int)$db->fetchColumn("SELECT COUNT(*) FROM constancias_tecnicas WHERE codigo = ?", [$codigo]);
        if ($existe) $numero++;
        $intentos++;
    } while ($existe && $intentos < 100);

    return $codigo;
}

/** Generar código de cotización único */
function generarCodigoCotizacion(): string
{
    $db      = Database::getInstance();
    $prefijo = Config::cotizacion()['prefijo'];

    $ultimo = (int)($db->fetchColumn("SELECT MAX(id) FROM cotizaciones") ?? 0);
    $numero = $ultimo + 1;

    $intentos = 0;
    do {
        $codigo = $prefijo . '-' . str_pad($numero, 6, '0', STR_PAD_LEFT);
        $existe = (int)$db->fetchColumn("SELECT COUNT(*) FROM cotizaciones WHERE codigo = ?", [$codigo]);
        if ($existe) $numero++;
        $intentos++;
    } while ($existe && $intentos < 100);

    return $codigo;
}

/** Color/etiqueta según estado de cotización: [nombre, color hex] */
function badgeEstadoCotizacion(string $estado): array
{
    return match ($estado) {
        'borrador'  => ['Borrador', '#6c757d'],
        'enviada'   => ['Enviada', '#0d6efd'],
        'aprobada'  => ['Aprobada', '#198754'],
        'rechazada' => ['Rechazada', '#dc3545'],
        'vencida'   => ['Vencida', '#fd7e14'],
        default     => [ucfirst($estado), '#6c757d'],
    };
}


function slaColor(int $dias, bool $hex = false): string
{
    $sla = Config::sla();
    if ($dias <= $sla['preventiva']) {
        return $hex ? '#198754' : 'success';
    } elseif ($dias <= $sla['importante']) {
        return $hex ? '#ffc107' : 'warning';
    } elseif ($dias < $sla['critica']) {
        return $hex ? '#fd7e14' : 'orange';
    } else {
        return $hex ? '#dc3545' : 'danger';
    }
}

/** Estado SLA como texto */
function slaEstado(int $dias): string
{
    $sla = Config::sla();
    if ($dias <= $sla['preventiva']) return 'verde';
    if ($dias <= $sla['importante']) return 'amarillo';
    if ($dias < $sla['critica'])    return 'naranja';
    return 'rojo';
}

/** Badge HTML de estado */
function badgeEstado(string $nombre, string $color): string
{
    return '<span class="badge" style="background-color:' . e($color) . '">' . e($nombre) . '</span>';
}

/** Badge HTML de prioridad */
function badgePrioridad(string $nombre, string $color): string
{
    return '<span class="badge border" style="color:' . e($color) . ';border-color:' . e($color) . '!important;background:transparent">' . e($nombre) . '</span>';
}

// ============================================================
// UPLOADS
// ============================================================

/** Validar y procesar upload */
function processUpload(array $file, string $subdir = 'tickets'): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Error en la carga del archivo'];
    }

    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return ['success' => false, 'error' => 'Archivo demasiado grande (máx 10MB)'];
    }

    // Validar MIME real (no confiar en extensión)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, UPLOAD_ALLOWED_MIME, true)) {
        return ['success' => false, 'error' => 'Tipo de archivo no permitido'];
    }

    // Extensión permitida
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, UPLOAD_ALLOWED_EXT, true)) {
        return ['success' => false, 'error' => 'Extensión no permitida'];
    }

    // Nombre seguro con hash
    $newName = bin2hex(random_bytes(16)) . '_' . time() . '.' . $ext;
    $targetDir = UPLOADS_PATH . '/' . $subdir . '/';

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $targetPath = $targetDir . $newName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'error' => 'Error al guardar el archivo'];
    }

    // Crear/actualizar .htaccess de seguridad en uploads
    secureUploadsDir($targetDir);

    return [
        'success'          => true,
        'nombre_original'  => $file['name'],
        'nombre_archivo'   => $newName,
        'ruta'             => $subdir . '/' . $newName,
        'mime_type'        => $mime,
        'tamano'           => $file['size'],
    ];
}

/**
 * Guardar una imagen o PDF recibido como Data URL base64 (ej. canvas de firma, PDF generado en navegador)
 * @param string $dataUrl  Cadena tipo "data:image/png;base64,...." o "data:application/pdf;base64,...."
 * @param string $subdir   Subcarpeta dentro de UPLOADS_PATH
 * @param array  $allowedMime Mimes permitidos para esta operación específica
 */
function guardarBase64(string $dataUrl, string $subdir, array $allowedMime = ['image/png', 'image/jpeg', 'application/pdf']): array
{
    if (!preg_match('/^data:([a-zA-Z0-9\/+.-]+);base64,(.+)$/', $dataUrl, $m)) {
        return ['success' => false, 'error' => 'Formato de archivo inválido'];
    }

    $mime = strtolower($m[1]);
    if (!in_array($mime, $allowedMime, true)) {
        return ['success' => false, 'error' => 'Tipo de archivo no permitido'];
    }

    $binario = base64_decode($m[2], true);
    if ($binario === false) {
        return ['success' => false, 'error' => 'No se pudo decodificar el archivo'];
    }

    if (strlen($binario) > UPLOAD_MAX_SIZE) {
        return ['success' => false, 'error' => 'Archivo demasiado grande (máx 10MB)'];
    }

    $extMap = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'application/pdf' => 'pdf'];
    $ext = $extMap[$mime] ?? 'bin';

    $newName   = bin2hex(random_bytes(16)) . '_' . time() . '.' . $ext;
    $targetDir = UPLOADS_PATH . '/' . $subdir . '/';

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $targetPath = $targetDir . $newName;
    if (file_put_contents($targetPath, $binario) === false) {
        return ['success' => false, 'error' => 'Error al guardar el archivo'];
    }

    secureUploadsDir($targetDir);

    return [
        'success'        => true,
        'nombre_archivo' => $newName,
        'ruta'           => $subdir . '/' . $newName,
        'mime_type'      => $mime,
        'tamano'         => strlen($binario),
    ];
}

/** Crear .htaccess en directorio de uploads */
function secureUploadsDir(string $dir): void
{
    $htaccess = $dir . '.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, 
            "Options -Indexes\n" .
            "<FilesMatch \"\.(php|php5|phtml|pl|py|jsp|asp|sh|cgi|exe|bat)$\">\n" .
            "  Deny from all\n" .
            "</FilesMatch>\n"
        );
    }
}

// ============================================================
// NOTIFICACIONES
// ============================================================

/** Crear notificación interna */
function crearNotificacion(int $usuarioId, string $tipo, string $titulo, string $mensaje, string $url = ''): void
{
    try {
        $db = Database::getInstance();
        $db->insert('notificaciones', [
            'usuario_id' => $usuarioId,
            'tipo'       => $tipo,
            'titulo'     => $titulo,
            'mensaje'    => $mensaje,
            'url'        => $url,
        ]);
    } catch (Throwable $e) {
        error_log('Notificación error: ' . $e->getMessage());
    }
}

/** Obtener notificaciones no leídas del usuario */
function getNotificacionesPendientes(int $usuarioId): array
{
    try {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT * FROM notificaciones WHERE usuario_id = ? AND leida = 0 ORDER BY created_at DESC LIMIT 20",
            [$usuarioId]
        );
    } catch (Throwable) {
        return [];
    }
}

// ============================================================
// AUDIT LOG
// ============================================================

/** Registrar acción en log de auditoría */
function auditLog(string $accion, string $modulo = '', string $descripcion = ''): void
{
    try {
        $db = Database::getInstance();
        $db->insert('logs_auditoria', [
            'usuario_id'  => Session::userId(),
            'accion'      => $accion,
            'modulo'      => $modulo,
            'descripcion' => $descripcion,
            'ip'          => getUserIp(),
            'user_agent'  => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    } catch (Throwable) {}
}

/** Obtener IP real del usuario */
function getUserIp(): string
{
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

// ============================================================
// PERMISOS
// ============================================================

/** Verificar acceso a módulo */
function canAccessModule(string $moduleSlug, int $userId, int $rolId): bool
{
    try {
        $db = Database::getInstance();

        // SuperAdmin tiene acceso a todo
        if ($rolId === 1) return true;

        // Verificar por usuario primero, luego por rol
        $sql = "SELECT pm.puede_ver FROM permisos_modulos pm
                JOIN modulos m ON pm.modulo_id = m.id
                WHERE m.slug = ? AND m.activo = 1 
                AND (pm.usuario_id = ? OR pm.rol_id = ?)
                ORDER BY pm.usuario_id DESC LIMIT 1";
        
        $result = $db->fetchColumn($sql, [$moduleSlug, $userId, $rolId]);
        return (bool)($result ?? false);
    } catch (Throwable) {
        return false;
    }
}

/** Obtener módulos accesibles para el usuario */
function getModulosUsuario(int $userId, int $rolId): array
{
    try {
        $db = Database::getInstance();

        if ($rolId === 1) {
            return $db->fetchAll("SELECT * FROM modulos WHERE activo = 1 ORDER BY orden");
        }

        return $db->fetchAll(
            "SELECT DISTINCT m.* FROM modulos m
             JOIN permisos_modulos pm ON pm.modulo_id = m.id
             WHERE m.activo = 1 AND pm.puede_ver = 1
             AND (pm.usuario_id = ? OR pm.rol_id = ?)
             ORDER BY m.orden",
            [$userId, $rolId]
        );
    } catch (Throwable) {
        return [];
    }
}

// ============================================================
// MISC
// ============================================================

/** Truncar texto */
function truncate(string $text, int $length = 100, string $suffix = '…'): string
{
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length) . $suffix;
}

/** Formatear tamaño de archivo */
function formatBytes(int $bytes): string
{
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

/** Generar token aleatorio */
function generateToken(int $length = 32): string
{
    return bin2hex(random_bytes($length));
}

/** Obtener alerta flash de sesión */
function getFlashAlert(): string
{
    $type = Session::getFlash('alert_type');
    $msg  = Session::getFlash('alert_msg');
    if (!$type || !$msg) return '';
    return '<div class="alert alert-' . e($type) . ' alert-dismissible fade show" role="alert">'
         . e($msg)
         . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
         . '</div>';
}

// ============================================================
// NAVBAR HELPERS
// ============================================================

/** Icono Bootstrap por tipo de notificación */
function notifIcon(string $tipo): string
{
    return match ($tipo) {
        'ticket_nuevo'       => 'bi-ticket-fill',
        'ticket_asignado'    => 'bi-person-check-fill',
        'ticket_actualizado' => 'bi-arrow-repeat',
        'sla_vencido'        => 'bi-exclamation-triangle-fill',
        'sla_proximo'        => 'bi-clock-history',
        'comentario'         => 'bi-chat-fill',
        'resuelto'           => 'bi-check-circle-fill',
        default              => 'bi-bell-fill',
    };
}
