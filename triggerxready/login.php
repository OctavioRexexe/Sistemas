<?php
/**
 * TRIGGERX - Login
 */
define('TRIGGERX', true);
require_once __DIR__ . '/core/config/config.php';
securityHeaders();

try { Config::load(); } catch (Throwable $e) {}

// Redirigir si ya logueado
if (Session::isLoggedIn()) {
    redirect(BASE_URL . '/dashboard.php');
}

$error = '';
$email = '';

if (isPost()) {
    verifyCsrf();

    $email    = post('email');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (!notEmpty($email, $password)) {
        $error = 'Por favor completa todos los campos.';
    } elseif (!isValidEmail($email)) {
        $error = 'Correo electrónico inválido.';
    } elseif (!checkLoginAttempts($email)) {
        $error = 'Demasiados intentos fallidos. Espera ' . LOCKOUT_TIME . ' minutos.';
    } else {
        try {
            $db   = Database::getInstance();
            $user = $db->fetch(
                "SELECT u.*, r.slug AS rol_slug, r.nombre AS rol_nombre
                 FROM usuarios u
                 JOIN roles r ON u.rol_id = r.id
                 WHERE u.email = ? AND u.activo = 1",
                [$email]
            );

            if ($user && password_verify($password, $user['password'])) {
                clearLoginAttempts($email);
                Session::setUser($user);

                $db->update('usuarios', [
                    'ultimo_login'   => date('Y-m-d H:i:s'),
                    'intentos_login' => 0,
                ], 'id = ?', [$user['id']]);

                auditLog('login', 'auth', "Login: {$email}");

                $redirect = get_param('redirect', '');
                if (!$redirect || !str_starts_with($redirect, '/')) {
                    $redirect = BASE_URL . '/dashboard.php';
                } else {
                    $redirect = BASE_URL . $redirect;
                }
                redirect($redirect);

            } else {
                registerFailedLogin($email);
                $remaining = getRemainingAttempts($email);
                $error = "Credenciales incorrectas. Intentos restantes: {$remaining}";
                auditLog('login_fallido', 'auth', "Fallo: {$email}");
            }

        } catch (Throwable $e) {
            error_log('Login error: ' . $e->getMessage());
            $error = 'Error del sistema. Verifica la conexión a la base de datos.';
        }
    }
}

$empresa       = Config::get('empresa_nombre', APP_NAME);
$colorPrimario = Config::get('color_primario', '#F4A020');
$logo          = Config::get('empresa_logo', '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Iniciar Sesión — <?= e($empresa) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= ASSETS_URL ?>/css/triggerx.css" rel="stylesheet">
    <style>
        :root {
            --tx-primary: <?= e($colorPrimario) ?>;
            --tx-primary-dark: color-mix(in srgb, <?= e($colorPrimario) ?> 80%, black);
            --tx-primary-light: color-mix(in srgb, <?= e($colorPrimario) ?> 15%, white);
        }
        body { padding-top: 0 !important; }
    </style>
</head>
<body>
<div class="tx-login-page">
    <div class="tx-login-card">

        <div class="tx-login-header">
            <?php if ($logo): ?>
            <img src="<?= ASSETS_URL . '/img/' . e($logo) ?>" alt="<?= e($empresa) ?>" height="48" class="mb-2">
            <?php else: ?>
            <div class="tx-login-logo">
                <i class="bi bi-lightning-charge-fill"></i>
            </div>
            <?php endif; ?>
            <div class="tx-login-brand"><?= e($empresa) ?></div>
            <div class="tx-login-sub">Mesa de Ayuda Empresarial</div>
        </div>

        <div class="tx-login-body">
            <h5 class="fw-700 mb-1">Bienvenido de vuelta</h5>
            <p class="text-muted mb-4" style="font-size:13px">Ingresa tus credenciales para continuar</p>

            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" style="font-size:13px">
                <i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <form method="POST" id="loginForm" novalidate>
                <?= csrfField() ?>

                <div class="tx-form-group">
                    <label class="tx-label">Correo Electrónico <span class="req">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text" style="background:var(--tx-bg);border-right:none">
                            <i class="bi bi-envelope text-muted"></i>
                        </span>
                        <input type="email" name="email" class="form-control"
                               placeholder="tu@empresa.com"
                               value="<?= e($email) ?>"
                               required autofocus
                               style="border-left:none">
                    </div>
                </div>

                <div class="tx-form-group">
                    <label class="tx-label">Contraseña <span class="req">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text" style="background:var(--tx-bg);border-right:none">
                            <i class="bi bi-lock text-muted"></i>
                        </span>
                        <input type="password" name="password" id="passInput"
                               class="form-control" placeholder="••••••••"
                               required
                               style="border-left:none;border-right:none">
                        <button type="button" class="input-group-text" id="togglePass"
                                style="background:var(--tx-bg);cursor:pointer;border-left:none">
                            <i class="bi bi-eye-slash" id="passIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label text-muted" for="remember" style="font-size:13px">
                            Recordarme
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn-login" id="btnLogin">
                    <span id="loginText">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar Sesión
                    </span>
                    <span id="loginSpinner" class="d-none">
                        <span class="spinner-border spinner-border-sm me-2"></span>Autenticando...
                    </span>
                </button>
            </form>

            <div class="mt-4 pt-3 border-top text-center">
                <p class="text-muted mb-2" style="font-size:12px">¿Tienes un ticket de soporte?</p>
                <div class="d-flex gap-2 justify-content-center">
                    <a href="<?= BASE_URL ?>/public/ticket/nuevo.php"
                       class="btn btn-sm btn-tx-outline" style="font-size:12px">
                        <i class="bi bi-plus-circle me-1"></i>Nuevo Ticket
                    </a>
                    <a href="<?= BASE_URL ?>/public/ticket/consultar.php"
                       class="btn btn-sm" style="font-size:12px;border:1px solid #ddd">
                        <i class="bi bi-search me-1"></i>Consultar Ticket
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center mt-3" style="font-size:11px;color:rgba(255,255,255,.3)">
        TriggerX Enterprise v<?= APP_VERSION ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('togglePass').addEventListener('click', function () {
    const inp  = document.getElementById('passInput');
    const icon = document.getElementById('passIcon');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'bi bi-eye';
    } else {
        inp.type = 'password';
        icon.className = 'bi bi-eye-slash';
    }
});
document.getElementById('loginForm').addEventListener('submit', function () {
    document.getElementById('loginText').classList.add('d-none');
    document.getElementById('loginSpinner').classList.remove('d-none');
    document.getElementById('btnLogin').disabled = true;
});
</script>
</body>
</html>
<?php if (ob_get_level()) ob_end_flush(); ?>
