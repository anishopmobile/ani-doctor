'use strict';
for (const picker of document.querySelectorAll('[data-patient-picker]')) {
    const form = picker.closest('form');
    const select = picker.querySelector('[data-patient-choice]');
    const dialog = picker.querySelector('dialog');
    if (!dialog.showModal) continue;
    const open = picker.querySelector('[data-patient-open]');
    const filter = picker.querySelector('[data-patient-filter]');
    const options = picker.querySelector('[data-patient-options]');
    function close() { dialog.close(); open.focus(); }
    function sync() {
        const existing = !!select.value;
        const fields = form.querySelector('[data-new-patient]');
        fields.hidden = existing; fields.disabled = existing;
        fields.querySelector('[data-patient-name]').required = !existing;
        picker.querySelector('[data-selected-patient]').textContent = select.selectedOptions[0]?.textContent || 'بیمار جدید';
        picker.querySelector('[data-selected-patient-help]').textContent = existing ? 'این بیمار انتخاب شده است. برای شخص دیگری، انتخاب را تغییر دهید.' : 'اطلاعات بیمار را در کادرهای زیر وارد کنید.';
    }
    function choose(value) { select.value = value; select.dispatchEvent(new Event('change', {bubbles:true})); close(); if (!value) form.querySelector('[data-patient-name]').focus(); }
    function render() {
        options.replaceChildren(); let count = 0;
        for (const option of select.options) {
            if (!option.value || !option.textContent.toLocaleLowerCase('fa').includes(filter.value.trim().toLocaleLowerCase('fa'))) continue;
            const row = document.createElement('div'); row.className = 'patient-option';
            const label = document.createElement('span'); label.textContent = option.textContent;
            const button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-outline-primary'; button.textContent = 'انتخاب'; button.setAttribute('aria-label', `انتخاب ${option.textContent}`);
            button.addEventListener('click', () => choose(option.value)); row.append(label,button); options.append(row); count++;
        }
        picker.querySelector('[data-patient-empty]').hidden = !!count;
    }
    open.addEventListener('click', () => {
        filter.value = ''; render();
        picker.querySelector('[data-patient-results]')?.replaceChildren();
        const status = picker.querySelector('[data-patient-search-status]'); if (status) status.textContent = '';
        dialog.showModal(); filter.focus();
    });
    picker.querySelector('[data-patient-close]').addEventListener('click', close);
    dialog.addEventListener('cancel', () => { requestAnimationFrame(() => open.focus()); });
    dialog.addEventListener('keydown', event => { if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); close(); } });
    filter.addEventListener('input', render);
    filter.addEventListener('keydown', event => { if (event.key === 'Enter') event.preventDefault(); });
    picker.querySelector('[data-patient-new]').addEventListener('click', () => choose(''));
    select.addEventListener('change', () => { sync(); if (dialog.open) close(); });
    picker.querySelector('[data-patient-native]').hidden = true;
    picker.querySelector('[data-patient-enhanced]').hidden = false;
    sync();
}
const bookingRoot = document.querySelector('#booking-calendar');
if (bookingRoot) {
    const form = bookingRoot.querySelector('[data-booking-form]');
    const summary = bookingRoot.querySelector('[data-booking-summary]');
    const service = bookingRoot.querySelector('#service');
    function refreshSummary() {
        if (!summary) return;
        summary.querySelector('[data-summary-service]').textContent = service.selectedOptions[0]?.textContent || 'خدمتی انتخاب نشده';
        const selected = bookingRoot.querySelector('#slot-selection').textContent;
        summary.querySelector('[data-summary-time]').textContent = selected || 'روز و ساعت را از تقویم انتخاب کنید.';
        const ready = !!form?.querySelector('[data-booking-start]').value;
        summary.querySelector('[data-summary-continue]').hidden = !ready;
        summary.querySelector('[data-summary-next]').textContent = ready ? 'اکنون مشخصات بیمار را بررسی و دکمه ثبت را بزنید.' : 'ابتدا یک روز آزاد و سپس ساعت مراجعه را انتخاب کنید.';
    }
    new MutationObserver(refreshSummary).observe(bookingRoot.querySelector('#slot-selection'), {childList:true,characterData:true,subtree:true});
    bookingRoot.addEventListener('booking:selection-reset', refreshSummary);
    service.addEventListener('change', refreshSummary);
    form?.addEventListener('input', refreshSummary);
    refreshSummary();
}
