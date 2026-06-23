<?php
/**
 * TRIGGERX - Layout Global: Head + Navbar
 * Incluir con: include CORE_PATH . '/layout/header.php';
 */
defined('TRIGGERX') or die('Acceso denegado');

// Cargar config de BD
Config::load();
$_user = Session::getUser();
$_modulosNav = $_user ? getModulosUsuario($_user['id'], $_user['rol_id']) : [];
$_notifs = $_user ? getNotificacionesPendientes($_user['id']) : [];
$_colorPrimario = Config::get('color_primario', '#F4A020');
$_empresa = Config::empresa();
$_logo = Config::logo();

// Título de página (se puede sobreescribir antes de incluir)
$pageTitle = $pageTitle ?? $_empresa;
$activeModule = $activeModule ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle) ?> — <?= e($_empresa) ?></title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- Fuente -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- CSS Global TriggerX -->
    <link href="<?= ASSETS_URL ?>/css/triggerx.css" rel="stylesheet">
    
    <style>
        :root {
            --tx-primary: <?= e($_colorPrimario) ?>;
            --tx-primary-dark: color-mix(in srgb, <?= e($_colorPrimario) ?> 80%, black);
            --tx-primary-light: color-mix(in srgb, <?= e($_colorPrimario) ?> 15%, white);
        }
    </style>
    
    <?php if (isset($extraHead)) echo $extraHead; ?>
</head>
<body class="tx-body">

<!-- ============================================================
     NAVBAR GLOBAL
     ============================================================ -->
<nav class="navbar navbar-expand-lg tx-navbar fixed-top" id="txNavbar">
    <div class="container-fluid">

        <!-- Logo / Brand -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL ?>/dashboard.php">
            <?php if ($_logo): ?>
                <img src="<?= e($_logo) ?>" alt="<?= e($_empresa) ?>" height="36">
            <?php else: ?>
                <div class="tx-logo-icon">
                    <i class="bi bi-lightning-charge-fill"></i>
                </div>
                <span class="tx-brand-text"><?= e($_empresa) ?></span>
            <?php endif; ?>
        </a>

        <!-- Toggle mobile -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Nav Links -->
        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <!-- Dashboard siempre visible -->
                <li class="nav-item">
                    <a class="nav-link <?= $activeModule === 'dashboard' ? 'active' : '' ?>" href="<?= BASE_URL ?>/dashboard.php">
                        <i class="bi bi-grid-fill me-1"></i>Dashboard
                    </a>
                </li>

                <!-- Módulos dinámicos según permisos -->
                <?php foreach ($_modulosNav as $mod): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $activeModule === $mod['slug'] ? 'active' : '' ?>" 
                       href="<?= BASE_URL . $mod['url'] ?>">
                        <i class="bi <?= e($mod['icono']) ?> me-1"></i>
                        <?= e($mod['nombre']) ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>

            <!-- Nav Right -->
            <ul class="navbar-nav align-items-center gap-1">
                
                <!-- Notificaciones -->
                <?php if ($_user): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link tx-notif-bell position-relative" href="#" 
                       data-bs-toggle="dropdown" id="btnNotificaciones">
                        <i class="bi bi-bell-fill fs-5"></i>
                        <?php if (count($_notifs) > 0): ?>
                        <span class="tx-notif-badge" id="notifCount"><?= count($_notifs) ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end tx-notif-dropdown p-0" style="width:360px">
                        <div class="tx-notif-header">
                            <span><i class="bi bi-bell me-2"></i>Notificaciones</span>
                            <?php if ($_notifs): ?>
                            <a href="#" class="tx-notif-markall" id="btnMarkAllRead">Marcar leídas</a>
                            <?php endif; ?>
                        </div>
                        <div class="tx-notif-list" id="notifList">
                            <?php if (empty($_notifs)): ?>
                            <div class="tx-notif-empty">
                                <i class="bi bi-check-circle fs-2 mb-2 d-block opacity-25"></i>
                                Sin notificaciones pendientes
                            </div>
                            <?php else: ?>
                            <?php foreach ($_notifs as $n): ?>
                            <a href="<?= e($n['url'] ?: '#') ?>" class="tx-notif-item" 
                               data-id="<?= $n['id'] ?>">
                                <div class="tx-notif-icon tx-notif-<?= e($n['tipo']) ?>">
                                    <i class="bi <?= notifIcon($n['tipo']) ?>"></i>
                                </div>
                                <div class="tx-notif-body">
                                    <div class="tx-notif-title"><?= e($n['titulo']) ?></div>
                                    <div class="tx-notif-msg"><?= e(truncate($n['mensaje'], 70)) ?></div>
                                    <div class="tx-notif-time"><?= timeAgo($n['created_at']) ?></div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>

                <!-- Usuario -->
                <li class="nav-item dropdown">
                    <a class="nav-link d-flex align-items-center gap-2 tx-user-nav" href="#" 
                       data-bs-toggle="dropdown">
                        <div class="tx-avatar">
                            <?= mb_substr($_user['nombre'], 0, 1) . mb_substr($_user['apellido'], 0, 1) ?>
                        </div>
                        <div class="d-none d-lg-block tx-user-info">
                            <div class="tx-user-name"><?= e($_user['nombre'] . ' ' . $_user['apellido']) ?></div>
                            <div class="tx-user-role"><?= e($_user['rol_nombre']) ?></div>
                        </div>
                        <i class="bi bi-chevron-down tx-chevron"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end tx-user-dropdown">
                        <li class="px-3 py-2 border-bottom">
                            <div class="fw-600"><?= e($_user['nombre'] . ' ' . $_user['apellido']) ?></div>
                            <small class="text-muted"><?= e($_user['email']) ?></small>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= BASE_URL ?>/admin/profile.php">
                                <i class="bi bi-person me-2"></i>Mi Perfil
                            </a>
                        </li>
                        <?php if (Session::hasRole('admin')): ?>
                        <li>
                            <a class="dropdown-item" href="<?= BASE_URL ?>/admin/settings.php">
                                <i class="bi bi-gear me-2"></i>Configuración
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (Session::hasRole('superadmin')): ?>
                        <li>
                            <a class="dropdown-item" href="<?= BASE_URL ?>/admin/logs.php">
                                <i class="bi bi-journal-text me-2"></i>Logs de Auditoría
                            </a>
                        </li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

            </ul>
        </div>
    </div>
</nav>

<!-- Main Content Wrapper -->
<div class="tx-main-content">

