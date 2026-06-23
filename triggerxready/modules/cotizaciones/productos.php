<?php
/**
 * TRIGGERX - Cotizaciones: Gestión de Productos/Servicios (catálogo reutilizable)
 */
define('TRIGGERX', true);
require_once __DIR__ . '/../../core/config/config.php';
requireLogin();
requireModule('cotizaciones');
requireRole('tecnico');
securityHeaders();

$db = Database::getInstance();

if (isPost()) {
    verifyCsrf();
    $action = post('action');

    if ($action === 'guardar') {
        $id          = (int)post('id');
        $codigo      = post('codigo');
        $descripcion = post('descripcion');
        $precio      = (float)post('precio_unitario', '0');

        if (!notEmpty($codigo, $descripcion)) {
            redirectWith(BASE_URL . '/modules/cotizaciones/productos.php', 'danger', 'Código y descripción son requeridos');
        }

        $data = ['codigo' => $codigo, 'descripcion' => $descripcion, 'precio_unitario' => $precio];

        try {
            if ($id) {
                $db->update('productos_servicios', $data, 'id = ?', [$id]);
                auditLog('producto_actualizar', 'cotizaciones', "Producto #{$id} actualizado");
                redirectWith(BASE_URL . '/modules/cotizaciones/productos.php', 'success', 'Producto actualizado');
            } else {
                $newId = $db->insert('productos_servicios', $data);
                auditLog('producto_crear', 'cotizaciones', "Producto #{$newId} creado: {$codigo}");
                redirectWith(BASE_URL . '/modules/cotizaciones/productos.php', 'success', 'Producto registrado');
            }
        } catch (Throwable $e) {
            redirectWith(BASE_URL . '/modules/cotizaciones/productos.php', 'danger', 'Error al guardar (¿código duplicado?)');
        }
    }

    if ($action === 'desactivar') {
        $id = (int)post('id');
        $db->update('productos_servicios', ['activo' => 0], 'id = ?', [$id]);
        redirectWith(BASE_URL . '/modules/cotizaciones/productos.php', 'success', 'Producto desactivado');
    }

    if ($action === 'activar') {
        $id = (int)post('id');
        $db->update('productos_servicios', ['activo' => 1], 'id = ?', [$id]);
        redirectWith(BASE_URL . '/modules/cotizaciones/productos.php', 'success', 'Producto activado');
    }
}

$productos = $db->fetchAll("SELECT * FROM productos_servicios ORDER BY descripcion");

$pageTitle    = 'Productos / Servicios';
$activeModule = 'cotizaciones';
include CORE_PATH . '/layout/header.php';
?>

<div class="container-fluid">
    <div class="tx-page-header">
        <div>
            <h1 class="tx-page-title"><i class="bi bi-box-seam me-2" style="color:var(--tx-primary)"></i>Productos / Servicios</h1>
            <div class="tx-breadcrumb">
                <a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a> ›
                <a href="<?= BASE_URL ?>/modules/cotizaciones/">Cotizaciones</a> › Productos
            </div>
        </div>
        <button class="btn-tx" data-bs-toggle="modal" data-bs-target="#modalProducto" onclick="abrirModalNuevo()">
            <i class="bi bi-plus-circle-fill"></i> Nuevo Producto
        </button>
    </div>

    <?= getFlashAlert() ?>

    <div class="tx-card">
        <div class="table-responsive">
            <table class="tx-table table mb-0">
                <thead>
                    <tr><th>Código</th><th>Descripción</th><th>Precio Unit.</th><th>Estado</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                <?php if (empty($productos)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">Sin productos registrados</td></tr>
                <?php endif; ?>
                <?php foreach ($productos as $p): ?>
                <tr>
                    <td class="font-monospace fw-600"><?= e($p['codigo']) ?></td>
                    <td><?= e($p['descripcion']) ?></td>
                    <td class="fw-600">S/ <?= number_format((float)$p['precio_unitario'], 2) ?></td>
                    <td>
                        <span class="badge" style="background:<?= $p['activo'] ? '#198754' : '#6c757d' ?>">
                            <?= $p['activo'] ? 'Activo' : 'Inactivo' ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-tx-outline" style="padding:3px 8px"
                                    onclick='abrirModalEditar(<?= json_encode($p, JSON_HEX_APOS) ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿<?= $p['activo'] ? 'Desactivar' : 'Activar' ?> este producto?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="<?= $p['activo'] ? 'desactivar' : 'activar' ?>">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-sm" style="padding:3px 8px;background:var(--tx-bg);border:1px solid var(--tx-border)">
                                    <i class="bi bi-<?= $p['activo'] ? 'pause-circle' : 'play-circle' ?>"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL CREAR/EDITAR -->
<div class="modal fade" id="modalProducto" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header" style="background:var(--tx-primary);color:#fff">
                    <h6 class="modal-title" id="modalProductoTitulo"><i class="bi bi-box-seam me-2"></i>Nuevo Producto</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="guardar">
                    <input type="hidden" name="id" id="prodId" value="">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="tx-label">Código <span class="req">*</span></label>
                            <input type="text" name="codigo" id="prodCodigo" class="form-control" required>
                        </div>
                        <div class="col-md-7">
                            <label class="tx-label">Precio Unitario (S/) <span class="req">*</span></label>
                            <input type="number" name="precio_unitario" id="prodPrecio" class="form-control" min="0" step="0.01" required>
                        </div>
                        <div class="col-12">
                            <label class="tx-label">Descripción <span class="req">*</span></label>
                            <input type="text" name="descripcion" id="prodDescripcion" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-tx btn-sm"><i class="bi bi-save me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script>
function abrirModalNuevo() {
    document.getElementById("modalProductoTitulo").innerHTML = \'<i class="bi bi-box-seam me-2"></i>Nuevo Producto\';
    document.getElementById("prodId").value = "";
    ["prodCodigo","prodDescripcion"].forEach(id => document.getElementById(id).value = "");
    document.getElementById("prodPrecio").value = "0";
}
function abrirModalEditar(p) {
    document.getElementById("modalProductoTitulo").innerHTML = \'<i class="bi bi-box-seam me-2"></i>Editar Producto\';
    document.getElementById("prodId").value = p.id;
    document.getElementById("prodCodigo").value = p.codigo || "";
    document.getElementById("prodDescripcion").value = p.descripcion || "";
    document.getElementById("prodPrecio").value = p.precio_unitario || "0";
    new bootstrap.Modal(document.getElementById("modalProducto")).show();
}
</script>';
include CORE_PATH . '/layout/footer.php';
?>
