for (const box of document.querySelectorAll('[data-discount-quote]')) {
    const form = box.closest('form');
    const button = box.querySelector('[data-discount-preview]');
    const result = box.querySelector('[data-discount-result]');
    let generation = 0;
    const invalidate = () => { generation++; result.textContent = 'برای مبلغ تازه دوباره برآورد را بزنید؛ تأیید نهایی در سرور است.'; };
    box.querySelector('input').addEventListener('input', invalidate);
    document.querySelector('#service')?.addEventListener('change', invalidate);
    document.querySelector('#booking-calendar')?.addEventListener('booking:selection-reset', invalidate);
    button.addEventListener('click', async () => {
        const service = form.querySelector('[data-booking-service]').value;
        if (!service) { result.textContent = 'ابتدا خدمت و ساعت را انتخاب کنید.'; return; }
        const current = ++generation;
        button.disabled = true;
        result.textContent = 'در حال دریافت مبلغ…';
        try {
            const response = await fetch(box.dataset.url, {method:'POST',credentials:'same-origin',cache:'no-store',signal:AbortSignal.timeout(10000),headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':form.querySelector('[name="_token"]').value},body:JSON.stringify({service_id:service,discount_code:box.querySelector('input').value})});
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error('unavailable');
            if (current === generation) result.textContent = `تعرفه: ${data.data.base_irr.toLocaleString('fa-IR')} ریال؛ تخفیف: ${data.data.discount_irr.toLocaleString('fa-IR')} ریال؛ مبلغ کامل نهایی: ${data.data.net_irr.toLocaleString('fa-IR')} ریال. سهمیه هنگام ثبت بررسی می‌شود.`;
        } catch (_) {
            if (current === generation) result.textContent = 'برآورد دریافت نشد یا کد قابل استفاده نیست؛ کد را بررسی و دوباره تلاش کنید.';
        } finally { button.disabled = false; }
    });
}
