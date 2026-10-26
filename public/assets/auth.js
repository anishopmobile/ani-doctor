'use strict';
document.querySelector('[data-errors]')?.focus();
document.querySelectorAll('form[data-submit-message]').forEach(form => {
    form.addEventListener('submit', () => {
        form.querySelector('button[type="submit"]').disabled = true;
        form.querySelector('[data-form-status]').textContent = form.dataset.submitMessage;
    });
});
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form button[type="submit"]').forEach(button => { button.disabled = false; });
    document.querySelectorAll('[data-form-status]').forEach(status => { status.textContent = ''; });
});
