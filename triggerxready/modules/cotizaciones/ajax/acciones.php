<?php
/**
 * TRIGGERX - Cotizaciones: Acciones AJAX
 * Crear, guardar, gestionar items, cambiar estado, generar PDF, enviar correo
 */
define('TRIGGERX', true);
require_once __DIR__ . '/../../../core/config/config.php';
requireLogin();
requireModule('cotizaciones');
verifyCsrf();

$user   = Session::getUser();
$db     = Database::getInstance();
$action = post('action');

/** Recalcula y persiste subtotal/igv/total de una cotización a partir de sus items */
function recalcularTotales(Database $db, int $cotizacionId): array
{
    $items = $db->fetchAll("SELECT cantidad, precio_unitario FROM cotizacion_items WHERE cotizacion_id = ?", [$cotizacionId]);

    $subtotal = 0.0;
    foreach ($items as $it) {
        $subtotal += (float)$it['cantidad'] * (float)$it['precio_unitario'];
    }

    $igvPct = Config::cotizacion()['igv_pct'];
    $igv    = round($subtotal * ($igvPct / 100), 2);
    $total  = round($subtotal + $igv, 2);
    $subtotal = round($subtotal, 2);

    $db->update('cotizaciones', [
        'subtotal' => $subtotal, 'igv' => $igv, 'total' => $total,
    ], 'id = ?', [$cotizacionId]);

    return ['subtotal' => $subtotal, 'igv' => $igv, 'total' => $total];
}

/** Verifica que el usuario pueda editar esta cotización (solo borrador, propio o admin) */
function puedeEditarCotizacion(array $cot, array $user): array
{
    if ($cot['estado'] !== 'borrador') {
        return [false, 'Esta cotización ya fue enviada y no puede modificarse. Crea una nueva si necesitas cambios.'];
    }
    if (Session::hasRole('admin')) return [true, ''];
    if ((int)$cot['usuario_id'] !== (int)$user['id']) {
        return [false, 'No tienes permiso sobre esta cotización'];
    }
    return [true, ''];
}

switch ($action) {

    // ============================================================
    case 'crear':
    // ============================================================
        requireRole('tecnico');
        $ticketId = (int)post('ticket_id') ?: null;

        $empresaId = (int)post('empresa_id') ?: null;
        $empresaRazon = post('empresa_razon_social');
        $empresaRuc   = post('empresa_ruc');
        $empresaDir   = post('empresa_direccion');
        $clienteNombre = post('cliente_nombre');

        // Si seleccionaron una empresa registrada, usar sus datos como base
        // (pero permitir que el formulario los haya ajustado/sobrescrito)
        if ($empresaId) {
            $empresa = $db->fetch("SELECT * FROM empresas_clientes WHERE id = ?", [$empresaId]);
            if (!$empresa) jsonResponse(false, 'Empresa no encontrada');
            $empresaRazon  = $empresaRazon ?: $empresa['razon_social'];
            $empresaRuc    = $empresaRuc ?: $empresa['ruc'];
            $empresaDir    = $empresaDir ?: $empresa['direccion'];
            $clienteNombre = $clienteNombre ?: $empresa['contacto_nombre'];
        }

        if (!notEmpty($empresaRazon)) jsonResponse(false, 'La razón social / empresa es requerida');
        if (!notEmpty($clienteNombre)) jsonResponse(false, 'El nombre del cliente es requerido');

        try {
            $codigo = generarCodigoCotizacion();
            $diasValidez = Config::cotizacion()['dias_validez'];

            $id = $db->insert('cotizaciones', [
                'codigo'              => $codigo,
                'usuario_id'          => $user['id'],
                'ticket_id'           => $ticketId,
                'empresa_id'          => $empresaId,
                'empresa_razon_social'=> $empresaRazon,
                'empresa_ruc'         => $empresaRuc ?: null,
                'empresa_direccion'   => $empresaDir ?: null,
                'cliente_nombre'      => $clienteNombre,
                'fecha_emision'       => date('Y-m-d'),
                'fecha_vencimiento'   => date('Y-m-d', strtotime("+{$diasValidez} days")),
                'estado'              => 'borrador',
            ]);

            auditLog('cotizacion_crear', 'cotizaciones', "Cotización {$codigo} creada");
            jsonResponse(true, 'Cotización creada', ['id' => $id, 'codigo' => $codigo]);

        } catch (Throwable $e) {
            error_log('Crear cotización error: ' . $e->getMessage());
            jsonResponse(false, 'Error al crear la cotización');
        }

    // ============================================================
    case 'guardar_cabecera':
    // ============================================================
        requireRole('tecnico');
        $id = (int)post('id');
        $cot = $db->fetch("SELECT * FROM cotizaciones WHERE id = ?", [$id]);
        if (!$cot) jsonResponse(false, 'Cotización no encontrada');

        [$puede, $motivo] = puedeEditarCotizacion($cot, $user);
        if (!$puede) jsonResponse(false, $motivo);

        $campos = ['empresa_razon_social', 'empresa_ruc', 'empresa_direccion', 'cliente_nombre', 'notas'];
        $update = [];
        foreach ($campos as $c) {
            if (array_key_exists($c, $_POST)) {
                $update[$c] = post($c) ?: null;
            }
        }
        if (!empty($update)) {
            $db->update('cotizaciones', $update, 'id = ?', [$id]);
        }
        jsonResponse(true, 'Cotización actualizada');

    // ============================================================
    case 'agregar_item':
    // ============================================================
        requireRole('tecnico');
        $id = (int)post('id');
        $cot = $db->fetch("SELECT * FROM cotizaciones WHERE id = ?", [$id]);
        if (!$cot) jsonResponse(false, 'Cotización no encontrada');

        [$puede, $motivo] = puedeEditarCotizacion($cot, $user);
        if (!$puede) jsonResponse(false, $motivo);

        $productoId  = (int)post('producto_id') ?: null;
        $codigo      = post('codigo');
        $descripcion = post('descripcion');
        $cantidad    = (float)post('cantidad', '1');
        $precio      = (float)post('precio_unitario', '0');

        if ($productoId) {
            $prod = $db->fetch("SELECT * FROM productos_servicios WHERE id = ?", [$productoId]);
            if ($prod) {
                $codigo      = $codigo ?: $prod['codigo'];
                $descripcion = $descripcion ?: $prod['descripcion'];
                $precio      = $precio ?: (float)$prod['precio_unitario'];
            }
        }

        if (!notEmpty($descripcion)) jsonResponse(false, 'La descripción del ítem es requerida');
        if ($cantidad <= 0) jsonResponse(false, 'La cantidad debe ser mayor a 0');

        $subtotalLinea = round($cantidad * $precio, 2);
        $orden = (int)($db->fetchColumn("SELECT COALESCE(MAX(orden),0) FROM cotizacion_items WHERE cotizacion_id = ?", [$id]) ?? 0) + 1;

        $itemId = $db->insert('cotizacion_items', [
            'cotizacion_id'   => $id,
            'producto_id'     => $productoId,
            'codigo'          => $codigo ?: null,
            'descripcion'     => $descripcion,
            'cantidad'        => $cantidad,
            'precio_unitario' => $precio,
            'subtotal_linea'  => $subtotalLinea,
            'orden'           => $orden,
        ]);

        $totales = recalcularTotales($db, $id);
        jsonResponse(true, 'Ítem agregado', ['item_id' => $itemId, 'subtotal_linea' => $subtotalLinea, 'totales' => $totales]);

    // ============================================================
    case 'eliminar_item':
    // ============================================================
        requireRole('tecnico');
        $itemId = (int)post('item_id');
        $item = $db->fetch("SELECT * FROM cotizacion_items WHERE id = ?", [$itemId]);
        if (!$item) jsonResponse(false, 'Ítem no encontrado');

        $cot = $db->fetch("SELECT * FROM cotizaciones WHERE id = ?", [$item['cotizacion_id']]);
        [$puede, $motivo] = puedeEditarCotizacion($cot, $user);
        if (!$puede) jsonResponse(false, $motivo);

        $db->delete('cotizacion_items', 'id = ?', [$itemId]);
        $totales = recalcularTotales($db, $item['cotizacion_id']);
        jsonResponse(true, 'Ítem eliminado', ['totales' => $totales]);

    // ============================================================
    case 'buscar_empresa':
    // ============================================================
        requireRole('tecnico');
        $q = post('q');
        if (strlen($q) < 2) jsonResponse(true, '', []);
        $rows = $db->fetchAll(
            "SELECT id, razon_social, ruc, direccion, contacto_nombre
             FROM empresas_clientes WHERE activo = 1 AND (razon_social LIKE ? OR ruc LIKE ?)
             ORDER BY razon_social LIMIT 10",
            ['%' . $q . '%', '%' . $q . '%']
        );
        jsonResponse(true, '', $rows);

    // ============================================================
    case 'buscar_producto':
    // ============================================================
        requireRole('tecnico');
        $q = post('q');
        if (strlen($q) < 2) jsonResponse(true, '', []);
        $rows = $db->fetchAll(
            "SELECT id, codigo, descripcion, precio_unitario
             FROM productos_servicios WHERE activo = 1 AND (codigo LIKE ? OR descripcion LIKE ?)
             ORDER BY descripcion LIMIT 10",
            ['%' . $q . '%', '%' . $q . '%']
        );
        jsonResponse(true, '', $rows);

    // ============================================================
    case 'guardar_empresa_nueva':
    // ============================================================
        requireRole('tecnico');
        $razon = post('razon_social');
        $ruc   = post('ruc');
        if (!notEmpty($razon)) jsonResponse(false, 'La razón social es requerida');

        try {
            $empresaId = $db->insert('empresas_clientes', [
                'razon_social'      => $razon,
                'ruc'               => $ruc ?: null,
                'direccion'         => post('direccion') ?: null,
                'contacto_nombre'   => post('contacto_nombre') ?: null,
                'contacto_telefono' => post('contacto_telefono') ?: null,
                'contacto_email'    => post('contacto_email') ?: null,
            ]);
            jsonResponse(true, 'Empresa registrada', ['id' => $empresaId, 'razon_social' => $razon]);
        } catch (Throwable $e) {
            jsonResponse(false, 'No se pudo registrar la empresa (¿RUC duplicado?)');
        }

    // ============================================================
    case 'cambiar_estado':
    // ============================================================
        requireRole('tecnico');
        $id = (int)post('id');
        $nuevoEstado = post('estado');

        $estadosValidos = ['borrador', 'enviada', 'aprobada', 'rechazada', 'vencida'];
        if (!in_array($nuevoEstado, $estadosValidos, true)) jsonResponse(false, 'Estado inválido');

        $cot = $db->fetch("SELECT * FROM cotizaciones WHERE id = ?", [$id]);
        if (!$cot) jsonResponse(false, 'Cotización no encontrada');

        if (!Session::hasRole('admin') && (int)$cot['usuario_id'] !== (int)$user['id']) {
            jsonResponse(false, 'No tienes permiso sobre esta cotización');
        }

        // Reglas de transición simples: desde 'aprobada' o 'rechazada' no se puede volver a 'borrador'
        if (in_array($cot['estado'], ['aprobada', 'rechazada'], true) && $nuevoEstado === 'borrador') {
            jsonResponse(false, 'No se puede regresar a borrador una cotización ya cerrada');
        }

        $db->update('cotizaciones', ['estado' => $nuevoEstado], 'id = ?', [$id]);
        auditLog('cotizacion_estado', 'cotizaciones', "Cotización {$cot['codigo']} → {$nuevoEstado}");
        jsonResponse(true, 'Estado actualizado');

    // ============================================================
    case 'finalizar':
    // ============================================================
        requireRole('tecnico');
        $id = (int)post('id');
        $pdfBase64 = $_POST['pdf_base64'] ?? '';

        $cot = $db->fetch("SELECT * FROM cotizaciones WHERE id = ?", [$id]);
        if (!$cot) jsonResponse(false, 'Cotización no encontrada');

        [$puede, $motivo] = puedeEditarCotizacion($cot, $user);
        if (!$puede) jsonResponse(false, $motivo);

        $totalItems = (int)$db->fetchColumn("SELECT COUNT(*) FROM cotizacion_items WHERE cotizacion_id = ?", [$id]);
        if ($totalItems === 0) jsonResponse(false, 'Agrega al menos un ítem antes de finalizar');
        if (!$pdfBase64) jsonResponse(false, 'No se recibió el PDF generado');

        try {
            $up = guardarBase64($pdfBase64, 'cotizaciones', ['application/pdf']);
            if (!$up['success']) throw new RuntimeException($up['error']);

            $db->update('cotizaciones', [
                'pdf_ruta' => $up['ruta'],
                'estado'   => 'enviada',
            ], 'id = ?', [$id]);

            // Si la cotización viene de un ticket, adjuntarla también ahí para trazabilidad
            if ($cot['ticket_id']) {
                $db->insert('adjuntos_ticket', [
                    'ticket_id'       => $cot['ticket_id'],
                    'usuario_id'      => $user['id'],
                    'nombre_original' => "Cotizacion_{$cot['codigo']}.pdf",
                    'nombre_archivo'  => $up['nombre_archivo'],
                    'ruta'            => $up['ruta'],
                    'mime_type'       => 'application/pdf',
                    'tamano'          => $up['tamano'],
                    'tipo'            => 'adjunto',
                ]);
                $db->insert('historial_ticket', [
                    'ticket_id'  => $cot['ticket_id'],
                    'usuario_id' => $user['id'],
                    'accion'     => 'cotizacion_generada',
                    'detalle'    => "Cotización {$cot['codigo']} generada y adjuntada",
                ]);
            }

            auditLog('cotizacion_finalizar', 'cotizaciones', "Cotización {$cot['codigo']} finalizada");
            jsonResponse(true, 'Cotización generada correctamente', ['codigo' => $cot['codigo']]);

        } catch (Throwable $e) {
            error_log('Finalizar cotización error: ' . $e->getMessage());
            jsonResponse(false, 'Error al generar la cotización');
        }

    // ============================================================
    case 'enviar_email':
    // ============================================================
        requireRole('tecnico');
        $id = (int)post('id');
        $cot = $db->fetch("SELECT * FROM cotizaciones WHERE id = ?", [$id]);
        if (!$cot) jsonResponse(false, 'Cotización no encontrada');
        if (!$cot['pdf_ruta']) jsonResponse(false, 'La cotización debe generarse primero');

        $email = post('email_destino');
        if (!isValidEmail($email)) jsonResponse(false, 'Correo de destino inválido');

        try {
            $urlPdf = UPLOADS_URL . '/' . $cot['pdf_ruta'];
            $mailer = new Mailer();
            $enviado = $mailer->send(
                $email,
                "Cotización {$cot['codigo']} — " . Config::empresa(),
                "<p>Hola <strong>" . e($cot['cliente_nombre']) . "</strong>,</p>
                 <p>Adjuntamos la cotización <strong>" . e($cot['codigo']) . "</strong> solicitada.</p>
                 <p><a href='{$urlPdf}' target='_blank'>Descargar Cotización (PDF)</a></p>",
                $cot['cliente_nombre']
            );

            $db->update('cotizaciones', [
                'enviado_email'    => $enviado ? 1 : 0,
                'enviado_email_at' => $enviado ? date('Y-m-d H:i:s') : null,
            ], 'id = ?', [$id]);

            if ($enviado) {
                auditLog('cotizacion_enviar_email', 'cotizaciones', "Cotización {$cot['codigo']} enviada por correo");
                jsonResponse(true, 'Cotización enviada por correo');
            }
            jsonResponse(false, 'No se pudo enviar el correo');

        } catch (Throwable $e) {
            error_log('Enviar cotización error: ' . $e->getMessage());
            jsonResponse(false, 'Error al enviar el correo');
        }

    // ============================================================
    case 'eliminar':
    // ============================================================
        requireRole('tecnico');
        $id = (int)post('id');
        $cot = $db->fetch("SELECT * FROM cotizaciones WHERE id = ?", [$id]);
        if (!$cot) jsonResponse(false, 'Cotización no encontrada');

        [$puede, $motivo] = puedeEditarCotizacion($cot, $user);
        if (!$puede) jsonResponse(false, $motivo);

        $db->delete('cotizaciones', 'id = ?', [$id]);
        auditLog('cotizacion_eliminar', 'cotizaciones', "Cotización {$cot['codigo']} eliminada (borrador)");
        jsonResponse(true, 'Cotización eliminada');

    default:
        jsonResponse(false, 'Acción no válida', null, 400);
}
