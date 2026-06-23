<?php
/**
 * TRIGGERX — Script de Reset de Contraseña (solo desarrollo)
 * Acceder UNA VEZ: http://localhost/triggerx/reset_password.php
 * ELIMINAR después de usarlo
 */
define('TRIGGERX', true);
require_once __DIR__ . '/core/config/config.php';

// Contraseña nueva para todos los usuarios de prueba
$nuevaPass = 'Admin2024!';
$hash = password_hash($nuevaPass, PASSWORD_BCRYPT, ['cost' => 10]);

$resultado = [];
try {
    $db = Database::getInstance();
    $db->query("UPDATE usuarios SET password = ?", [$hash]);
    $afectados = $db->fetchColumn("SELECT COUNT(*) FROM usuarios");
    $resultado = ['ok' => true, 'msg' => "Contraseña actualizada para {$afectados} usuario(s)", 'hash' => $hash];
} catch (Throwable $e) {
    $resultado = ['ok' => false, 'msg' => 'Error: ' . $e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reset Password — TriggerX</title>
    <style>
        body{font-family:monospace;padding:40px;background:#1a1a2e;color:#eee;max-width:600px;margin:0 auto}
        .ok{color:#4ade80;background:#052e16;border:1px solid #166534;padding:16px;border-radius:8px}
        .err{color:#f87171;background:#450a0a;border:1px solid #991b1b;padding:16px;border-radius:8px}
        code{display:block;margin-top:12px;word-break:break-all;color:#fbbf24;font-size:12px}
        h2{color:#F4A020}
        .warn{color:#fbbf24;font-size:13px;margin-top:20px;padding:12px;border:1px solid #854d0e;border-radius:8px;background:#431a03}
    </style>
</head>
<body>
<h2>⚡ TriggerX — Reset de Contraseña</h2>

<?php if ($resultado['ok']): ?>
<div class="ok">
    ✓ <?= htmlspecialchars($resultado['msg']) ?><br><br>
    <strong>Nueva contraseña:</strong> <?= htmlspecialchars($nuevaPass) ?><br><br>
    <strong>Usuarios disponibles:</strong><br>
    — superadmin@triggerx.com / <?= htmlspecialchars($nuevaPass) ?><br>
    — admin@triggerx.com / <?= htmlspecialchars($nuevaPass) ?><br>
    — tecnico@triggerx.com / <?= htmlspecialchars($nuevaPass) ?><br>
    <code>Hash generado: <?= htmlspecialchars($resultado['hash']) ?></code>
</div>
<?php else: ?>
<div class="err">✗ <?= htmlspecialchars($resultado['msg']) ?></div>
<?php endif; ?>

<div class="warn">
    ⚠️ <strong>IMPORTANTE:</strong> Elimina este archivo después de usarlo.<br>
    <code>Ruta: <?= __FILE__ ?></code>
</div>

<br>
<a href="<?= BASE_URL ?>/login.php" style="color:#F4A020">→ Ir al Login</a>
</body>
</html>
