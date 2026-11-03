'use strict';
(() => {
    const byId = id => document.getElementById(id);
    const status = text => { byId('bale-status').textContent = text; };
    let services = [], reference = null, bookingKey = null;
    async function api(action, data = {}) {
        const form = data instanceof FormData;
        const headers = {Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-Bale-Init-Data': window.Bale?.WebApp?.initData || ''};
        if (!form) headers['Content-Type'] = 'application/json';
        const response = await fetch('/bale/' + action, {method: 'POST', headers, body: form ? data : JSON.stringify(data), credentials: 'same-origin'});
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.error?.message || 'درخواست انجام نشد. ورودی‌ها را بررسی و دوباره تلاش کنید.');
        return result.data;
    }
    function option(select, value, label) { const item = document.createElement('option'); item.value = value; item.textContent = label; select.append(item); }
    function ready(data) {
        services = data.services;
        byId('bale-login').hidden = true; byId('bale-booking').hidden = false;
        byId('bale-service').replaceChildren();
        services.forEach(s => option(byId('bale-service'), s.id, s.name + ' — ' + Number(s.price_irr).toLocaleString('fa-IR') + ' ریال'));
        ['bale-patient', 'bale-document-patient'].forEach(id => { byId(id).replaceChildren(); if (id === 'bale-patient') option(byId(id), '', 'بیمار جدید'); data.patients.forEach(p => option(byId(id), p.id, p.name)); });
        status(services.length ? 'حساب متصل است. خدمت و زمان مراجعه را انتخاب کنید.' : 'خدمتی برای رزرو آنلاین موجود نیست.');
    }
    function bind(id, handler) { byId(id).addEventListener('submit', async event => { event.preventDefault(); const button = event.submitter; if (button) button.disabled = true; try { await handler(new FormData(event.target)); } catch (error) { status(error.message); } finally { if (button) button.disabled = false; } }); }
    bind('bale-otp', async data => { await api('otp', Object.fromEntries(data)); status('رمز در بازوی رمز یک‌بارمصرف بله ارسال شد.'); });
    bind('bale-verify', async data => ready(await api('verify', Object.fromEntries(data))));
    bind('bale-days', async data => { const result = await api('days', Object.fromEntries(data)); byId('bale-slot').replaceChildren(); for (const day of result.days) for (const slot of day.slots) if (slot.is_bookable) option(byId('bale-slot'), slot.start_at_utc, day.jalali + ' ' + slot.local_start_time); status(byId('bale-slot').options.length ? 'زمان آزاد و بیمار را انتخاب کنید.' : 'در این هفته زمان آزادی موجود نیست.'); bookingKey = null; });
    byId('bale-service').addEventListener('change', () => { byId('bale-slot').replaceChildren(); bookingKey = null; });
    byId('bale-book').addEventListener('change', () => { bookingKey = null; });
    bind('bale-book', async data => {
        const service = services.find(s => String(s.id) === byId('bale-service').value);
        if (!service) throw new Error('خدمت را انتخاب کنید.');
        const payload = Object.fromEntries(data); if (!payload.patient_id) delete payload.patient_id; else delete payload.patient_name;
        bookingKey ||= crypto.randomUUID();
        const result = await api('book', {...payload, service_id: service.id, expected_price_irr: service.price_irr, idempotency_key: bookingKey});
        reference = result.reference;
        byId('bale-card').textContent = 'کارت: ' + result.card + '؛ ' + result.holder + '؛ مبلغ ' + Number(result.amount_irr).toLocaleString('fa-IR') + ' ریال؛ پایان مهلت: ' + new Date(result.deadline).toLocaleTimeString('fa-IR', {timeZone: 'Asia/Tehran'}) + '. رسید به‌موقع نوبت را قطعی می‌کند؛ تأیید وجه با مطب است.';
        byId('bale-payment').hidden = false; status('رزرو موقت ثبت شد. پیش از پایان مهلت رسید را بفرستید.');
    });
    bind('bale-receipt', async data => { if (!reference) throw new Error('ابتدا نوبت رزرو کنید.'); data.append('reference', reference); await api('receipt', data); byId('bale-payment').hidden = true; status('نوبت قطعی شد؛ پرداخت کارت‌به‌کارت در انتظار تأیید منشی است.'); });
    bind('bale-document', async data => { await api('document', data); status('مدرک خصوصی برای بررسی دریافت شد.'); });
    byId('bale-responses').addEventListener('click', async () => { try { const result = await api('responses', {patient_id: byId('bale-document-patient').value}); byId('bale-response-list').replaceChildren(); result.documents.forEach(doc => { const p = document.createElement('p'); p.textContent = ({pending: 'در انتظار بررسی', accepted: 'تأییدشده', rejected: 'ردشده'}[doc.state] || '') + (doc.response ? ': ' + doc.response : ''); byId('bale-response-list').append(p); }); status(result.documents.length ? 'پاسخ‌ها دریافت شد.' : 'مدرکی برای این بیمار ثبت نشده است.'); } catch (error) { status(error.message); } });
    if (!window.Bale?.WebApp?.initData) { status('این صفحه را از بازوی بله باز کنید.'); return; }
    api('me').then(ready).catch(() => { byId('bale-login').hidden = false; status('برای اتصال اولیه شماره خود را تأیید کنید. اگر اتصال قبلی دارید، مینی‌اپ را دوباره باز کنید.'); });
})();
