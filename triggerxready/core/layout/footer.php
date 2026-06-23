<?php
/**
 * TRIGGERX - Layout Global: Footer
 */
defined('TRIGGERX') or die('Acceso denegado');
?>
</div><!-- /.tx-main-content -->

<!-- Footer -->
<footer class="tx-footer">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center">
            <span><?= e(Config::empresa()) ?> &copy; <?= date('Y') ?></span>
            <span class="text-muted">
                <i class="bi bi-lightning-charge-fill me-1"></i>
                TriggerX Enterprise v<?= APP_VERSION ?>
            </span>
        </div>
    </div>
</footer>

<!-- ============================================================
     SCRIPTS GLOBALES
     ============================================================ -->
<!-- jQuery (necesario para DataTables) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Bootstrap 5 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

<!-- TriggerX Core JS -->
<script src="<?= ASSETS_URL ?>/js/triggerx.js"></script>

<script>
// Config global para JS
const TX = {
    baseUrl: '<?= BASE_URL ?>',
    assetsUrl: '<?= ASSETS_URL ?>',
    csrfToken: '<?= Session::csrfToken() ?>',
    userId: <?= Session::userId() ?? 'null' ?>,
    userRole: '<?= Session::userRole() ?>',
    colorPrimario: '<?= e(Config::colorPrimario()) ?>',
};

// Notificaciones: polling cada 60 segundos
setInterval(function () {
    $.get(TX.baseUrl + '/core/ajax/notificaciones.php', { action: 'count' }, function (res) {
        if (res.count !== undefined) {
            const badge = document.getElementById('notifCount');
            if (res.count > 0) {
                if (badge) {
                    badge.textContent = res.count;
                    badge.style.display = '';
                } else {
                    const bell = document.getElementById('btnNotificaciones');
                    if (bell) {
                        const span = document.createElement('span');
                        span.className = 'tx-notif-badge';
                        span.id = 'notifCount';
                        span.textContent = res.count;
                        bell.appendChild(span);
                    }
                }
            } else if (badge) {
                badge.style.display = 'none';
            }
        }
    });
}, 60000);

// Marcar todas como leídas
document.getElementById('btnMarkAllRead')?.addEventListener('click', function (e) {
    e.preventDefault();
    $.post(TX.baseUrl + '/core/ajax/notificaciones.php',
        { action: 'mark_all', _csrf_token: TX.csrfToken },
        function (res) {
            if (res.success) {
                document.getElementById('notifList').innerHTML =
                    '<div class="tx-notif-empty"><i class="bi bi-check-circle fs-2 mb-2 d-block opacity-25"></i>Sin notificaciones pendientes</div>';
                const badge = document.getElementById('notifCount');
                if (badge) badge.style.display = 'none';
            }
        }
    );
});
</script>

<?php if (isset($extraScripts)) echo $extraScripts; ?>
</body>
</html>
<?php if (ob_get_level()) ob_end_flush(); ?>
