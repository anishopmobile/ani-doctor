'use strict';

// Presentation only. Native validation, form payloads and server rules remain authoritative.
const publicHeader = document.querySelector('[data-public-header]');
if (publicHeader) {
    const menu = publicHeader.querySelector('[data-public-menu]');
    const close = () => { publicHeader.classList.remove('public-menu-open'); menu.setAttribute('aria-expanded', 'false'); };
    menu.hidden = false; publicHeader.classList.add('nav-ready');
    menu.addEventListener('click', () => menu.setAttribute('aria-expanded', String(publicHeader.classList.toggle('public-menu-open'))));
    publicHeader.querySelector('nav').addEventListener('click', event => { if (event.target.closest('a')) close(); });
    publicHeader.addEventListener('keydown', event => { if (event.key === 'Escape') { close(); menu.focus(); } });
}

const dirtyForms = new Set();
document.querySelectorAll('.account-menu').forEach(menu => {
    document.addEventListener('pointerdown', event => { if (!menu.contains(event.target)) menu.open = false; });
    menu.addEventListener('keydown', event => { if (event.key === 'Escape') { menu.open = false; menu.querySelector('summary').focus(); } });
});
document.querySelectorAll('form').forEach(form => {
    const method = form.querySelector('[name="_method"]')?.value || form.getAttribute('method') || 'get';
    if (!form.closest('[data-page-builder]') && method.toUpperCase() !== 'DELETE' && (form.closest('[data-record-panel]') || /\/admin\/website-(theme|articles|sections|links|service-content|taxonomies)(\/|$)/.test(new URL(form.getAttribute('action') || location.href, document.baseURI).pathname))) form.dataset.unsavedGuard = '';
    if (!form.hasAttribute('data-unsaved-guard')) return;
    const status = form.querySelector('[data-unsaved-status]') || form.closest('[data-record-panel]')?.querySelector('[data-record-unsaved]');
    const mark = () => { dirtyForms.add(form); if (status) status.textContent = 'تغییرات ذخیره‌نشده دارید.'; };
    form.addEventListener('input', mark); form.addEventListener('change', mark);
    form.addEventListener('reset', () => { dirtyForms.delete(form); if (status) status.textContent = ''; });
});
window.addEventListener('beforeunload', event => { if (dirtyForms.size) { event.preventDefault(); event.returnValue = ''; } });

document.querySelectorAll('[data-color-value]').forEach(output => {
    const input = document.getElementById(output.dataset.colorValue);
    const sync = () => { output.textContent = input.value.toUpperCase(); };
    input.addEventListener('input', sync); sync();
    input.form?.addEventListener('reset', () => queueMicrotask(sync));
});
document.querySelectorAll('[data-palette-preset]').forEach(button => {
    button.hidden = false;
    button.addEventListener('click', () => {
        const values = JSON.parse(button.dataset.palettePreset);
        for (const [name, value] of Object.entries(values)) {
            const input = button.form.elements.namedItem(name);
            if (input instanceof HTMLInputElement && /^#[0-9a-f]{6}$/i.test(value)) { input.value = value; input.dispatchEvent(new Event('input', {bubbles: true})); }
        }
    });
});
document.querySelectorAll('[data-order-move]').forEach(button => {
    button.hidden = false;
    button.addEventListener('click', () => {
        const row = button.closest('[data-order-row]'), target = button.dataset.orderMove === 'up' ? row.previousElementSibling : row.nextElementSibling;
        if (!target) return;
        button.dataset.orderMove === 'up' ? target.before(row) : target.after(row);
        button.focus(); row.dispatchEvent(new Event('input', {bubbles: true}));
    });
});
document.querySelectorAll('[data-settings-repeater]').forEach(container => {
    const list = container.querySelector('[data-settings-rows]'), template = container.querySelector('template'), add = container.querySelector('[data-settings-add]');
    const reindex = () => {
        [...list.children].forEach((row, index) => row.querySelectorAll('[data-setting-key]').forEach(input => { input.name = `${container.dataset.settingsRepeater}[${index}][${input.dataset.settingKey}]`; }));
        add.disabled = list.children.length >= 8;
    };
    add.hidden = false;
    add.addEventListener('click', () => {
        if (list.children.length >= 8) return;
        list.append(template.content.cloneNode(true)); reindex(); list.lastElementChild.querySelector('[data-settings-remove]').hidden = false;
        list.lastElementChild.querySelector('input').focus(); container.dispatchEvent(new Event('input', {bubbles: true}));
    });
    container.addEventListener('click', event => {
        const remove = event.target.closest('[data-settings-remove]'); if (!remove) return;
        remove.closest('[data-settings-row]').remove(); reindex(); add.focus(); container.dispatchEvent(new Event('input', {bubbles: true}));
    });
    reindex();
    list.querySelectorAll('[data-settings-remove]').forEach(button => { button.hidden = false; });
});

const lockedForms = new Map();
document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || (form.getAttribute('method') || 'get').toLowerCase() !== 'post') return;
    const action = event.submitter?.getAttribute('formaction');
    if (action && new URL(action, document.baseURI).pathname.endsWith('/preview')) return;
    if (lockedForms.has(form)) { event.preventDefault(); return; }
    queueMicrotask(() => {
        if (event.defaultPrevented) return;
        dirtyForms.delete(form);
        const buttons = [...form.querySelectorAll('button[type="submit"],button:not([type])')].filter(button => !button.disabled && !button.name);
        lockedForms.set(form, buttons); buttons.forEach(button => { button.disabled = true; });
        form.classList.add('is-submitting'); form.setAttribute('aria-busy', 'true');
        const status = form.querySelector('[data-unsaved-status]'); if (status) status.textContent = 'در حال ارسال درخواست…';
    });
});
window.addEventListener('pageshow', () => { for (const [form, buttons] of lockedForms) { buttons.forEach(button => { button.disabled = false; }); form.classList.remove('is-submitting'); form.removeAttribute('aria-busy'); } lockedForms.clear(); });
document.addEventListener('invalid', event => { const details = event.target.closest('details'); if (details) details.open = true; }, true);
document.addEventListener('DOMContentLoaded', () => {
    let sequence = 0;
    document.querySelectorAll('[data-field-error]').forEach(item => {
        const key = item.dataset.fieldError, name = key.replace(/\.([^\.]+)/g, '[$1]');
        const context = document.querySelector('[data-errors]')?.dataset.errorContext;
        const scope = [...document.querySelectorAll('[data-record-panel]')].find(panel => panel.id === context) || document;
        const input = [...scope.querySelectorAll('input,select,textarea')].find(control => [key, name, name + '[]'].includes(control.name) && control.type !== 'hidden');
        if (!input) return;
        input.id ||= `error-field-${++sequence}`;
        const patientPicker = input.hasAttribute('data-patient-choice') ? input.closest('[data-patient-picker]') : null;
        const visible = patientPicker?.querySelector('[data-patient-open]') || input._hcgApi?.element.querySelector('.hcg-select-control') || input;
        const message = document.createElement('span'); message.className = 'field-error'; message.id = `field-message-${++sequence}`; message.textContent = item.textContent;
        (patientPicker || input.closest('.ui-field') || input.closest('label') || input.parentElement).append(message);
        for (const control of new Set([input, visible])) { control.setAttribute('aria-invalid', 'true'); control.setAttribute('aria-describedby', [control.getAttribute('aria-describedby'), message.id].filter(Boolean).join(' ')); control.classList.add('field-invalid'); }
        let details = input.parentElement; while (details) { if (details instanceof HTMLDetailsElement) details.open = true; if (details.hasAttribute('data-schedule-shifts')) details.hidden = false; details = details.parentElement; }
        const link = document.createElement('a'); link.href = `#${input.id}`; link.textContent = item.textContent;
        link.addEventListener('click', event => { event.preventDefault(); visible.focus(); }); item.replaceChildren(link);
    });
});
