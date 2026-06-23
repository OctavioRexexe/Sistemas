# TriggerX Enterprise Platform v1.0.0
## Guía de Instalación — WAMP + cPanel

---

## INSTALACIÓN EN WAMP (Windows / Desarrollo)

### Paso 1 — Copiar archivos

Copia la carpeta `triggerx/` dentro de `C:\wamp64\www\`:
```
C:\wamp64\www\
    └── triggerx\
        ├── index.php
        ├── login.php
        ├── dashboard.php
        ├── .htaccess
        ├── core\
        ├── modules\
        ├── assets\
        └── ...
```

### Paso 2 — Habilitar mod_rewrite en WAMP

1. Clic izquierdo en ícono WAMP (barra de tareas)
2. **Apache → Apache Modules → rewrite_module** → activar ✓
3. Reiniciar WAMP

### Paso 3 — Verificar AllowOverride en httpd.conf

1. WAMP → Apache → httpd.conf
2. Buscar la sección `<Directory "C:/wamp64/www/">`
3. Cambiar `AllowOverride None` por `AllowOverride All`
4. Guardar y reiniciar WAMP

### Paso 4 — Crear la Base de Datos

1. Abrir **phpMyAdmin**: http://localhost/phpmyadmin
2. Crear base de datos: `triggerx` (cotejamiento: `utf8mb4_unicode_ci`)
3. Seleccionar la BD `triggerx` → pestaña **Importar**
4. Subir el archivo: `sql/triggerx_install.sql`
5. Clic en **Continuar/Ejecutar**

### Paso 5 — Configurar config.php

Editar `core/config/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'triggerx');
define('DB_USER', 'root');    // WAMP default
define('DB_PASS', '');        // WAMP default (vacío)
```

Si el proyecto NO está en `localhost/triggerx/`, ajustar BASE_URL:
```php
// Descomentar y ajustar esta línea:
define('BASE_URL', 'http://localhost/triggerx');
```

### Paso 6 — Establecer Contraseñas

1. Abrir: http://localhost/triggerx/reset_password.php
2. Ejecutará el script y fijará la contraseña **Admin2024!** a todos los usuarios
3. **Eliminar** el archivo `reset_password.php` después

### Paso 7 — Verificar instalación

Abrir: http://localhost/triggerx/diagnostico.php  
Verifica: PHP version, módulos, BD, rutas  
**Eliminar** el archivo después.

### Paso 8 — Acceder al sistema

URL: **http://localhost/triggerx/**

| Email | Contraseña | Rol |
|---|---|---|
| superadmin@triggerx.com | Admin2024! | SuperAdmin |
| admin@triggerx.com | Admin2024! | Administrador |
| tecnico@triggerx.com | Admin2024! | Técnico |

---

## INSTALACIÓN EN cPanel (Producción)

### Paso 1 — Subir archivos

Subir contenido de `triggerx/` a `public_html/` vía FTP o File Manager.

### Paso 2 — Base de datos

cPanel → MySQL Databases:
1. Crear base de datos: `usuario_triggerx`
2. Crear usuario MySQL con contraseña segura
3. Asignar **todos los privilegios**
4. phpMyAdmin → importar `sql/triggerx_install.sql`

### Paso 3 — Configurar

Editar `core/config/config.php`:
```php
define('DB_NAME', 'usuario_triggerx');
define('DB_USER', 'usuario_dbuser');
define('DB_PASS', 'tu_password_segura');
define('DEBUG_MODE', false);  // ← false en producción
```

### Paso 4 — Cron Job SLA

cPanel → Cron Jobs:
```
0 8 * * * php /home/tu_usuario/public_html/cron/sla_check.php
```

### Paso 5 — Permisos de carpetas

```
assets/uploads/     → 755
assets/uploads/tickets/ → 755
assets/uploads/logos/   → 755
```

---

## SOLUCIÓN DE ERRORES COMUNES

### Error 500 en WAMP
**Causa más común:** mod_rewrite no habilitado o AllowOverride None  
**Solución:** Ver Paso 2 y Paso 3 de instalación WAMP

### Error 500 con .htaccess en WAMP
Si persiste el error 500, reemplaza el contenido de `.htaccess` con:
```apache
Options -Indexes
AddDefaultCharset UTF-8

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^core/(?!ajax/) - [F,L]
</IfModule>
```

### "Undefined BASE_URL" o rutas rotas
Descomentar en `core/config/config.php`:
```php
define('BASE_URL', 'http://localhost/triggerx');
```

### No puedo hacer login (contraseña incorrecta)
Ejecutar: http://localhost/triggerx/reset_password.php

### Error de conexión a BD
Verificar en `config.php`: DB_HOST, DB_NAME, DB_USER, DB_PASS  
Verificar que la BD `triggerx` existe en phpMyAdmin

---

## ESTRUCTURA DEL PROYECTO

```
triggerx/
├── core/                    # Núcleo del sistema
│   ├── ajax/                # Endpoints AJAX del core
│   ├── classes/             # Database, Session, Config, Mailer
│   ├── config/              # config.php (configuración principal)
│   ├── helpers/             # functions.php, security.php
│   └── layout/              # header.php, footer.php
├── modules/
│   └── tickets/             # Módulo Mesa de Ayuda
│       ├── ajax/            # listar.php, acciones.php, export.php
│       ├── index.php        # Listado de tickets
│       ├── detalle.php      # Detalle del ticket
│       └── reportes.php     # Reportes y estadísticas
├── admin/
│   ├── users.php            # Gestión usuarios
│   ├── roles.php            # Gestión roles
│   ├── modules.php          # Gestión módulos
│   ├── settings.php         # Configuración del sistema
│   ├── logs.php             # Auditoría
│   └── profile.php          # Perfil de usuario
├── public/ticket/
│   ├── nuevo.php            # Formulario público
│   └── consultar.php        # Consulta pública
├── cron/
│   └── sla_check.php        # Verificación SLA automática
├── assets/
│   ├── css/triggerx.css     # Estilos globales
│   ├── js/triggerx.js       # JavaScript global
│   └── uploads/             # Archivos subidos (protegidos)
├── errors/
│   ├── 403.php
│   └── maintenance.php
├── sql/
│   └── triggerx_install.sql # Schema completo
├── index.php                # Punto de entrada
├── login.php
├── logout.php
├── dashboard.php
├── diagnostico.php          # ← Eliminar después de instalar
├── reset_password.php       # ← Eliminar después de instalar
└── .htaccess
```

---

## CREDENCIALES PREDETERMINADAS (tras ejecutar reset_password.php)

| Email | Contraseña | Rol |
|---|---|---|
| superadmin@triggerx.com | Admin2024! | SuperAdmin |
| admin@triggerx.com | Admin2024! | Administrador |
| tecnico@triggerx.com | Admin2024! | Técnico |

**Cambiar contraseñas inmediatamente en producción.**

---
TriggerX Enterprise v1.0.0 — PHP 8.x + MySQL + Bootstrap 5
