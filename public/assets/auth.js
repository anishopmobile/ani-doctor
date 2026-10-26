'use strict';
const confirmation = document.querySelector('#confirm-action');
let pendingConfirmation;
document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', event => {
        if (form.dataset.confirmed === 'true') {
            delete form.dataset.confirmed;
            return;
        }
        event.preventDefault();
        pendingConfirmation = form;
        confirmation.querySelector('#confirm-message').textContent = form.dataset.confirm;
        confirmation.returnValue = 'cancel';
        confirmation.showModal();
    });
});
confirmation?.addEventListener('close', () => {
    const form = pendingConfirmation;
    pendingConfirmation = null;
    if (form && confirmation.returnValue === 'confirm') {
        form.dataset.confirmed = 'true';
        form.requestSubmit();
    }
});
document.querySelector('[data-errors]')?.focus();
document.querySelectorAll('form[data-submit-message]').forEach(form => {
    form.addEventListener('submit', () => {
        const button = form.querySelector('button[type="submit"]');
        button.dataset.submitting = 'true';
        button.disabled = true;
        form.querySelector('[data-form-status]').textContent = form.dataset.submitMessage;
    });
});
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form button[data-submitting="true"]').forEach(button => {
        button.disabled = button.hasAttribute('data-booking-submit') && !button.form.querySelector('[data-booking-start]').value;
        delete button.dataset.submitting;
    });
    document.querySelectorAll('[data-form-status]').forEach(status => { status.textContent = ''; });
});
