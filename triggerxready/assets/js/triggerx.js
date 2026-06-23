/**
 * TRIGGERX ENTERPRISE PLATFORM - Core JavaScript
 * Version: 1.0.0
 */

'use strict';

/* ============================================================
   INICIALIZACIÓN
   ============================================================ */
document.addEventListener('DOMContentLoaded', function () {
    TriggerX.init();
});

const TriggerX = {

    init() {
        this.setupAjaxCSRF();
        this.initTooltips();
        this.initDragDrop();
        this.setupDeleteConfirm();
        this.flashAutoHide();
    },

    /* Agregar CSRF token a todas las peticiones AJAX jQuery */
    setupAjaxCSRF() {
        if (typeof $ !== 'undefined' && typeof TX !== 'undefined') {
            $.ajaxSetup({
                headers: { 'X-CSRF-Token': TX.csrfToken },
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                }
            });
        }
    },

    /* Tooltips Bootstrap */
    initTooltips() {
        const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltips.forEach(el => new bootstrap.Tooltip(el, { trigger: 'hover' }));
    },

    /* Drag & Drop para uploads */
    initDragDrop() {
        const zones = document.querySelectorAll('.tx-upload-zone');
        zones.forEach(zone => {
            const input = zone.querySelector('input[type="file"]');
            zone.addEventListener('click', () => input?.click());
            zone.addEventListener('dragover', (e) => {
                e.preventDefault();
                zone.classList.add('dragging');
            });
            zone.addEventListener('dragleave', () => zone.classList.remove('dragging'));
            zone.addEventListener('drop', (e) => {
                e.preventDefault();
                zone.classList.remove('dragging');
                if (input && e.dataTransfer.files.length) {
                    input.files = e.dataTransfer.files;
                    input.dispatchEvent(new Event('change'));
                    TriggerX.updateUploadLabel(zone, e.dataTransfer.files);
                }
            });
            input?.addEventListener('change', () => {
                TriggerX.updateUploadLabel(zone, input.files);
            });
        });
    },

    updateUploadLabel(zone, files) {
        const label = zone.querySelector('.tx-upload-label');
        if (label && files.length) {
            label.textContent = `${files.length} archivo(s) seleccionado(s)`;
        }
    },

    /* Confirmación de eliminación con SweetAlert */
    setupDeleteConfirm() {
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-confirm]');
            if (!btn) return;
            e.preventDefault();
            const msg = btn.dataset.confirm || '¿Estás seguro de realizar esta acción?';
            Swal.fire({
                title: '¿Confirmar acción?',
                text: msg,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: window.TX?.colorPrimario || '#F4A020',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, continuar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
            }).then((result) => {
                if (result.isConfirmed) {
                    const href = btn.href;
                    const form = btn.closest('form');
                    if (href && href !== '#') {
                        window.location.href = href;
                    } else if (form) {
                        form.submit();
                    }
                }
            });
        });
    },

    /* Auto-ocultar alertas flash */
    flashAutoHide() {
        const alerts = document.querySelectorAll('.alert-dismissible:not(.alert-permanent)');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                bsAlert?.close();
            }, 6000);
        });
    },

    /* ============================================================
       TOAST NOTIFICATIONS
       ============================================================ */
    toast(message, type = 'success', duration = 4000) {
        const icons = {
            success: 'bi-check-circle-fill',
            error:   'bi-x-circle-fill',
            warning: 'bi-exclamation-triangle-fill',
            info:    'bi-info-circle-fill',
        };
        const colors = {
            success: '#198754',
            error:   '#dc3545',
            warning: '#F4A020',
            info:    '#0d6efd',
        };

        const toastId = 'toast_' + Date.now();
        const html = `
        <div id="${toastId}" class="toast show align-items-center border-0 mb-2"
             role="alert" style="background:#fff;box-shadow:0 4px 20px rgba(0,0,0,.15);
             border-radius:10px;min-width:300px;border-left:4px solid ${colors[type]}!important">
            <div class="d-flex p-3 gap-3 align-items-center">
                <i class="bi ${icons[type]} fs-5" style="color:${colors[type]}"></i>
                <div style="font-size:13.5px;font-weight:500;flex:1">${message}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>`;

        let container = document.getElementById('txToastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'txToastContainer';
            container.style.cssText = 'position:fixed;top:76px;right:20px;z-index:9999;';
            document.body.appendChild(container);
        }
        container.insertAdjacentHTML('beforeend', html);

        const toastEl = document.getElementById(toastId);
        setTimeout(() => {
            toastEl?.remove();
        }, duration);
    },

    /* ============================================================
       AJAX HELPERS
       ============================================================ */
    post(url, data, onSuccess, onError) {
        if (typeof TX !== 'undefined') {
            data._csrf_token = TX.csrfToken;
        }
        $.ajax({
            url: url,
            method: 'POST',
            data: data,
            dataType: 'json',
            success: (res) => {
                if (res.success) {
                    onSuccess && onSuccess(res);
                } else {
                    onError ? onError(res) : TriggerX.toast(res.message || 'Error', 'error');
                }
            },
            error: (xhr) => {
                if (xhr.status === 401) {
                    window.location.href = (TX?.baseUrl || '') + '/login.php';
                    return;
                }
                TriggerX.toast('Error de conexión', 'error');
                onError && onError(null);
            }
        });
    },

    /* Mostrar loading overlay */
    showLoading() {
        if (!document.getElementById('txLoadingOverlay')) {
            const div = document.createElement('div');
            div.id = 'txLoadingOverlay';
            div.className = 'tx-spinner-overlay';
            div.innerHTML = '<div class="tx-spinner"></div>';
            document.body.appendChild(div);
        }
    },

    hideLoading() {
        document.getElementById('txLoadingOverlay')?.remove();
    },

    /* ============================================================
       CONFIRMACIÓN ESTÁNDAR
       ============================================================ */
    confirm(title, text, callback, type = 'warning') {
        Swal.fire({
            title: title,
            text: text,
            icon: type,
            showCancelButton: true,
            confirmButtonColor: window.TX?.colorPrimario || '#F4A020',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Confirmar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
        }).then((result) => {
            if (result.isConfirmed && callback) callback();
        });
    },

    /* ============================================================
       FORMATEAR NÚMEROS
       ============================================================ */
    formatNumber(n) {
        return new Intl.NumberFormat('es-PE').format(n);
    },

    /* ============================================================
       COPIAR AL PORTAPAPELES
       ============================================================ */
    copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            TriggerX.toast('Copiado al portapapeles', 'success', 2000);
        }).catch(() => {
            TriggerX.toast('No se pudo copiar', 'error', 2000);
        });
    },
};

/* ============================================================
   CHART.JS - CONFIGURACIÓN GLOBAL
   ============================================================ */
if (typeof Chart !== 'undefined') {
    Chart.defaults.font.family = "'Plus Jakarta Sans', system-ui, sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#6c757d';
    Chart.defaults.plugins.legend.position = 'bottom';
    Chart.defaults.plugins.legend.labels.padding = 20;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.pointStyleWidth = 10;
    Chart.defaults.plugins.tooltip.backgroundColor = '#1a1a2e';
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
    Chart.defaults.plugins.tooltip.bodyFont.size = 13;
}

/* ============================================================
   DATATABLES - CONFIGURACIÓN ESPAÑOL
   ============================================================ */
const dtLangES = {
    sProcessing:     "Procesando...",
    sLengthMenu:     "Mostrar _MENU_ registros",
    sZeroRecords:    "No se encontraron resultados",
    sEmptyTable:     "Sin datos disponibles",
    sInfo:           "Mostrando _START_ a _END_ de _TOTAL_ registros",
    sInfoEmpty:      "Mostrando 0 a 0 de 0 registros",
    sInfoFiltered:   "(filtrado de _MAX_ total)",
    sSearch:         "",
    sSearchPlaceholder: "Buscar...",
    oPaginate: {
        sFirst:    "«", sLast: "»",
        sNext:     "›", sPrevious: "‹",
    },
};

/* Inicializar DataTable con configuración estándar TriggerX */
function txDataTable(selector, options = {}) {
    const defaults = {
        language:    dtLangES,
        pageLength:  25,
        responsive:  true,
        dom:         "<'row mb-3'<'col-sm-6'l><'col-sm-6'f>>" +
                     "<'row'<'col-12'tr>>" +
                     "<'row mt-3'<'col-sm-5'i><'col-sm-7'p>>",
        autoWidth:   false,
    };
    return $(selector).DataTable({ ...defaults, ...options });
}
