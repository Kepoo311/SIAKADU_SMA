(() => {
    const notifications = Array.from(
        document.querySelectorAll('.admin-alert, .form-alert, #notifikasi')
    ).filter((notification) => !notification.classList.contains('app-toast'));
    if (notifications.length === 0) {
        return;
    }

    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('section');
        container.className = 'toast-container';
        container.setAttribute('aria-label', 'Notifikasi');
        container.setAttribute('aria-live', 'polite');
        container.setAttribute('aria-relevant', 'additions');
        document.body.append(container);
    }

    notifications.forEach((notification) => {
        const isError = notification.classList.contains('admin-alert-error') ||
            notification.classList.contains('form-alert-error');
        const isWarning = notification.classList.contains('admin-alert-warning') ||
            notification.classList.contains('form-alert-warning');
        const variant = isError ? 'error' : isWarning ? 'warning' : 'success';

        notification.classList.add('app-toast', `app-toast-${variant}`);
        notification.setAttribute('role', isError ? 'alert' : 'status');

        const closeButton = document.createElement('button');
        closeButton.className = 'toast-dismiss';
        closeButton.type = 'button';
        closeButton.setAttribute('aria-label', 'Tutup notifikasi');
        closeButton.textContent = '×';
        notification.append(closeButton);
        container.append(notification);

        let dismissed = false;
        const dismiss = () => {
            if (dismissed) {
                return;
            }
            dismissed = true;
            notification.classList.add('app-toast-exit');
            window.setTimeout(() => notification.remove(), 180);
        };

        closeButton.addEventListener('click', dismiss);
        window.setTimeout(dismiss, variant === 'error' ? 8000 : variant === 'warning' ? 6500 : 5000);
    });
})();
