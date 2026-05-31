// assets/js/toast.js - Custom Toast Notification System
(function () {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const ICONS = {
        success: '<i class="bi bi-check2"></i>',
        error: '<i class="bi bi-x-lg"></i>',
        warning: '<i class="bi bi-exclamation-triangle"></i>',
        info: '<i class="bi bi-info-circle"></i>'
    };

    function createToast(message, type, duration) {
        type = type || 'info';
        duration = duration || 3500;

        const el = document.createElement('div');
        el.className = 'toast-item';
        el.innerHTML =
            '<div class="toast-icon ' + type + '">' + (ICONS[type] || ICONS.info) + '</div>' +
            '<div class="toast-content">' + message + '</div>' +
            '<button class="toast-close" onclick="this.closest(\'.toast-item\').classList.add(\'removing\');setTimeout(function(){this.closest(\'.toast-item\').remove()}.bind(this),300)"><i class="bi bi-x"></i></button>';

        container.appendChild(el);

        setTimeout(function () {
            if (el.parentNode) {
                el.classList.add('removing');
                setTimeout(function () { if (el.parentNode) el.remove(); }, 300);
            }
        }, duration);

        return el;
    }

    window.Toast = {
        success: function (msg, dur) { return createToast(msg, 'success', dur); },
        error: function (msg, dur) { return createToast(msg, 'error', dur); },
        warning: function (msg, dur) { return createToast(msg, 'warning', dur); },
        info: function (msg, dur) { return createToast(msg, 'info', dur); }
    };
})();
