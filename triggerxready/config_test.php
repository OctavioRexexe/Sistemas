<?php
// Test de config.php paso a paso
echo '<style>body{font-family:monospace;padding:20px;background:#111;color:#eee}
.ok{color:#4ade80}.err{color:#f87171}h2{color:#F4A020}</style>';
echo '<h2>⚡ TriggerX — Test de Configuración</h2>';

// Paso 1: Rutas
$root = __DIR__;
echo '<p class="ok">✓ ROOT_PATH: ' . $root . '</p>';

// Paso 2: Archivos del core
$files = [
    'core/config/config.php',
    'core/classes/Database.php',
    'core/classes/Session.php',
    'core/classes/Config.php',
    'core/helpers/functions.php',
    'core/helpers/security.php',
];
foreach ($files as $f) {
    $exists = file_exists($root . '/' . $f);
    $cls = $exists ? 'ok' : 'err';
    $ico = $exists ? '✓' : '✗';
    echo "<p class='$cls'>$ico $f</p>";
}

// Paso 3: Cargar config
echo '<hr><h2>Cargando config.php...</h2>';
try {
    define('TRIGGERX', true);
    require_once $root . '/core/config/config.php';
    echo '<p class="ok">✓ config.php cargado correctamente</p>';
    echo '<p class="ok">✓ BASE_URL: ' . BASE_URL . '</p>';
    echo '<p class="ok">✓ ROOT_PATH: ' . ROOT_PATH . '</p>';
} catch (Throwable $e) {
    echo '<p class="err">✗ Error en config.php: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p class="err">Archivo: ' . $e->getFile() . ' línea ' . $e->getLine() . '</p>';
    exit;
}

// Paso 4: Test BD
echo '<hr><h2>Test de Base de Datos...</h2>';
try {
    $db = Database::getInstance();
    $tablas = $db->fetchAll("SHOW TABLES");
    echo '<p class="ok">✓ Conexión MySQL OK — ' . count($tablas) . ' tablas</p>';
    foreach ($tablas as $t) {
        echo '<p style="color:#888;margin:2px 0 0 20px">→ ' . array_values($t)[0] . '</p>';
    }
    if (count($tablas) === 0) {
        echo '<p class="err">✗ Sin tablas — Importa sql/triggerx_install.sql</p>';
    }
} catch (Throwable $e) {
    echo '<p class="err">✗ Error MySQL: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p class="err">Verifica DB_HOST, DB_NAME, DB_USER, DB_PASS en core/config/config.php</p>';
}

echo '<hr><p style="color:#888">Elimina test.php y config_test.php después de verificar</p>';
echo '<p><a href="' . BASE_URL . '/login.php" style="color:#F4A020">→ Ir al Login</a></p>';
