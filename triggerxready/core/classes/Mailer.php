<?php
/**
 * TRIGGERX - Clase Mailer
 * Envío de correos: mail() nativo o SMTP manual
 * No requiere Composer — compatible con hosting compartido
 */
defined('TRIGGERX') or die('Acceso denegado');

class Mailer
{
    private string $fromEmail;
    private string $fromName;
    private bool   $useSmtp;
    private array  $smtpConfig;

    public function __construct()
    {
        $this->fromEmail  = Config::get('empresa_email', 'no-reply@triggerx.com');
        $this->fromName   = Config::get('smtp_from_name', Config::empresa());
        $this->useSmtp    = (bool)(int)Config::get('smtp_activo', 0);
        $this->smtpConfig = [
            'host'     => Config::get('smtp_host', ''),
            'port'     => (int)Config::get('smtp_port', 587),
            'usuario'  => Config::get('smtp_usuario', ''),
            'password' => Config::get('smtp_password', ''),
            'cifrado'  => Config::get('smtp_cifrado', 'tls'),
        ];
    }

    /**
     * Enviar email
     */
    public function send(string $to, string $subject, string $htmlBody, string $toName = ''): bool
    {
        if ($this->useSmtp && !empty($this->smtpConfig['host'])) {
            return $this->sendSmtp($to, $subject, $htmlBody, $toName);
        }
        return $this->sendNative($to, $subject, $htmlBody, $toName);
    }

    /**
     * Enviar usando mail() nativo de PHP (funciona en cPanel sin config extra)
     */
    private function sendNative(string $to, string $subject, string $html, string $toName): bool
    {
        $boundary = md5(uniqid(time(), true));
        $toHeader = $toName ? "{$toName} <{$to}>" : $to;

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $headers .= "From: {$this->fromName} <{$this->fromEmail}>\r\n";
        $headers .= "Reply-To: {$this->fromEmail}\r\n";
        $headers .= "X-Mailer: TriggerX/1.0\r\n";

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode(strip_tags($html))) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($html)) . "\r\n";
        $body .= "--{$boundary}--";

        $result = @mail($toHeader, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);

        if (!$result) {
            error_log("Mailer native error: failed to send to {$to}");
        }
        return (bool)$result;
    }

    /**
     * Enviar vía SMTP con socket (sin librerías externas)
     */
    private function sendSmtp(string $to, string $subject, string $html, string $toName): bool
    {
        $cfg  = $this->smtpConfig;
        $host = ($cfg['cifrado'] === 'ssl' ? 'ssl://' : '') . $cfg['host'];

        try {
            $socket = fsockopen($host, $cfg['port'], $errno, $errstr, 10);
            if (!$socket) {
                throw new RuntimeException("No se pudo conectar al SMTP: {$errstr}");
            }

            $read = fn() => fgets($socket, 515);
            $write = function(string $cmd) use ($socket) {
                fwrite($socket, $cmd . "\r\n");
            };

            $read(); // Banner
            $write("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            while ($line = $read()) {
                if (substr($line, 3, 1) === ' ') break;
            }

            // STARTTLS
            if ($cfg['cifrado'] === 'tls') {
                $write("STARTTLS");
                $read();
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $write("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
                while ($line = $read()) {
                    if (substr($line, 3, 1) === ' ') break;
                }
            }

            // Auth
            $write("AUTH LOGIN");
            $read();
            $write(base64_encode($cfg['usuario']));
            $read();
            $write(base64_encode($cfg['password']));
            $authResponse = $read();
            if (substr($authResponse, 0, 3) !== '235') {
                throw new RuntimeException("SMTP Auth failed: {$authResponse}");
            }

            $write("MAIL FROM:<{$this->fromEmail}>");
            $read();
            $write("RCPT TO:<{$to}>");
            $read();
            $write("DATA");
            $read();

            $boundary = md5(uniqid());
            $msg  = "From: {$this->fromName} <{$this->fromEmail}>\r\n";
            $msg .= "To: " . ($toName ? "{$toName} <{$to}>" : $to) . "\r\n";
            $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $msg .= "MIME-Version: 1.0\r\n";
            $msg .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n";
            $msg .= "--{$boundary}\r\n";
            $msg .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
            $msg .= chunk_split(base64_encode(strip_tags($html))) . "\r\n";
            $msg .= "--{$boundary}\r\n";
            $msg .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
            $msg .= chunk_split(base64_encode($html)) . "\r\n";
            $msg .= "--{$boundary}--\r\n";
            $msg .= ".";

            $write($msg);
            $dataResponse = $read();
            $write("QUIT");
            fclose($socket);

            return substr($dataResponse, 0, 3) === '250';

        } catch (Throwable $e) {
            error_log("Mailer SMTP error: " . $e->getMessage());
            // Fallback a mail() nativo
            return $this->sendNative($to, $subject, $html, $toName);
        }
    }

    // ============================================================
    // TEMPLATES DE EMAIL
    // ============================================================

    /**
     * Template base HTML para emails
     */
    private function template(string $titulo, string $contenido, string $botonTexto = '', string $botonUrl = ''): string
    {
        $empresa = Config::empresa();
        $color   = Config::colorPrimario();
        $btn     = $botonTexto ? "
            <div style='text-align:center;margin:28px 0'>
                <a href='{$botonUrl}'
                   style='background:{$color};color:#fff;padding:12px 28px;border-radius:8px;
                          text-decoration:none;font-weight:700;font-size:14px;display:inline-block'>
                    {$botonTexto}
                </a>
            </div>" : '';

        return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head>
        <body style='margin:0;padding:0;background:#f4f5f7;font-family:Arial,sans-serif'>
        <table width='100%' cellpadding='0' cellspacing='0' style='background:#f4f5f7;padding:30px 0'>
            <tr><td align='center'>
            <table width='580' cellpadding='0' cellspacing='0' style='background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)'>
                <tr><td style='background:{$color};padding:20px 32px;text-align:center'>
                    <span style='color:#fff;font-size:20px;font-weight:900'>⚡ {$empresa}</span>
                </td></tr>
                <tr><td style='padding:32px'>
                    <h2 style='margin:0 0 16px;font-size:20px;color:#1a1a2e'>{$titulo}</h2>
                    <div style='font-size:14px;line-height:1.7;color:#444'>{$contenido}</div>
                    {$btn}
                    <hr style='border:none;border-top:1px solid #eee;margin:24px 0'>
                    <p style='font-size:12px;color:#999;margin:0'>{$empresa} — Sistema de Mesa de Ayuda<br>Este es un mensaje automático, por favor no respondas.</p>
                </td></tr>
            </table>
            </td></tr>
        </table>
        </body></html>";
    }

    /**
     * Email: Ticket creado (al cliente)
     */
    public function ticketCreado(string $email, string $nombre, string $codigo, string $asunto): bool
    {
        $url = BASE_URL . '/public/ticket/consultar.php?codigo=' . urlencode($codigo);
        $contenido = "
            <p>Hola <strong>{$nombre}</strong>,</p>
            <p>Tu solicitud de soporte ha sido registrada correctamente.</p>
            <table style='width:100%;border-collapse:collapse;margin:16px 0'>
                <tr><td style='padding:8px 12px;background:#f9f9f9;font-weight:600;width:40%'>Número de Ticket</td>
                    <td style='padding:8px 12px;background:#f9f9f9;font-family:monospace;font-weight:700;color:" . Config::colorPrimario() . "'>{$codigo}</td></tr>
                <tr><td style='padding:8px 12px;font-weight:600'>Asunto</td>
                    <td style='padding:8px 12px'>{$asunto}</td></tr>
            </table>
            <p>Guarda tu número de ticket para consultar el estado en cualquier momento.</p>";

        $html = $this->template('Ticket de Soporte Creado', $contenido, 'Ver Estado de mi Ticket', $url);
        return $this->send($email, "Ticket {$codigo} — {$asunto}", $html, $nombre);
    }

    /**
     * Email: Ticket actualizado
     */
    public function ticketActualizado(string $email, string $nombre, string $codigo, string $asunto, string $estado): bool
    {
        $url = BASE_URL . '/public/ticket/consultar.php?codigo=' . urlencode($codigo);
        $contenido = "
            <p>Hola <strong>{$nombre}</strong>,</p>
            <p>Tu ticket <strong>{$codigo}</strong> ha sido actualizado.</p>
            <p><strong>Nuevo estado:</strong> <span style='background:#F4A020;color:#fff;padding:3px 10px;border-radius:6px;font-size:13px'>{$estado}</span></p>
            <p>Ingresa al portal para ver los detalles y respuestas de nuestro equipo técnico.</p>";

        $html = $this->template('Tu Ticket fue Actualizado', $contenido, 'Ver mi Ticket', $url);
        return $this->send($email, "Actualización Ticket {$codigo}", $html, $nombre);
    }

    /**
     * Email: SLA próximo a vencer (al técnico)
     */
    public function slaAlerta(string $email, string $nombre, string $codigo, string $asunto, int $dias, string $nivel): bool
    {
        $url    = BASE_URL . '/modules/tickets/detalle.php?id=0&codigo=' . urlencode($codigo);
        $colores = ['preventiva' => '#ffc107', 'importante' => '#fd7e14', 'critica' => '#dc3545'];
        $color  = $colores[$nivel] ?? '#dc3545';
        $iconos = ['preventiva' => '⚠️', 'importante' => '🔶', 'critica' => '🚨'];
        $icono  = $iconos[$nivel] ?? '🚨';

        $contenido = "
            <p>Hola <strong>{$nombre}</strong>,</p>
            <p>{$icono} El siguiente ticket está <strong style='color:{$color}'>próximo a vencer su SLA</strong>:</p>
            <table style='width:100%;border-collapse:collapse;margin:16px 0'>
                <tr><td style='padding:8px 12px;background:#f9f9f9;font-weight:600'>Ticket</td>
                    <td style='padding:8px 12px;background:#f9f9f9;font-family:monospace;font-weight:700'>{$codigo}</td></tr>
                <tr><td style='padding:8px 12px;font-weight:600'>Asunto</td>
                    <td style='padding:8px 12px'>{$asunto}</td></tr>
                <tr><td style='padding:8px 12px;background:#f9f9f9;font-weight:600'>Días abierto</td>
                    <td style='padding:8px 12px;background:#f9f9f9;font-weight:700;color:{$color}'>{$dias} día(s)</td></tr>
            </table>
            <p>Por favor atiende este ticket a la brevedad para cumplir con el SLA.</p>";

        $html = $this->template("{$icono} Alerta SLA — Ticket {$codigo}", $contenido, 'Atender Ticket', $url);
        return $this->send($email, "{$icono} Alerta SLA: Ticket {$codigo}", $html, $nombre);
    }

    /**
     * Email: Ticket resuelto (al cliente)
     */
    public function ticketResuelto(string $email, string $nombre, string $codigo, string $asunto): bool
    {
        $url = BASE_URL . '/public/ticket/consultar.php?codigo=' . urlencode($codigo);
        $contenido = "
            <p>Hola <strong>{$nombre}</strong>,</p>
            <p>✅ Tu ticket <strong>{$codigo}</strong> ha sido <strong>resuelto</strong>.</p>
            <p><strong>Asunto:</strong> {$asunto}</p>
            <p>Puedes ver la solución y los detalles completos en el portal de soporte.</p>
            <p>Si el problema persiste, puedes reabrir el ticket o crear uno nuevo.</p>";

        $html = $this->template('✅ Tu Ticket fue Resuelto', $contenido, 'Ver Solución', $url);
        return $this->send($email, "✅ Resuelto: Ticket {$codigo}", $html, $nombre);
    }
}
