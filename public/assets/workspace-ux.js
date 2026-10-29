'use strict';
const recordPanels = [...document.querySelectorAll('[data-record-panel]')];
for (const panel of recordPanels) {
    for (const form of panel.querySelectorAll('form')) {
        if ((form.getAttribute('method') || '').toLowerCase() !== 'post' || form.querySelector('[name="_ux_context"]')) continue;
        const context = document.createElement('input'); context.type = 'hidden'; context.name = '_ux_context'; context.value = panel.id; form.append(context);
    }
}
const tools = document.querySelector('[data-record-tools]');
if (tools) {
    const filter = tools.querySelector('[data-record-filter]');
    const list = recordPanels.filter(panel => panel.dataset.recordCreate !== 'true');
    const normalize = value => value.normalize('NFKC').replaceAll('ي', 'ی').replaceAll('ك', 'ک').toLocaleLowerCase('fa');
    function refresh() {
        const query = normalize(filter.value.trim()); let count = 0;
        for (const panel of list) { panel.hidden = !normalize(panel.dataset.recordSearch || '').includes(query); if (!panel.hidden) count++; }
        tools.querySelector('[data-record-count]').textContent = `${count.toLocaleString('fa-IR')} مورد از ${list.length.toLocaleString('fa-IR')} مورد این صفحه نمایش داده می‌شود.`;
    }
    filter.addEventListener('input', refresh);
    tools.querySelector('[data-record-clear]').addEventListener('click', () => { filter.value = ''; refresh(); filter.focus(); });
    tools.hidden = !list.length; refresh();
}
for (const link of document.querySelectorAll('[data-open-record]')) {
    link.addEventListener('click', event => {
        const panel = document.getElementById(link.dataset.openRecord);
        if (!panel?.matches('[data-record-panel]')) return;
        // Own the in-page action so fragment navigation cannot take focus back.
        event.preventDefault();
        panel.hidden = false; panel.open = true;
        panel.querySelector('input:not([type=hidden]),textarea,select')?.focus();
    });
}
document.addEventListener('invalid', event => {
    let node = event.target.parentElement;
    while (node) { if (node instanceof HTMLDetailsElement) node.open = true; node = node.parentElement; }
}, true);
// Derived display only: the backend still validates schedule and report rules.
for (const form of document.querySelectorAll('[data-schedule-form]')) {
    const closed = form.querySelector('[name="is_closed"][type="checkbox"]');
    const kind = form.querySelector('[name="kind"]');
    const fields = form.querySelector('[data-schedule-shifts]');
    if (!fields) continue;
    const sync = () => { const hide = closed ? closed.checked : kind?.value === 'closed'; fields.hidden = hide; };
    closed?.addEventListener('change', sync); kind?.addEventListener('change', sync); sync();
}
const reportForm = document.querySelector('[data-report-form]');
if (reportForm) {
    const kind = reportForm.querySelector('[name="kind"]');
    const filters = reportForm.querySelector('[data-appointment-filters]');
    const sync = () => { filters.hidden = kind.value !== 'appointments'; filters.disabled = filters.hidden; };
    kind.addEventListener('change', sync); sync();
}

for (const link of document.querySelectorAll('.ux-task-links a')) { if (new URL(link.href).pathname === location.pathname && !new URL(link.href).hash) link.setAttribute('aria-current','page'); }

for (const map of document.querySelectorAll('[data-site-map]')) map.open = matchMedia('(min-width: 992px)').matches;
