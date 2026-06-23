<?php
define('TRIGGERX', true);
require_once __DIR__ . '/../../../core/config/config.php';
requireLogin();
requireModule('tickets');
verifyCsrf();

$user   = Session::getUser();
$db     = Database::getInstance();
$action = post('action');

switch ($action) {

    // ============================================================
    case 'crear_interno':
    // ============================================================
        requireRole('tecnico');

        $campos = ['cliente_nombre','cliente_email','asunto','descripcion'];
        foreach ($campos as $c) {
            if (!notEmpty(post($c))) {
                jsonResponse(false, "El campo {$c} es requerido");
            }
        }
        if (!isValidEmail(post('cliente_email'))) {
            jsonResponse(false, 'Correo inválido');
        }

        try {
            $db->beginTransaction();

            $codigo    = generarCodigoTicket();
            $prioridad = $db->fetch("SELECT * FROM prioridades_ticket WHERE id = ?", [(int)post('prioridad_id',2)]);
            $slaLimite = date('Y-m-d H:i:s', strtotime('+' . ($prioridad['sla_horas'] ?? 72) . ' hours'));

            $ticketId = $db->insert('tickets', [
                'codigo'           => $codigo,
                'usuario_id'       => $user['id'],
                'estado_id'        => 1,
                'prioridad_id'     => (int)post('prioridad_id', 2),
                'categoria_id'     => post('categoria_id') ?: null,
                'tecnico_id'       => post('tecnico_id') ?: null,
                'cliente_nombre'   => post('cliente_nombre'),
                'cliente_empresa'  => post('cliente_empresa'),
                'cliente_telefono' => post('cliente_telefono'),
                'cliente_email'    => post('cliente_email'),
                'asunto'           => post('asunto'),
                'descripcion'      => sanitize($_POST['descripcion'] ?? ''),
                'serie_equipo'     => post('serie_equipo'),
                'sla_limite'       => $slaLimite,
                'sla_estado'       => 'verde',
                'origen'           => 'interno',
            ]);

            // Procesar adjuntos
            if (!empty($_FILES['adjuntos']['name'][0])) {
                $files = reorganizeFiles($_FILES['adjuntos']);
                foreach ($files as $file) {
                    $up = processUpload($file, 'tickets');
                    if ($up['success']) {
                        $db->insert('adjuntos_ticket', [
                            'ticket_id'       => $ticketId,
                            'usuario_id'      => $user['id'],
                            'nombre_original' => $up['nombre_original'],
                            'nombre_archivo'  => $up['nombre_archivo'],
                            'ruta'            => $up['ruta'],
                            'mime_type'       => $up['mime_type'],
                            'tamano'          => $up['tamano'],
                            'tipo'            => 'adjunto',
                        ]);
                    }
                }
            }

            // Historial
            $db->insert('historial_ticket', [
                'ticket_id'  => $ticketId,
                'usuario_id' => $user['id'],
                'accion'     => 'creado',
                'detalle'    => "Ticket creado internamente por {$user['nombre']} {$user['apellido']}",
            ]);

            // Notificar a admins
            $admins = $db->fetchAll("SELECT id FROM usuarios u JOIN roles r ON u.rol_id=r.id WHERE r.nivel>=3 AND u.activo=1");
            foreach ($admins as $a) {
                crearNotificacion($a['id'], 'ticket_nuevo',
                    "Nuevo ticket {$codigo}",
                    "Se creó el ticket: " . post('asunto'),
                    BASE_URL . "/modules/tickets/detalle.php?id={$ticketId}"
                );
            }

            $db->commit();
            auditLog('ticket_crear', 'tickets', "Ticket {$codigo} creado");
            jsonResponse(true, 'Ticket creado exitosamente', ['codigo' => $codigo, 'id' => $ticketId]);

        } catch (Throwable $e) {
            $db->rollback();
            error_log('Crear ticket error: ' . $e->getMessage());
            jsonResponse(false, 'Error al crear el ticket');
        }

    // ============================================================
    case 'asignar':
    // ============================================================
        requireRole('admin');
        $ticketId  = (int)post('ticket_id');
        $tecnicoId = (int)post('tecnico_id');

        if (!$ticketId || !$tecnicoId) jsonResponse(false, 'Datos inválidos');

        $tecnico = $db->fetch("SELECT CONCAT(nombre,' ',apellido) AS nombre FROM usuarios WHERE id=?", [$tecnicoId]);
        if (!$tecnico) jsonResponse(false, 'Técnico no encontrado');

        $ticket = $db->fetch("SELECT codigo FROM tickets WHERE id=?", [$ticketId]);
        $db->update('tickets', ['tecnico_id' => $tecnicoId], 'id = ?', [$ticketId]);

        $db->insert('historial_ticket', [
            'ticket_id'  => $ticketId,
            'usuario_id' => $user['id'],
            'accion'     => 'asignado',
            'detalle'    => "Asignado a {$tecnico['nombre']}",
        ]);

        crearNotificacion($tecnicoId, 'ticket_asignado',
            "Ticket asignado: {$ticket['codigo']}",
            "Se te asignó un nuevo ticket",
            BASE_URL . "/modules/tickets/detalle.php?id={$ticketId}"
        );

        auditLog('ticket_asignar', 'tickets', "Ticket #{$ticketId} asignado a técnico #{$tecnicoId}");
        jsonResponse(true, 'Ticket asignado correctamente');

    // ============================================================
    case 'cambiar_estado':
    // ============================================================
        requireRole('tecnico');
        $ticketId = (int)post('ticket_id');
        $estadoId = (int)post('estado_id');

        if (!$ticketId || !$estadoId) jsonResponse(false, 'Datos inválidos');

        $estado  = $db->fetch("SELECT * FROM estados_ticket WHERE id=?", [$estadoId]);
        $ticket  = $db->fetch("SELECT * FROM tickets WHERE id=?", [$ticketId]);
        if (!$estado || !$ticket) jsonResponse(false, 'No encontrado');

        $update = ['estado_id' => $estadoId];
        if ($estado['es_final']) {
            $update['fecha_resolucion'] = date('Y-m-d H:i:s');
            $update['tiempo_atencion']  = (int)((time() - strtotime($ticket['created_at'])) / 60);
        }
        $db->update('tickets', $update, 'id = ?', [$ticketId]);

        $db->insert('historial_ticket', [
            'ticket_id'      => $ticketId,
            'usuario_id'     => $user['id'],
            'accion'         => 'estado_cambio',
            'valor_anterior' => $ticket['estado_id'],
            'valor_nuevo'    => $estadoId,
            'detalle'        => "Estado cambiado a: {$estado['nombre']}",
        ]);

        auditLog('ticket_estado', 'tickets', "Ticket #{$ticketId} → {$estado['nombre']}");
        jsonResponse(true, 'Estado actualizado');

    // ============================================================
    case 'comentar':
    // ============================================================
        $ticketId  = (int)post('ticket_id');
        $contenido = sanitize($_POST['contenido'] ?? '');
        $tipo      = in_array(post('tipo'), ['publico','interno','solucion']) ? post('tipo') : 'publico';

        if (!$ticketId || !notEmpty($contenido)) {
            jsonResponse(false, 'El comentario no puede estar vacío');
        }

        // Verificar acceso al ticket
        $ticket = $db->fetch("SELECT * FROM tickets WHERE id=?", [$ticketId]);
        if (!$ticket) jsonResponse(false, 'Ticket no encontrado');

        try {
            $db->beginTransaction();

            $comentarioId = $db->insert('comentarios_ticket', [
                'ticket_id'   => $ticketId,
                'usuario_id'  => $user['id'],
                'autor_nombre'=> $user['nombre'] . ' ' . $user['apellido'],
                'contenido'   => $contenido,
                'tipo'        => $tipo,
            ]);

            // Si es solución, cambiar estado a Resuelto
            if ($tipo === 'solucion') {
                $estadoResuelto = $db->fetchColumn("SELECT id FROM estados_ticket WHERE slug='resuelto'");
                if ($estadoResuelto) {
                    $db->update('tickets', [
                        'estado_id'        => $estadoResuelto,
                        'fecha_resolucion' => date('Y-m-d H:i:s'),
                        'tiempo_atencion'  => (int)((time() - strtotime($ticket['created_at'])) / 60),
                    ], 'id = ?', [$ticketId]);
                }
            }

            // Primera respuesta
            if (!$ticket['primera_respuesta'] && $user['rol_slug'] !== 'cliente') {
                $db->update('tickets', ['primera_respuesta' => date('Y-m-d H:i:s')], 'id=?', [$ticketId]);
            }

            // Actualizar updated_at
            $db->update('tickets', ['updated_at' => date('Y-m-d H:i:s')], 'id=?', [$ticketId]);

            // Historial
            $db->insert('historial_ticket', [
                'ticket_id'  => $ticketId,
                'usuario_id' => $user['id'],
                'accion'     => 'comentario',
                'detalle'    => "Comentario {$tipo} agregado",
            ]);

            // Procesar adjuntos del comentario
            if (!empty($_FILES['adjuntos']['name'][0])) {
                $files = reorganizeFiles($_FILES['adjuntos']);
                foreach ($files as $file) {
                    $up = processUpload($file, 'tickets');
                    if ($up['success']) {
                        $db->insert('adjuntos_ticket', [
                            'ticket_id'       => $ticketId,
                            'comentario_id'   => $comentarioId,
                            'usuario_id'      => $user['id'],
                            'nombre_original' => $up['nombre_original'],
                            'nombre_archivo'  => $up['nombre_archivo'],
                            'ruta'            => $up['ruta'],
                            'mime_type'       => $up['mime_type'],
                            'tamano'          => $up['tamano'],
                            'tipo'            => 'evidencia',
                        ]);
                    }
                }
            }

            $db->commit();
            auditLog('ticket_comentar', 'tickets', "Comentario en ticket #{$ticketId}");
            jsonResponse(true, 'Comentario agregado', ['comentario_id' => $comentarioId]);

        } catch (Throwable $e) {
            $db->rollback();
            jsonResponse(false, 'Error al guardar comentario');
        }

    // ============================================================
    case 'cambiar_prioridad':
    // ============================================================
        requireRole('admin');
        $ticketId   = (int)post('ticket_id');
        $prioridadId = (int)post('prioridad_id');
        $db->update('tickets', ['prioridad_id' => $prioridadId], 'id=?', [$ticketId]);
        jsonResponse(true, 'Prioridad actualizada');

    default:
        jsonResponse(false, 'Acción no válida', null, 400);
}

/** Reorganizar array de files para múltiples */
function reorganizeFiles(array $files): array
{
    $result = [];
    if (is_array($files['name'])) {
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $result[] = [
                    'name'     => $files['name'][$i],
                    'type'     => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i],
                ];
            }
        }
    }
    return $result;
}
