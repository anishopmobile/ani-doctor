'use strict';
const countdown = document.querySelector('[data-hold-expires]');
if (countdown) {
    const initial = Date.parse(countdown.dataset.serverNow);
    const deadline = Date.parse(countdown.dataset.holdExpires);
    const began = performance.now();
    function tick() {
        const seconds = Math.max(0, Math.ceil((deadline - initial - (performance.now() - began)) / 1000));
        countdown.textContent = seconds ? `مهلت رزرو موقت: ${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')} — هنوز قطعی نشده است.` : 'مهلت رزرو موقت تمام شد. دوباره ظرفیت را از تقویم بررسی کنید.';
        if (!seconds) document.querySelectorAll('[data-hold-action]').forEach(element => {
            element.disabled = true;
            element.removeAttribute('href');
            element.setAttribute('aria-disabled', 'true');
        });
    }
    tick();
    setInterval(tick, 1000);
}
