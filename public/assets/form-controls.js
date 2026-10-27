import './vendor/hcg-select.js';
import { PardisDatepicker, JalaaliUtil } from './vendor/pardis.js';

// Progressive controls: the original inputs, names and native submission stay authoritative.
const singles = new Set();
const dates = new Map();
let sequence = 0;
const digits = value => value.replace(/[۰-۹٠-٩]/g, c => String('۰۱۲۳۴۵۶۷۸۹'.includes(c) ? '۰۱۲۳۴۵۶۷۸۹'.indexOf(c) : '٠١٢٣٤٥٦٧٨٩'.indexOf(c))).replaceAll('/', '-');

function detachLabel(input) {
    const label = input.closest('label');
    if (!label || label.querySelectorAll('input,select,textarea').length !== 1) return;
    input.id ||= `ui-field-${++sequence}`;
    const field = document.createElement('div');
    field.className = `${label.className} ui-field`;
    label.before(field);
    field.append(label, input);
    label.htmlFor = input.id;
    label.classList.add('form-label');
}

function enhanceSelect(select) {
    if (select.dataset.uiSelect || !select.isConnected || select.closest('template')) return;
    select.dataset.uiSelect = 'ready';
    detachLabel(select);
    if (select.multiple) {
        const wrap = document.createElement('div');
        wrap.className = 'ui-multiple-select';
        const search = document.createElement('input');
        search.type = 'search'; search.className = 'form-control';
        search.placeholder = 'جستجو در گزینه‌ها…'; search.setAttribute('aria-label', 'جستجو در گزینه‌های چندانتخابی');
        search.autocomplete = 'off';
        const status = document.createElement('span'); status.className = 'visually-hidden'; status.setAttribute('role', 'status');
        select.before(wrap); wrap.append(search, select, status);
        const filter = () => {
            const query = search.value.trim().toLocaleLowerCase('fa'); let count = 0;
            for (const option of select.options) { option.hidden = !option.textContent.toLocaleLowerCase('fa').includes(query); if (!option.hidden) count++; }
            search.disabled = select.matches(':disabled'); status.textContent = `${count} گزینه`;
        };
        search.addEventListener('input', filter);
        const observer = new MutationObserver(filter); observer.observe(select, {childList: true, subtree: true, attributes: true, attributeFilter: ['disabled']});
        const form = select.form;
        const reset = () => { search.value = ''; filter(); };
        form?.addEventListener('reset', reset);
        select._uiCleanup = () => { observer.disconnect(); form?.removeEventListener('reset', reset); };
        singles.add(select); filter();
        return;
    }
    const api = window.hcgSelect(select, {placeholder: 'انتخاب کنید', searchPlaceholder: 'جستجو در گزینه‌ها…', noResultsText: 'گزینه‌ای پیدا نشد', clearable: false});
    if (api) { singles.add(select); select._uiCleanup = () => api.destroy(); }
}

function enhanceDate(input) {
    if (dates.has(input) || !input.isConnected) return;
    detachLabel(input);
    const wrap = document.createElement('div'); wrap.className = 'ui-date-field';
    const button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-outline-primary ui-date-button';
    button.textContent = 'انتخاب تاریخ'; button.setAttribute('aria-expanded', 'false');
    button.setAttribute('aria-label', `انتخاب تاریخ: ${input.labels?.[0]?.textContent.trim() || 'تاریخ شمسی'}`);
    const panel = document.createElement('div'); panel.className = 'ui-date-panel'; panel.hidden = true; panel.id = `ui-calendar-${++sequence}`;
    panel.setAttribute('role', 'dialog'); panel.setAttribute('aria-label', 'انتخاب تاریخ شمسی'); button.setAttribute('aria-controls', panel.id);
    const closeButton = document.createElement('button'); closeButton.type = 'button'; closeButton.className = 'btn btn-sm btn-outline-secondary'; closeButton.textContent = 'بستن تقویم';
    const host = document.createElement('div'); panel.append(closeButton, host); input.before(wrap); wrap.append(input, button, panel);
    let picker;
    const close = (focus = false) => { panel.hidden = true; button.setAttribute('aria-expanded', 'false'); if (focus) button.focus(); };
    const synchronize = () => { button.disabled = input.matches(':disabled') || input.readOnly; if (button.disabled || input.closest('[hidden]')) close(); };
    const open = () => {
        if (input.matches(':disabled') || input.readOnly) return;
        document.dispatchEvent(new CustomEvent('ui:calendar-open', {detail: panel.id}));
        if (picker) { picker.destroy(); host.replaceChildren(); }
        const value = digits(input.value); const parts = /^(1[34]\d{2})-(\d{2})-(\d{2})$/.exec(value);
        const today = JalaaliUtil.todayJalaali();
        const year = parts ? Number(parts[1]) : today.jy; const month = parts && Number(parts[2]) >= 1 && Number(parts[2]) <= 12 ? Number(parts[2]) : today.jm;
        let initializing = true;
        picker = new PardisDatepicker(host, {inline: true, numeralType: 'persian', initialYear: year, initialMonth: month,
            onChange: payload => { if (initializing) return; const day = payload.jalali; input.value = `${day.year}-${String(day.month).padStart(2, '0')}-${String(day.day).padStart(2, '0')}`; input.dispatchEvent(new Event('input', {bubbles: true})); input.dispatchEvent(new Event('change', {bubbles: true})); close(true); },
            onClear: () => { input.value = ''; input.dispatchEvent(new Event('input', {bubbles: true})); input.dispatchEvent(new Event('change', {bubbles: true})); close(true); }});
        if (parts && Number(parts[2]) === month && Number(parts[3]) >= 1 && Number(parts[3]) <= JalaaliUtil.jalaaliMonthLength(year, month)) picker.setValue(year, month, Number(parts[3]));
        initializing = false;
        panel.hidden = false; button.setAttribute('aria-expanded', 'true');
        (panel.querySelector('.pardis-day.selected') || panel.querySelector('.pardis-day:not(:disabled)'))?.focus();
    };
    button.addEventListener('click', () => panel.hidden ? open() : close(true)); closeButton.addEventListener('click', () => close(true));
    input.addEventListener('keydown', e => { if (e.altKey && e.key === 'ArrowDown') { e.preventDefault(); open(); } });
    wrap.addEventListener('keydown', e => { if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(true); } });
    const outside = e => { if (!wrap.contains(e.target)) close(); };
    const other = e => { if (e.detail !== panel.id) close(); };
    document.addEventListener('pointerdown', outside); document.addEventListener('ui:calendar-open', other);
    const observer = new MutationObserver(synchronize); observer.observe(input, {attributes: true, attributeFilter: ['disabled', 'readonly']});
    dates.set(input, {sync: synchronize, cleanup: () => { observer.disconnect(); picker?.destroy(); document.removeEventListener('pointerdown', outside); document.removeEventListener('ui:calendar-open', other); }}); synchronize();
}

function enhance(root) {
    if (!(root instanceof Element) && root !== document) return;
    if (root.matches?.('select')) enhanceSelect(root);
    if (root.matches?.('input[data-persian-date]')) enhanceDate(root);
    root.querySelectorAll('select').forEach(enhanceSelect);
    root.querySelectorAll('input[data-persian-date]').forEach(enhanceDate);
}
enhance(document);
new MutationObserver(records => {
    for (const record of records) {
        if (record.type === 'attributes' && record.target instanceof Element) {
            record.target.querySelectorAll('select').forEach(select => {
                select._hcgApi?.refresh();
                const search = select.closest('.ui-multiple-select')?.querySelector('input');
                if (search) search.disabled = select.matches(':disabled');
            });
            record.target.querySelectorAll('input[data-persian-date]').forEach(input => dates.get(input)?.sync());
        }
        for (const node of record.addedNodes) if (node.isConnected) enhance(node);
    }
    for (const select of singles) if (!select.isConnected) { select._uiCleanup?.(); singles.delete(select); }
    for (const [input, state] of dates) if (!input.isConnected) { state.cleanup(); dates.delete(input); }
}).observe(document.body, {childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'hidden']});

const toggle = document.querySelector('[data-menu-toggle]');
if (toggle) {
    toggle.hidden = false; document.body.classList.add('ui-ready');
    const close = () => { document.body.classList.remove('menu-open'); toggle.setAttribute('aria-expanded', 'false'); };
    toggle.addEventListener('click', () => { const open = document.body.classList.toggle('menu-open'); toggle.setAttribute('aria-expanded', String(open)); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && document.body.classList.contains('menu-open')) { close(); toggle.focus(); } });
}
