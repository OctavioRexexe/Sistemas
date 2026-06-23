-- ============================================================
-- TRIGGERX ENTERPRISE PLATFORM - DATABASE SCHEMA
-- Version: 1.0.0
-- Compatible: MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- ============================================================
-- DATABASE
-- ============================================================
CREATE DATABASE IF NOT EXISTS `triggerx` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `triggerx`;

-- ============================================================
-- TABLA: roles
-- ============================================================
CREATE TABLE `roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(50) NOT NULL,
  `slug` VARCHAR(50) NOT NULL,
  `descripcion` TEXT DEFAULT NULL,
  `nivel` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=Cliente,2=Tecnico,3=Admin,4=SuperAdmin',
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`nombre`, `slug`, `descripcion`, `nivel`) VALUES
('SuperAdmin', 'superadmin', 'Control total del sistema', 4),
('Administrador', 'admin', 'Administración general', 3),
('Técnico', 'tecnico', 'Gestión de tickets asignados', 2),
('Cliente', 'cliente', 'Creación y consulta de tickets', 1);

-- ============================================================
-- TABLA: usuarios
-- ============================================================
CREATE TABLE `usuarios` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rol_id` INT UNSIGNED NOT NULL DEFAULT 4,
  `nombre` VARCHAR(100) NOT NULL,
  `apellido` VARCHAR(100) NOT NULL,
  `email` VARCHAR(180) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `telefono` VARCHAR(20) DEFAULT NULL,
  `empresa` VARCHAR(150) DEFAULT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `token_remember` VARCHAR(255) DEFAULT NULL,
  `token_reset` VARCHAR(255) DEFAULT NULL,
  `token_reset_exp` DATETIME DEFAULT NULL,
  `ultimo_login` DATETIME DEFAULT NULL,
  `intentos_login` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `bloqueado_hasta` DATETIME DEFAULT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`),
  KEY `fk_usuarios_rol` (`rol_id`),
  CONSTRAINT `fk_usuarios_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- IMPORTANTE: Ejecutar reset_password.php para establecer contraseñas
-- O importar el SQL y luego acceder a reset_password.php
INSERT INTO `usuarios` (`rol_id`, `nombre`, `apellido`, `email`, `password`, `empresa`, `activo`) VALUES
(1, 'Super', 'Administrador', 'superadmin@triggerx.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'TriggerX', 1),
(2, 'Admin', 'Principal', 'admin@triggerx.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'TriggerX', 1),
(3, 'Técnico', 'Soporte', 'tecnico@triggerx.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'TriggerX', 1);

-- ============================================================
-- TABLA: modulos
-- ============================================================
CREATE TABLE `modulos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `descripcion` TEXT DEFAULT NULL,
  `icono` VARCHAR(50) NOT NULL DEFAULT 'bi-grid',
  `url` VARCHAR(255) NOT NULL,
  `orden` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `instalado` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `modulos` (`nombre`, `slug`, `descripcion`, `icono`, `url`, `orden`, `activo`) VALUES
('Mesa de Ayuda', 'tickets', 'Sistema de tickets y soporte técnico', 'bi-headset', '/modules/tickets/', 1, 1),
('Inventario', 'inventario', 'Control de inventario (próximamente)', 'bi-box-seam', '/modules/inventario/', 2, 0),
('Facturación', 'facturacion', 'Sistema de facturación (próximamente)', 'bi-receipt', '/modules/facturacion/', 3, 0),
('CRM', 'crm', 'Gestión de clientes (próximamente)', 'bi-people', '/modules/crm/', 4, 0);

-- ============================================================
-- TABLA: permisos_modulos (acceso por usuario/rol a módulos)
-- ============================================================
CREATE TABLE `permisos_modulos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT UNSIGNED DEFAULT NULL,
  `rol_id` INT UNSIGNED DEFAULT NULL,
  `modulo_id` INT UNSIGNED NOT NULL,
  `puede_ver` TINYINT(1) NOT NULL DEFAULT 1,
  `puede_crear` TINYINT(1) NOT NULL DEFAULT 0,
  `puede_editar` TINYINT(1) NOT NULL DEFAULT 0,
  `puede_eliminar` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_pm_usuario` (`usuario_id`),
  KEY `fk_pm_rol` (`rol_id`),
  KEY `fk_pm_modulo` (`modulo_id`),
  CONSTRAINT `fk_pm_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pm_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pm_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `modulos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dar acceso al módulo tickets a todos los roles
INSERT INTO `permisos_modulos` (`rol_id`, `modulo_id`, `puede_ver`, `puede_crear`, `puede_editar`, `puede_eliminar`) VALUES
(1, 1, 1, 1, 1, 1), -- SuperAdmin: todos los permisos tickets
(2, 1, 1, 1, 1, 0), -- Admin: ver/crear/editar tickets
(3, 1, 1, 1, 1, 0), -- Técnico: ver/crear/editar tickets
(4, 1, 1, 1, 0, 0); -- Cliente: ver/crear

-- ============================================================
-- TABLA: estados_ticket
-- ============================================================
CREATE TABLE `estados_ticket` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(50) NOT NULL,
  `slug` VARCHAR(50) NOT NULL,
  `color` VARCHAR(20) NOT NULL DEFAULT '#6c757d',
  `icono` VARCHAR(50) NOT NULL DEFAULT 'bi-circle',
  `orden` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `es_final` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `estados_ticket` (`nombre`, `slug`, `color`, `icono`, `orden`, `es_final`) VALUES
('Abierto', 'abierto', '#0d6efd', 'bi-circle-fill', 1, 0),
('En Proceso', 'en_proceso', '#F4A020', 'bi-gear-fill', 2, 0),
('Esperando Cliente', 'esperando', '#6f42c1', 'bi-hourglass-split', 3, 0),
('Resuelto', 'resuelto', '#198754', 'bi-check-circle-fill', 4, 1),
('Cerrado', 'cerrado', '#6c757d', 'bi-x-circle-fill', 5, 1);

-- ============================================================
-- TABLA: prioridades_ticket
-- ============================================================
CREATE TABLE `prioridades_ticket` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(50) NOT NULL,
  `slug` VARCHAR(50) NOT NULL,
  `color` VARCHAR(20) NOT NULL,
  `nivel` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `sla_horas` SMALLINT UNSIGNED NOT NULL DEFAULT 72 COMMENT 'SLA en horas',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `prioridades_ticket` (`nombre`, `slug`, `color`, `nivel`, `sla_horas`) VALUES
('Baja', 'baja', '#198754', 1, 120),
('Media', 'media', '#F4A020', 2, 72),
('Alta', 'alta', '#fd7e14', 3, 24),
('Crítica', 'critica', '#dc3545', 4, 8);

-- ============================================================
-- TABLA: categorias_ticket
-- ============================================================
CREATE TABLE `categorias_ticket` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL,
  `descripcion` TEXT DEFAULT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categorias_ticket` (`nombre`) VALUES
('Soporte Técnico'),('Hardware'),('Software'),('Red / Conectividad'),('Correo Electrónico'),('Facturación'),('Consulta General'),('Otro');

-- ============================================================
-- TABLA: tickets
-- ============================================================
CREATE TABLE `tickets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(20) NOT NULL COMMENT 'TRG-000001',
  `usuario_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL si es cliente externo',
  `estado_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `prioridad_id` INT UNSIGNED NOT NULL DEFAULT 2,
  `categoria_id` INT UNSIGNED DEFAULT NULL,
  `tecnico_id` INT UNSIGNED DEFAULT NULL,
  -- Datos del cliente
  `cliente_nombre` VARCHAR(150) NOT NULL,
  `cliente_empresa` VARCHAR(150) DEFAULT NULL,
  `cliente_telefono` VARCHAR(25) DEFAULT NULL,
  `cliente_email` VARCHAR(180) NOT NULL,
  -- Datos del ticket
  `asunto` VARCHAR(255) NOT NULL,
  `descripcion` LONGTEXT NOT NULL,
  `serie_equipo` VARCHAR(100) DEFAULT NULL,
  `modelo_equipo` VARCHAR(100) DEFAULT NULL,
  -- SLA
  `sla_limite` DATETIME DEFAULT NULL COMMENT 'Fecha límite SLA',
  `sla_estado` ENUM('verde','amarillo','naranja','rojo') NOT NULL DEFAULT 'verde',
  `sla_vencido` TINYINT(1) NOT NULL DEFAULT 0,
  -- Control
  `origen` ENUM('publico','interno','email') NOT NULL DEFAULT 'publico',
  `primera_respuesta` DATETIME DEFAULT NULL,
  `fecha_resolucion` DATETIME DEFAULT NULL,
  `tiempo_atencion` INT UNSIGNED DEFAULT NULL COMMENT 'Minutos totales',
  -- Timestamps
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_codigo` (`codigo`),
  KEY `fk_ticket_estado` (`estado_id`),
  KEY `fk_ticket_prioridad` (`prioridad_id`),
  KEY `fk_ticket_categoria` (`categoria_id`),
  KEY `fk_ticket_tecnico` (`tecnico_id`),
  KEY `fk_ticket_usuario` (`usuario_id`),
  CONSTRAINT `fk_ticket_estado` FOREIGN KEY (`estado_id`) REFERENCES `estados_ticket` (`id`),
  CONSTRAINT `fk_ticket_prioridad` FOREIGN KEY (`prioridad_id`) REFERENCES `prioridades_ticket` (`id`),
  CONSTRAINT `fk_ticket_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias_ticket` (`id`),
  CONSTRAINT `fk_ticket_tecnico` FOREIGN KEY (`tecnico_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ticket_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA: comentarios_ticket
-- ============================================================
CREATE TABLE `comentarios_ticket` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT UNSIGNED NOT NULL,
  `usuario_id` INT UNSIGNED DEFAULT NULL,
  `autor_nombre` VARCHAR(150) DEFAULT NULL COMMENT 'Para clientes externos',
  `contenido` LONGTEXT NOT NULL,
  `tipo` ENUM('publico','interno','solucion','sistema') NOT NULL DEFAULT 'publico',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_com_ticket` (`ticket_id`),
  KEY `fk_com_usuario` (`usuario_id`),
  CONSTRAINT `fk_com_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA: adjuntos_ticket
-- ============================================================
CREATE TABLE `adjuntos_ticket` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT UNSIGNED NOT NULL,
  `comentario_id` INT UNSIGNED DEFAULT NULL,
  `usuario_id` INT UNSIGNED DEFAULT NULL,
  `nombre_original` VARCHAR(255) NOT NULL,
  `nombre_archivo` VARCHAR(255) NOT NULL,
  `ruta` VARCHAR(500) NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `tamano` INT UNSIGNED NOT NULL DEFAULT 0,
  `tipo` ENUM('adjunto','evidencia','factura') NOT NULL DEFAULT 'adjunto',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_adj_ticket` (`ticket_id`),
  KEY `fk_adj_comentario` (`comentario_id`),
  CONSTRAINT `fk_adj_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_adj_comentario` FOREIGN KEY (`comentario_id`) REFERENCES `comentarios_ticket` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA: historial_ticket
-- ============================================================
CREATE TABLE `historial_ticket` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT UNSIGNED NOT NULL,
  `usuario_id` INT UNSIGNED DEFAULT NULL,
  `accion` VARCHAR(100) NOT NULL,
  `detalle` TEXT DEFAULT NULL,
  `valor_anterior` VARCHAR(255) DEFAULT NULL,
  `valor_nuevo` VARCHAR(255) DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_hist_ticket` (`ticket_id`),
  CONSTRAINT `fk_hist_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA: notificaciones
-- ============================================================
CREATE TABLE `notificaciones` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT UNSIGNED NOT NULL,
  `tipo` VARCHAR(50) NOT NULL,
  `titulo` VARCHAR(255) NOT NULL,
  `mensaje` TEXT NOT NULL,
  `url` VARCHAR(500) DEFAULT NULL,
  `leida` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_noti_usuario` (`usuario_id`),
  CONSTRAINT `fk_noti_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA: logs_auditoria
-- ============================================================
CREATE TABLE `logs_auditoria` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT UNSIGNED DEFAULT NULL,
  `accion` VARCHAR(100) NOT NULL,
  `modulo` VARCHAR(50) DEFAULT NULL,
  `descripcion` TEXT DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_log_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA: configuracion
-- ============================================================
CREATE TABLE `configuracion` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `clave` VARCHAR(100) NOT NULL,
  `valor` TEXT DEFAULT NULL,
  `tipo` ENUM('texto','numero','color','bool','json','email') NOT NULL DEFAULT 'texto',
  `grupo` VARCHAR(50) NOT NULL DEFAULT 'general',
  `descripcion` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `configuracion` (`clave`, `valor`, `tipo`, `grupo`, `descripcion`) VALUES
-- General
('empresa_nombre', 'TriggerX Enterprise', 'texto', 'general', 'Nombre de la empresa'),
('empresa_logo', '', 'texto', 'general', 'Ruta del logo'),
('empresa_slogan', 'Mesa de Ayuda Profesional', 'texto', 'general', 'Slogan'),
('empresa_email', 'soporte@triggerx.com', 'email', 'general', 'Email de soporte'),
('empresa_telefono', '', 'texto', 'general', 'Teléfono'),
('empresa_web', '', 'texto', 'general', 'Sitio web'),
-- Colores
('color_primario', '#F4A020', 'color', 'apariencia', 'Color principal'),
('color_secundario', '#1a1a2e', 'color', 'apariencia', 'Color secundario'),
('color_navbar', '#F4A020', 'color', 'apariencia', 'Color del navbar'),
-- SLA
('sla_dias_maximos', '15', 'numero', 'sla', 'Días máximos ticket abierto'),
('sla_alerta_preventiva', '5', 'numero', 'sla', 'Días alerta preventiva'),
('sla_alerta_importante', '10', 'numero', 'sla', 'Días alerta importante'),
('sla_alerta_critica', '15', 'numero', 'sla', 'Días alerta crítica'),
-- SMTP
('smtp_host', '', 'texto', 'smtp', 'Servidor SMTP'),
('smtp_port', '587', 'numero', 'smtp', 'Puerto SMTP'),
('smtp_usuario', '', 'texto', 'smtp', 'Usuario SMTP'),
('smtp_password', '', 'texto', 'smtp', 'Contraseña SMTP'),
('smtp_cifrado', 'tls', 'texto', 'smtp', 'Cifrado: tls/ssl'),
('smtp_from_name', 'TriggerX Soporte', 'texto', 'smtp', 'Nombre remitente'),
('smtp_activo', '0', 'bool', 'smtp', 'Usar SMTP (0=mail nativo)'),
-- Tickets
('ticket_prefijo', 'TRG', 'texto', 'tickets', 'Prefijo tickets'),
('ticket_auto_asignar', '0', 'bool', 'tickets', 'Auto-asignar tickets'),
('ticket_cerrar_dias', '3', 'numero', 'tickets', 'Días para cierre automático resueltos'),
-- Sistema
('version', '1.0.0', 'texto', 'sistema', 'Versión del sistema'),
('instalado', '1', 'bool', 'sistema', 'Sistema instalado'),
('modo_mantenimiento', '0', 'bool', 'sistema', 'Modo mantenimiento');

-- ============================================================
-- TABLA: sesiones (para control adicional)
-- ============================================================
CREATE TABLE `sesiones_activas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT UNSIGNED NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `last_activity` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `expira_en` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_ses_usuario` (`usuario_id`),
  KEY `idx_token` (`token`(64)),
  CONSTRAINT `fk_ses_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- VISTA: v_tickets_dashboard
-- ============================================================
CREATE OR REPLACE VIEW `v_tickets_dashboard` AS
SELECT
  t.id,
  t.codigo,
  t.cliente_nombre,
  t.cliente_email,
  t.cliente_empresa,
  t.asunto,
  t.created_at,
  t.updated_at,
  t.sla_limite,
  t.sla_estado,
  t.sla_vencido,
  t.primera_respuesta,
  t.fecha_resolucion,
  t.serie_equipo,
  t.modelo_equipo,
  e.nombre AS estado_nombre,
  e.slug AS estado_slug,
  e.color AS estado_color,
  p.nombre AS prioridad_nombre,
  p.color AS prioridad_color,
  p.nivel AS prioridad_nivel,
  c.nombre AS categoria_nombre,
  CONCAT(u.nombre, ' ', u.apellido) AS tecnico_nombre,
  u.email AS tecnico_email,
  TIMESTAMPDIFF(HOUR, t.created_at, NOW()) AS horas_abierto,
  TIMESTAMPDIFF(DAY, t.created_at, NOW()) AS dias_abierto
FROM tickets t
LEFT JOIN estados_ticket e ON t.estado_id = e.id
LEFT JOIN prioridades_ticket p ON t.prioridad_id = p.id
LEFT JOIN categorias_ticket c ON t.categoria_id = c.id
LEFT JOIN usuarios u ON t.tecnico_id = u.id;

-- ============================================================
-- ACTUALIZACIÓN v1.0.1: Agregar campo modelo_equipo
-- Ejecutar solo si ya instalaste la versión anterior
-- ============================================================
-- ALTER TABLE `tickets` ADD COLUMN `modelo_equipo` VARCHAR(100) DEFAULT NULL AFTER `serie_equipo`;
