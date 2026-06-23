<?php
/**
 * TRIGGERX - Constancias Técnicas: Acciones AJAX
 * Crear, guardar borrador, finalizar, eliminar, registrar envío de email
 */
define('TRIGGERX', true);
require_once __DIR__ . '/../../../core/config/config.php';
requireLogin();
requireModule('tickets');
verifyCsrf();

$user   = Session::getUser();
$db     = Database::getInstance();
$action = post('action');

/** Campos editables del formulario (todo lo que NO es snapshot/control) */
const CONST_CAMPOS_EDITABLES = [
    'tipo_servicio', 'hora_inicio', 'problema_reportado',
    'rev_danos_fisicos', 'rev_danos_fisicos_obs',
    'rev_tornillos_completos', 'rev_tornillos_obs',
    'rev_ensamblado_correcto', 'rev_ensamblado_obs',
    'rev_signos_desgaste', 'rev_desgaste_obs',
    'rev_signos_golpes', 'rev_golpes_obs',
    'rev_equipo_limpio', 'rev_limpio_obs',
    'trabajo_realizado',
    'calificacion', 'cliente_conf_dni', 'cliente_conf_nombre',
];

/**
 * Verifica si el usuario actual puede editar/eliminar esta constancia.
 * Admin/superadmin: siempre sí (mientras sea borrador).
 * Técnico: solo si es su propia constancia, sigue en borrador,
 *          no superó el límite de horas ni el límite de ediciones.
 */
function puedeEditarConstancia(array $constancia, array $user): array
{
    if ($constancia['estado'] !== 'borrador') {
        return [false, 'Esta constancia ya fue finalizada y no puede modificarse'];
    }

    if (Session::hasRole('admin')) {
        return [true, ''];
    }

    if ((int)$constancia['tecnico_id'] !== (int)$user['id']) {
        return [false, 'No tienes permiso sobre esta constancia'];
    }

    $cfg = Config::constancia();

    $horasTranscurridas = (time() - strtotime($constancia['created_at'])) / 3600;
    if ($horasTranscurridas > $cfg['horas_edicion']) {
        return [false, "El tiempo límite de edición ({$cfg['horas_edicion']}h) ha expirado. Solicita ayuda a un administrador."];
    }

    if ((int)$constancia['ediciones_borrador'] >= $cfg['max_ediciones']) {
        return [false, "Alcanzaste el máximo de {$cfg['max_ediciones']} ediciones permitidas. Solicita ayuda a un administrador."];
    }

    return [true, ''];
}

switch ($action) {

    // ============================================================
    case 'crear':
    // ============================================================
        requireRole('tecnico');
        $ticketId = (int)post('ticket_id');
        if (!$ticketId) jsonResponse(false, 'Ticket inválido');

        $ticket = $db->fetch("SELECT * FROM tickets WHERE id = ?", [$ticketId]);
        if (!$ticket) jsonResponse(false, 'Ticket no encontrado');

        try {
            $codigo = generarCodigoConstancia();

            $clienteTipo = ($ticket['origen'] === 'interno') ? 'interno' : 'externo';

            $id = $db->insert('constancias_tecnicas', [
                'ticket_id'          => $ticketId,
                'tecnico_id'         => $user['id'],
                'codigo'             => $codigo,
                'cliente_nombre'     => $ticket['cliente_nombre'],
                'cliente_tipo'       => $clienteTipo,
                'cliente_direccion'  => post('cliente_direccion') ?: null,
                'ticket_codigo'      => $ticket['codigo'],
                'equipo_marca'       => post('equipo_marca') ?: null,
                'equipo_modelo'      => $ticket['modelo_equipo'],
                'equipo_serie'       => $ticket['serie_equipo'],
                'problema_reportado' => $ticket['descripcion'],
                'hora_inicio'        => date('Y-m-d H:i:s'),
                'estado'             => 'borrador',
            ]);

            $db->insert('historial_ticket', [
                'ticket_id'  => $ticketId,
                'usuario_id' => $user['id'],
                'accion'     => 'constancia_creada',
                'detalle'    => "Constancia técnica {$codigo} iniciada (borrador)",
            ]);

            auditLog('constancia_crear', 'tickets', "Constancia {$codigo} creada para ticket #{$ticketId}");
            jsonResponse(true, 'Constancia iniciada', ['id' => $id, 'codigo' => $codigo]);

        } catch (Throwable $e) {
            error_log('Crear constancia error: ' . $e->getMessage());
            jsonResponse(false, 'Error al iniciar la constancia');
        }

    // ============================================================
    case 'guardar':
    // ============================================================
        requireRole('tecnico');
        $id = (int)post('id');
        $constancia = $db->fetch("SELECT * FROM constancias_tecnicas WHERE id = ?", [$id]);
        if (!$constancia) jsonResponse(false, 'Constancia no encontrada');

        [$puede, $motivo] = puedeEditarConstancia($constancia, $user);
        if (!$puede) jsonResponse(false, $motivo);

        $update = [];
        foreach (CONST_CAMPOS_EDITABLES as $campo) {
            if (array_key_exists($campo, $_POST)) {
                $valor = $_POST[$campo];
                $update[$campo] = ($valor === '') ? null : sanitize($valor);
            }
        }

        if (empty($update)) jsonResponse(false, 'Sin datos para guardar');

        // Solo cuenta como "edición" si la constancia ya tenía datos guardados antes (no la primera vez)
        if ((int)$constancia['ediciones_borrador'] > 0 || post('contar_edicion') === '1') {
            $update['ediciones_borrador'] = (int)$constancia['ediciones_borrador'] + 1;
        }

        $db->update('constancias_tecnicas', $update, 'id = ?', [$id]);
        jsonResponse(true, 'Borrador guardado correctamente');

    // ============================================================
    case 'subir_firma':
    // ============================================================
        requireRole('tecnico');
        $id     = (int)post('id');
        $tipo   = post('tipo'); // 'cliente' o 'tecnico'
        $imagen = $_POST['imagen_base64'] ?? '';
        $soloPerfil = post('solo_perfil') === '1';

        if (!in_array($tipo, ['cliente', 'tecnico'], true)) jsonResponse(false, 'Tipo de firma inválido');

        // Caso especial: guardar/actualizar la firma fija del perfil del técnico,
        // sin estar asociada a ninguna constancia en particular (ej. desde Mi Perfil)
        if ($soloPerfil) {
            if ($tipo !== 'tecnico') jsonResponse(false, 'Solo se puede guardar firma de técnico en el perfil');

            $up = guardarBase64($imagen, 'firmas', ['image/png', 'image/jpeg']);
            if (!$up['success']) jsonResponse(false, $up['error']);

            $db->update('usuarios', ['firma_archivo' => $up['ruta']], 'id = ?', [$user['id']]);
            auditLog('perfil_guardar_firma', 'perfil', 'Firma de perfil actualizada');
            jsonResponse(true, 'Firma guardada en tu perfil', ['ruta' => UPLOADS_URL . '/' . $up['ruta']]);
        }

        $constancia = $db->fetch("SELECT * FROM constancias_tecnicas WHERE id = ?", [$id]);
        if (!$constancia) jsonResponse(false, 'Constancia no encontrada');

        [$puede, $motivo] = puedeEditarConstancia($constancia, $user);
        if (!$puede) jsonResponse(false, $motivo);

        $up = guardarBase64($imagen, 'firmas', ['image/png', 'image/jpeg']);
        if (!$up['success']) jsonResponse(false, $up['error']);

        $campo = $tipo === 'cliente' ? 'cliente_firma_ruta' : 'tecnico_firma_ruta';
        $db->update('constancias_tecnicas', [$campo => $up['ruta']], 'id = ?', [$id]);

        // Si es la firma del técnico y eligió guardarla en su perfil para reutilizar
        if ($tipo === 'tecnico' && post('guardar_perfil') === '1') {
            $db->update('usuarios', ['firma_archivo' => $up['ruta']], 'id = ?', [$user['id']]);
        }

        jsonResponse(true, 'Firma guardada', ['ruta' => UPLOADS_URL . '/' . $up['ruta']]);

    // ============================================================
    case 'finalizar':
    // ============================================================
        requireRole('tecnico');
        $id = (int)post('id');
        $pdfBase64 = $_POST['pdf_base64'] ?? '';

        $constancia = $db->fetch("SELECT * FROM constancias_tecnicas WHERE id = ?", [$id]);
        if (!$constancia) jsonResponse(false, 'Constancia no encontrada');

        [$puede, $motivo] = puedeEditarConstancia($constancia, $user);
        if (!$puede) jsonResponse(false, $motivo);

        if (!notEmpty($constancia['trabajo_realizado'] ?? '')) {
            jsonResponse(false, 'Debes completar el trabajo realizado antes de finalizar');
        }
        if (empty($constancia['cliente_firma_ruta'])) {
            jsonResponse(false, 'Falta la firma del cliente');
        }
        if (empty($constancia['tecnico_firma_ruta']) && empty($_POST['tecnico_firma_ruta'])) {
            jsonResponse(false, 'Falta la firma del técnico');
        }
        if (!$pdfBase64) {
            jsonResponse(false, 'No se recibió el PDF generado');
        }

        try {
            $db->beginTransaction();

            // Guardar el PDF como archivo
            $up = guardarBase64($pdfBase64, 'constancias', ['application/pdf']);
            if (!$up['success']) throw new RuntimeException($up['error']);

            // Adjuntarlo al ticket
            $adjuntoId = $db->insert('adjuntos_ticket', [
                'ticket_id'       => $constancia['ticket_id'],
                'usuario_id'      => $user['id'],
                'nombre_original' => "Constancia_{$constancia['codigo']}.pdf",
                'nombre_archivo'  => $up['nombre_archivo'],
                'ruta'            => $up['ruta'],
                'mime_type'       => 'application/pdf',
                'tamano'          => $up['tamano'],
                'tipo'            => 'constancia',
            ]);

            $db->update('constancias_tecnicas', [
                'estado'         => 'final',
                'hora_fin'       => date('Y-m-d H:i:s'),
                'finalizado_at'  => date('Y-m-d H:i:s'),
                'adjunto_id'     => $adjuntoId,
            ], 'id = ?', [$id]);

            $db->insert('historial_ticket', [
                'ticket_id'  => $constancia['ticket_id'],
                'usuario_id' => $user['id'],
                'accion'     => 'constancia_finalizada',
                'detalle'    => "Constancia técnica {$constancia['codigo']} finalizada y adjuntada",
            ]);

            $db->commit();
            auditLog('constancia_finalizar', 'tickets', "Constancia {$constancia['codigo']} finalizada");

            jsonResponse(true, 'Constancia finalizada y adjuntada al ticket', [
                'adjunto_id' => $adjuntoId,
                'codigo'     => $constancia['codigo'],
            ]);

        } catch (Throwable $e) {
            $db->rollback();
            error_log('Finalizar constancia error: ' . $e->getMessage());
            jsonResponse(false, 'Error al finalizar la constancia');
        }

    // ============================================================
    case 'enviar_email':
    // ============================================================
        requireRole('tecnico');
        $id = (int)post('id');
        $constancia = $db->fetch(
            "SELECT ct.*, t.cliente_email, t.cliente_nombre, t.codigo AS tk_codigo
             FROM constancias_tecnicas ct
             JOIN tickets t ON ct.ticket_id = t.id
             WHERE ct.id = ?", [$id]
        );
        if (!$constancia) jsonResponse(false, 'Constancia no encontrada');
        if ($constancia['estado'] !== 'final') jsonResponse(false, 'La constancia debe estar finalizada primero');

        try {
            $adjunto = $db->fetch("SELECT * FROM adjuntos_ticket WHERE id = ?", [$constancia['adjunto_id']]);
            $urlPdf  = $adjunto ? UPLOADS_URL . '/' . $adjunto['ruta'] : null;

            $mailer = new Mailer();
            $enviado = $mailer->send(
                $constancia['cliente_email'],
                "Constancia Técnica {$constancia['codigo']} — Ticket {$constancia['tk_codigo']}",
                "<p>Hola <strong>" . e($constancia['cliente_nombre']) . "</strong>,</p>
                 <p>Adjuntamos la constancia técnica de la atención realizada en tu ticket
                 <strong>" . e($constancia['tk_codigo']) . "</strong>.</p>" .
                 ($urlPdf ? "<p><a href='{$urlPdf}' target='_blank'>Descargar Constancia (PDF)</a></p>" : ''),
                $constancia['cliente_nombre']
            );

            $db->update('constancias_tecnicas', [
                'enviado_email'    => $enviado ? 1 : 0,
                'enviado_email_at' => $enviado ? date('Y-m-d H:i:s') : null,
            ], 'id = ?', [$id]);

            if ($enviado) {
                auditLog('constancia_enviar_email', 'tickets', "Constancia {$constancia['codigo']} enviada por correo");
                jsonResponse(true, 'Constancia enviada por correo');
            }
            jsonResponse(false, 'No se pudo enviar el correo');

        } catch (Throwable $e) {
            error_log('Enviar constancia error: ' . $e->getMessage());
            jsonResponse(false, 'Error al enviar el correo');
        }

    // ============================================================
    case 'omitir_email':
    // ============================================================
        requireRole('tecnico');
        $id = (int)post('id');
        $db->update('constancias_tecnicas', ['enviado_email' => 0], 'id = ?', [$id]);
        jsonResponse(true, 'Continuando sin enviar correo');

    // ============================================================
    case 'eliminar':
    // ============================================================
        requireRole('tecnico');
        $id = (int)post('id');
        $constancia = $db->fetch("SELECT * FROM constancias_tecnicas WHERE id = ?", [$id]);
        if (!$constancia) jsonResponse(false, 'Constancia no encontrada');

        [$puede, $motivo] = puedeEditarConstancia($constancia, $user);
        if (!$puede) jsonResponse(false, $motivo);

        $db->delete('constancias_tecnicas', 'id = ?', [$id]);
        auditLog('constancia_eliminar', 'tickets', "Constancia {$constancia['codigo']} eliminada (borrador)");
        jsonResponse(true, 'Borrador eliminado');

    default:
        jsonResponse(false, 'Acción no válida', null, 400);
}
