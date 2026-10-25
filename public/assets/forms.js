/* Local progressive enhancement; server validation and authorization remain authoritative. */
const builder = document.querySelector('[data-form-builder]');
if (builder) {
    const fields = builder.querySelector('[data-fields]');
    let sequence = Math.max(fields.children.length, ...[...fields.querySelectorAll('[data-field-key]')].map(input => (Number(input.value.replace('field_', '')) || 0) + 1));
    const updateConditions = () => {
        const previous = [];
        for (const row of fields.children) {
            const select = row.querySelector('[data-condition-source]');
            const selected = select.value || select.dataset.selected;
            select.replaceChildren(new Option('همیشه نمایش داده شود', ''));
            for (const p of previous) select.add(new Option(p.label, p.key));
            select.value = selected || '';
            select.dataset.selected = '';
            const type = row.querySelector('[name$="[type]"]').value;
            if (['select', 'checkbox'].includes(type)) previous.push({key: row.querySelector('[data-field-key]').value, label: row.querySelector('[name$="[label]"]').value || 'فیلد قبلی'});
        }
    };
    builder.querySelector('[data-add-field]').addEventListener('click', () => {
        if (fields.children.length >= 30) { builder.querySelector('[data-builder-status]').textContent = 'حداکثر ۳۰ فیلد مجاز است.'; return; }
        const fragment = builder.querySelector('template').content.cloneNode(true);
        for (const element of fragment.querySelectorAll('[name]')) element.name = element.name.replaceAll('NEW', String(sequence));
        fragment.querySelector('[data-field-key]').value = 'field_' + sequence++;
        fields.append(fragment);
        updateConditions();
        fields.lastElementChild.querySelector('input').focus();
    });
    fields.addEventListener('click', event => { if (event.target.matches('[data-remove-field]')) { event.target.closest('fieldset').remove(); updateConditions(); } });
    fields.addEventListener('change', updateConditions);
    updateConditions();
}
const privateForm = document.querySelector('[data-private-form]');
if (privateForm) {
    const update = () => {
        const values = {};
        for (const row of privateForm.querySelectorAll('[data-answer-field]')) {
            const input = row.querySelector('[data-answer]');
            const visible = !row.dataset.source || (Object.hasOwn(values, row.dataset.source) && values[row.dataset.source] === row.dataset.equals);
            row.hidden = !visible;
            if (!input.dataset.readonly) { input.dataset.readonly = input.disabled ? 'yes' : 'no'; }
            input.disabled = !visible || input.dataset.readonly === 'yes';
            input.required = visible && input.hasAttribute('data-required') && !input.disabled;
            if (visible) values[row.dataset.key] = input.value;
        }
    };
    privateForm.addEventListener('change', update);
    update();
}
