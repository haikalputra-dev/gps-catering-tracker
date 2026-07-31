// Toast dismissal + auto-hide behaviour.
//
// Toasts are server-rendered in resources/views/layouts/app.blade.php and
// resources/views/layouts/public.blade.php as elements with `data-toast`.
// Each toast may include a close button with `data-toast-close`. This
// module wires up the close button and auto-dismisses non-error toasts
// after 5 seconds. Error toasts (variant="danger") stick until dismissed
// so the user has time to read the failure.
//
// CSS animation classes `toast-enter` and `toast-exit` live in
// resources/css/app.css. The DOM is removed after the exit animation
// completes so that focus outlines and screen-reader announcements do
// not linger on empty containers.

const AUTO_DISMISS_MS = 5000;
const EXIT_ANIMATION_MS = 200;

function dismissToast(toast) {
    if (!toast || toast.dataset.toastDismissing === '1') {
        return;
    }
    toast.dataset.toastDismissing = '1';
    toast.classList.remove('toast-enter');
    toast.classList.add('toast-exit');
    setTimeout(() => {
        toast.remove();
    }, EXIT_ANIMATION_MS);
}

function initToast(toast) {
    if (toast.dataset.toastInit === '1') {
        return;
    }
    toast.dataset.toastInit = '1';
    toast.classList.add('toast-enter');

    const closeButton = toast.querySelector('[data-toast-close]');
    if (closeButton) {
        closeButton.addEventListener('click', () => dismissToast(toast));
    }

    const variant = toast.dataset.toastVariant || '';
    if (variant !== 'danger' && variant !== 'error') {
        setTimeout(() => dismissToast(toast), AUTO_DISMISS_MS);
    }
}

function initAll(root = document) {
    root.querySelectorAll('[data-toast]').forEach(initToast);
}

document.addEventListener('DOMContentLoaded', () => initAll());

// Support dynamically injected toasts (future-proofing; safe no-op today).
const observer = new MutationObserver((mutations) => {
    for (const mutation of mutations) {
        mutation.addedNodes.forEach((node) => {
            if (node.nodeType !== 1) return;
            if (node.matches && node.matches('[data-toast]')) {
                initToast(node);
            }
            if (node.querySelectorAll) {
                node.querySelectorAll('[data-toast]').forEach(initToast);
            }
        });
    }
});

if (document.body) {
    observer.observe(document.body, { childList: true, subtree: true });
} else {
    document.addEventListener('DOMContentLoaded', () => {
        observer.observe(document.body, { childList: true, subtree: true });
    });
}
