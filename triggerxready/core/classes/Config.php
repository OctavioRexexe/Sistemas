<?php
/**
 * TRIGGERX - Clase Config
 * Lee configuración desde BD con caché en sesión
 */
defined('TRIGGERX') or die('Acceso denegado');

class Config
{
    private static array $cache = [];
    private static bool $loaded = false;

    /** Cargar toda la configuración en caché */
    public static function load(): void
    {
        if (self::$loaded) return;

        try {
            $db = Database::getInstance();
            $rows = $db->fetchAll("SELECT clave, valor FROM configuracion");
            foreach ($rows as $row) {
                self::$cache[$row['clave']] = $row['valor'];
            }
        } catch (Throwable $e) {
            // Si falla la BD, usar defaults
            error_log('Config load error: ' . $e->getMessage());
        }
        self::$loaded = true;
    }

    /** Obtener valor de configuración */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$loaded) self::load();
        return self::$cache[$key] ?? $default;
    }

    /** Establecer/actualizar configuración */
    public static function set(string $key, string $value): bool
    {
        try {
            $db = Database::getInstance();
            $db->query(
                "INSERT INTO configuracion (clave, valor) VALUES (?, ?) 
                 ON DUPLICATE KEY UPDATE valor = VALUES(valor)",
                [$key, $value]
            );
            self::$cache[$key] = $value;
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** Obtener color primario */
    public static function colorPrimario(): string
    {
        return self::get('color_primario', '#F4A020');
    }

    /** Obtener nombre empresa */
    public static function empresa(): string
    {
        return self::get('empresa_nombre', APP_NAME);
    }

    /** Obtener logo */
    public static function logo(): string
    {
        $logo = self::get('empresa_logo', '');
        return $logo ? ASSETS_URL . '/img/' . $logo : '';
    }

    /** Obtener prefijo de tickets */
    public static function prefijo(): string
    {
        return self::get('ticket_prefijo', 'TRG');
    }

    /** Configuración SLA */
    public static function sla(): array
    {
        return [
            'max_dias'    => (int) self::get('sla_dias_maximos', 15),
            'preventiva'  => (int) self::get('sla_alerta_preventiva', 5),
            'importante'  => (int) self::get('sla_alerta_importante', 10),
            'critica'     => (int) self::get('sla_alerta_critica', 15),
        ];
    }

    /** Configuración de límites de edición de borrador (Constancias Técnicas) */
    public static function constancia(): array
    {
        return [
            'horas_edicion'  => (int) self::get('constancia_horas_edicion', 24),
            'max_ediciones'  => (int) self::get('constancia_max_ediciones', 5),
            'prefijo'        => self::get('constancia_prefijo', 'CT'),
        ];
    }

    /** Configuración de Cotizaciones */
    public static function cotizacion(): array
    {
        return [
            'prefijo'      => self::get('cotizacion_prefijo', 'COT'),
            'igv_pct'      => (float) self::get('cotizacion_igv_pct', 18),
            'dias_validez' => (int) self::get('cotizacion_dias_validez', 15),
        ];
    }
}
