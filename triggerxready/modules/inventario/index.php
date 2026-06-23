<?php
define('TRIGGERX', true);
require_once __DIR__ . '/../../core/config/config.php';
requireLogin();
requireModule('inventario');

$pageTitle    = 'Inventario';
$activeModule = 'inventario';
include CORE_PATH . '/layout/header.php';
?>
<div class="container-fluid">
    <div class="tx-page-header">
        <h1 class="tx-page-title">
            <i class="bi bi-box-seam me-2" style="color:var(--tx-primary)"></i>Inventario
        </h1>
    </div>
    <div class="tx-card text-center py-5">
        <i class="bi bi-box-seam" style="font-size:64px;color:var(--tx-primary);opacity:.3;display:block;margin-bottom:16px"></i>
        <h4 class="fw-700">Módulo en Desarrollo</h4>
        <p class="text-muted">El módulo de Inventario estará disponible próximamente.</p>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn-tx mt-2">
            <i class="bi bi-arrow-left me-1"></i>Volver al Dashboard
        </a>
    </div>
</div>
<?php include CORE_PATH . '/layout/footer.php'; ?>
