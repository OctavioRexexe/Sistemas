<?php
/**
 * TRIGGERX - Diagnóstico del Sistema
 * Ejecutar UNA VEZ para verificar la configuración
 * ELIMINAR después de verificar
 */

echo '<style>body{font-family:monospace;padding:20px;background:#1a1a2e;color:#eee}
.ok{color:#4ade80}.err{color:#f87171}.warn{color:#fbbf24}
h2{color:#F4A020}table{width:100%;border-collapse:collapse}
td{padding:6px 12px;border-bottom:1px solid #333}
</style>';

echo '<h2>⚡ TriggerX — Diagnóstico del Sistema</h2>';

$checks = [];

// PHP Version
$phpOk = version_compare(PHP_VERSION, '8.0', '>=');
$checks[] = [$phpOk, 'PHP Version', PHP_VERSION, 'Requiere 8.0+'];

// Extensiones
foreach (['pdo', 'pdo_mysql', 'json', 'mbstring', 'fileinfo'] as $ext) {
    $ok = extension_loaded($ext);
    $checks[] = [$ok, "Extensión: {$ext}", $ok ? 'Cargada' : 'NO ENCONTRADA', 'Requerida'];
}

// mod_rewrite (indirecto)
$checks[] = [true, 'Servidor', $_SERVER['SERVER_SOFTWARE'] ?? 'N/A', ''];

// Rutas
$root = __DIR__;
$checks[] = [is_dir($root . '/core'), 'Carpeta /core', is_dir($root.'/core') ? 'Existe' : 'NO EXISTE', ''];
$checks[] = [is_dir($root . '/assets/uploads'), 'Carpeta uploads', is_dir($root.'/assets/uploads') ? 'Existe' : 'NO EXISTE', ''];
$checks[] = [is_writable($root . '/assets/uploads'), 'Uploads escribible', is_writable($root.'/assets/uploads') ? 'Sí' : 'NO (permisos)', ''];

// Base URL detectada
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$rootDir  = str_replace('\\', '/', __DIR__);
$docRoot  = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
if ($docRoot && strpos($rootDir, $docRoot) === 0) {
    $base = rtrim(substr($rootDir, strlen($docRoot)), '/');
} else {
    $base = '';
}
$baseUrl = $protocol . '://' . $host . $base;
$checks[] = [true, 'BASE_URL detectada', $baseUrl, ''];
$checks[] = [true, 'DOCUMENT_ROOT', $docRoot, ''];
$checks[] = [true, 'SCRIPT_NAME', $_SERVER['SCRIPT_NAME'] ?? 'N/A', ''];

// Test conexión MySQL
echo '<h2>Base de Datos</h2>';
$dbHost = 'localhost';
$dbName = 'triggerx';
$dbUser = 'root';
$dbPass = '';

try {
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $checks[] = [true, 'Conexión MySQL', 'OK — BD: '.$dbName, ''];
    $checks[] = [count($tables) > 0, 'Tablas instaladas', count($tables) . ' tablas', 'Requiere > 0'];
    if (count($tables) > 0) {
        echo '<p class="ok">✓ Tablas encontradas: ' . implode(', ', $tables) . '</p>';
    } else {
        echo '<p class="err">✗ Sin tablas — Importa sql/triggerx_install.sql en phpMyAdmin</p>';
    }
} catch (PDOException $e) {
    $checks[] = [false, 'Conexión MySQL', 'ERROR: ' . $e->getMessage(), 'Verificar DB_USER/DB_PASS/DB_NAME en config.php'];
}

// Tabla de resultados
echo '<h2>Verificaciones</h2><table>';
foreach ($checks as [$ok, $nombre, $valor, $nota]) {
    $cls = $ok ? 'ok' : 'err';
    $ico = $ok ? '✓' : '✗';
    echo "<tr><td class='{$cls}'>{$ico}</td><td>{$nombre}</td><td class='{$cls}'>{$valor}</td><td style='color:#888'>{$nota}</td></tr>";
}
echo '</table>';

echo '<br><p style="color:#888;font-size:12px">⚠️ Elimina este archivo (diagnostico.php) después de revisar.</p>';
