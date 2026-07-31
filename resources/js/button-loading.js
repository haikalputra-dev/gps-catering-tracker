// Button loading state on form submit.
//
// Attaches a submit listener to every form on the page. When a form
// submits, the button that triggered the submit gets the `button-loading`
// class (see resources/css/app.css) which hides its label and shows a
// spinner. This gives immediate feedback for the mutation actions:
// dispatch delivery, mark delivered, save, delete, etc.
//
// Rules:
// - Only forms with a POST/PUT/PATCH/DELETE method get the treatment.
//   GET forms are usually filter forms and should not lock up.
// - Forms can opt out with data-no-loading on the form or the button.
// - The button re-enables on bfcache restore (pageshow) so back-nav
//   does not leave a spinner spinning.

function submitterFor(form, event) {
    if (event && event.submitter) {
        return event.submitter;
    }
    return form.querySelector('button[type="submit"], input[type="submit"]');
}

function isMutatingMethod(form) {
    const method = (form.getAttribute('method') || 'get').toLowerCase();
    if (method !== 'get') {
        return true;
    }
    // Laravel spoofed methods live in a hidden _method input.
    const spoof = form.querySelector('input[name="_method"]');
    if (spoof && spoof.value) {
        const value = spoof.value.toLowerCase();
        return value !== 'get';
    }
    return false;
}

function markLoading(button) {
    if (!button || button.disabled) return;
    button.classList.add('button-loading');
    button.setAttribute('aria-busy', 'true');
    // Delay the disable by a tick so the submitted value is still sent.
    setTimeout(() => {
        button.disabled = true;
    }, 0);
}

function clearLoading(button) {
    if (!button) return;
    button.classList.remove('button-loading');
    button.removeAttribute('aria-busy');
    button.disabled = false;
}

function onSubmit(event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.hasAttribute('data-no-loading')) return;
    if (!isMutatingMethod(form)) return;

    const button = submitterFor(form, event);
    if (!button || button.hasAttribute('data-no-loading')) return;

    markLoading(button);
}

document.addEventListener('submit', onSubmit, true);

// Restore state on back/forward cache restores.
window.addEventListener('pageshow', (event) => {
    if (!event.persisted) return;
    document.querySelectorAll('.button-loading').forEach(clearLoading);
});
