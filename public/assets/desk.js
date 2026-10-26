'use strict';
const deskForm = document.querySelector('[data-desk-booking]');
if (deskForm) {
    const root = document.querySelector('#booking-calendar');
    const service = root.querySelector('#service');
    const submit = deskForm.querySelector('[data-booking-submit]');
    const start = deskForm.querySelector('[data-booking-start]');
    const time = deskForm.querySelector('[data-urgent-time]');
    const urgentFields = deskForm.querySelector('[data-urgent-fields]');
    const kind = () => deskForm.querySelector('[name="booking_kind"]:checked')?.value;
    function urgentSelection() {
        if (kind() !== 'urgent') return;
        start.value = time.value ? `${deskForm.querySelector('[name="urgent_date"]').value}T${time.value}:00${deskForm.dataset.offset}` : '';
        deskForm.querySelector('[data-booking-service]').value = service.value;
        deskForm.querySelector('[data-booking-price]').value = service.selectedOptions[0]?.dataset.price || '';
        submit.disabled = !time.value || !service.value;
    }
    function changeKind() {
        urgentFields.hidden = kind() !== 'urgent';
        urgentFields.querySelectorAll('input,button').forEach(input => { input.disabled = urgentFields.hidden; });
        time.required = !urgentFields.hidden;
        start.value = '';
        submit.disabled = true;
        urgentSelection();
    }
    deskForm.querySelectorAll('[name="booking_kind"]').forEach(radio => radio.addEventListener('change', changeKind));
    time.addEventListener('input', urgentSelection);
    root.addEventListener('booking:selection-reset', urgentSelection);
    service.addEventListener('change', urgentSelection);
    const serverTime = Date.parse(deskForm.dataset.serverNow);
    const began = performance.now();
    deskForm.querySelector('[data-urgent-now]')?.addEventListener('click', () => {
        const nextMinute = Math.ceil((serverTime + performance.now() - began + 1) / 60000) * 60000;
        time.value = new Intl.DateTimeFormat('en-GB', {timeZone:'Asia/Tehran', hour:'2-digit', minute:'2-digit', hourCycle:'h23'}).format(new Date(nextMinute));
        urgentSelection();
    });
    const payment = deskForm.querySelector('[data-desk-settlement]');
    function settlement() {
        const fields = deskForm.querySelector('[data-clinic-paid]');
        fields.hidden = payment.value !== 'clinic_paid';
        fields.disabled = fields.hidden;
    }
    payment.addEventListener('change', settlement);
    const patientChoice = deskForm.querySelector('[data-patient-choice]');
    function patientSelection() {
        const fields = deskForm.querySelector('[data-new-patient]');
        fields.disabled = !!patientChoice.value;
        fields.hidden = !!patientChoice.value;
        deskForm.querySelector('[data-patient-name]').required = !patientChoice.value;
    }
    patientChoice.addEventListener('change', patientSelection);
    const find = deskForm.querySelector('[data-patient-find]');
    let searchController;
    async function search() {
        searchController?.abort();
        const controller = new AbortController();
        searchController = controller;
        const status = deskForm.querySelector('[data-patient-search-status]');
        const results = deskForm.querySelector('[data-patient-results]');
        results.replaceChildren();
        status.textContent = 'در حال جستجوی بیماران مجاز…';
        find.disabled = true;
        results.setAttribute('aria-busy','true');
        try {
            const response = await fetch(deskForm.dataset.searchUrl, {method:'POST', credentials:'same-origin', cache:'no-store', signal:AbortSignal.any([controller.signal,AbortSignal.timeout(10000)]), headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':deskForm.querySelector('[name="_token"]').value}, body:JSON.stringify({query:deskForm.querySelector('[data-patient-search]').value})});
            const json = await response.json();
            if (!response.ok || !json.success) throw new Error('search failed');
            status.textContent = json.data.patients.length ? 'بیمار درست را با شماره پرونده و تماس انتخاب کنید؛ حداکثر ۲۰ نتیجه.' : 'بیماری پیدا نشد؛ می‌توانید بیمار جدید و مستقل ثبت کنید.';
            json.data.patients.forEach(patient => {
                const label = `${patient.name} — پرونده: ${patient.file_number || 'ندارد'} — تماس: ${patient.contact_mobile || 'ندارد'}`;
                const button = document.createElement('button');
                button.type = 'button'; button.className = 'btn btn-outline-primary'; button.textContent = label;
                button.addEventListener('click', () => {
                    let option = Array.from(patientChoice.options).find(item => item.value === String(patient.id));
                    if (!option) { option = new Option(label,String(patient.id)); patientChoice.add(option); }
                    patientChoice.value = String(patient.id); patientSelection(); status.textContent = `انتخاب شد: ${label}`;
                });
                results.append(button);
            });
        } catch (error) {
            if (error.name !== 'AbortError') status.textContent = 'جستجو تکمیل نشد؛ دوباره دکمه جستجو را بزنید. با ادامه، بیمار جدید خودکار انتخاب نمی‌شود.';
        } finally {
            if (searchController === controller) { find.disabled = false; results.setAttribute('aria-busy','false'); }
        }
    }
    find?.addEventListener('click', search);
    deskForm.querySelector('[data-patient-search]')?.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); search(); } });
    changeKind(); settlement(); patientSelection();
}
