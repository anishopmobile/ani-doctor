import { PardisDatepicker } from './vendor/pardis.js';

const root = document.querySelector('#booking-calendar');
if (root) {
    const service = root.querySelector('#service');
    const host = root.querySelector('#inline-calendar');
    const status = root.querySelector('#calendar-status');
    const slotStatus = root.querySelector('#slots-status');
    const slots = root.querySelector('#slots');
    const selection = root.querySelector('#slot-selection');
    const retry = root.querySelector('#calendar-retry');
    const overrideChoice = root.querySelector('input[type="checkbox"][name="override_capacity"]');
    const canChooseDay = state => state === 'available' || (overrideChoice?.checked && state === 'full');
    const labels = { available: 'آزاد', full: 'تکمیل', holiday: 'تعطیل', closed: 'بسته', past: 'گذشته', outside_window: 'خارج افق' };
    const pad = value => String(value).padStart(2, '0');
    const key = (year, month, day) => `${year}-${pad(month)}-${pad(day)}`;
    let states = new Map();
    let selectedDate = null;
    let monthAbort;
    let dayAbort;
    let revision = 0;
    let retryAction;
    const [year, month] = root.dataset.month.split('-').map(Number);

    const picker = new PardisDatepicker(host, {
        inline: true, initialYear: year, initialMonth: month,
        disabledDates: (y, m, d) => !canChooseDay(states.get(key(y, m, d))?.status),
        onChange: payload => {
            selectedDate = payload.iso;
            loadDay();
            queueMicrotask(() => {
                decorate();
                host.querySelector('.pardis-day.selected')?.focus();
            });
        },
        onClear: () => { selectedDate = null; clearSlots(); queueMicrotask(decorate); }
    });

    function clearSlots() {
        dayAbort?.abort();
        slots.replaceChildren();
        selection.textContent = '';
        slotStatus.textContent = 'یک روز قابل رزرو انتخاب کنید.';
        const form = root.querySelector('[data-booking-form]');
        if (form) {
            form.querySelector('[data-booking-start]').value = '';
            form.querySelector('[data-booking-submit]').disabled = true;
        }
        root.dispatchEvent(new CustomEvent('booking:selection-reset'));
    }

    async function get(url, parameters, signal) {
        const query = new URLSearchParams({ service_id: service.value, ...parameters });
        const requestSignal = AbortSignal.any([signal, AbortSignal.timeout(10000)]);
        const response = await fetch(`${url}?${query}`, { signal: requestSignal, headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
        const json = await response.json();
        if (!response.ok || !json.success) throw new Error(json.error?.message || 'دریافت اطلاعات تکمیل نشد.');
        return json.data;
    }

    function decorate() {
        host.querySelectorAll('.pardis-day[data-day]').forEach(cell => {
            const state = states.get(key(cell.dataset.year, cell.dataset.month, cell.dataset.day));
            if (!state) return;
            cell.classList.add(`day-${state.status}`);
            const text = labels[state.status];
            cell.dataset.baseLabel ||= cell.getAttribute('aria-label');
            cell.setAttribute('aria-label', `${cell.dataset.baseLabel} — ${text}`);
            cell.title = text;
            cell.querySelector('.day-state')?.remove();
            const label = document.createElement('span');
            label.className = 'day-state';
            label.textContent = text;
            cell.append(label);
        });
    }

    async function loadMonth() {
        monthAbort?.abort();
        dayAbort?.abort();
        const current = ++revision;
        const info = picker.engine.getViewInfo();
        monthAbort = new AbortController();
        states = new Map();
        picker.setOption('disabledDates', () => true);
        clearSlots();
        status.textContent = 'در حال دریافت وضعیت روزها…';
        host.setAttribute('aria-busy', 'true');
        retry.hidden = true;
        if (!service.value) {
            status.textContent = 'خدمتی برای نمایش فعال نیست.';
            host.setAttribute('aria-busy', 'false');
            return;
        }
        try {
            const data = await get(root.dataset.monthUrl, { month: `${info.year}-${pad(info.month)}` }, monthAbort.signal);
            if (current !== revision) return;
            states = new Map(data.days.map(day => [day.jalali, day]));
            picker.setOption('disabledDates', (y, m, d) => !canChooseDay(states.get(key(y, m, d))?.status));
            decorate();
            status.textContent = 'وضعیت روزها به‌روز شد. روزهای آزاد قابل انتخاب‌اند.';
            if (selectedDate && data.days.some(day => day.date === selectedDate && canChooseDay(day.status))) {
                await loadDay();
            } else selectedDate = null;
        } catch (error) {
            if (error.name === 'AbortError' || current !== revision) return;
            status.textContent = 'دریافت وضعیت روزها انجام نشد. دوباره تلاش کنید.';
            retryAction = loadMonth;
            retry.hidden = false;
        } finally {
            if (current === revision) host.setAttribute('aria-busy', 'false');
        }
    }

    async function loadDay() {
        dayAbort?.abort();
        const controller = new AbortController();
        dayAbort = controller;
        slots.replaceChildren();
        selection.textContent = '';
        slotStatus.textContent = 'در حال دریافت ساعت‌های آزاد…';
        slots.setAttribute('aria-busy', 'true');
        retry.hidden = true;
        try {
            const data = await get(root.dataset.dayUrl, { date: selectedDate }, controller.signal);
            if (controller.signal.aborted) return;
            const persianDate = data.jalali.replaceAll('-', '/').replace(/[0-9]/g, digit => '۰۱۲۳۴۵۶۷۸۹'[digit]);
            slotStatus.textContent = data.status === 'available' ? `ساعت‌های روز \u2066${persianDate}\u2069` : (labels[data.status] || 'زمان آزادی موجود نیست.');
            data.slots.forEach(slot => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-outline-primary';
                button.disabled = !slot.is_bookable && !(overrideChoice?.checked && slot.unavailable_reason === 'full');
                button.textContent = slot.local_start_time;
                button.setAttribute('aria-label', `${slot.local_start_time} — ${slot.is_bookable ? 'آزاد' : labels[slot.unavailable_reason]}`);
                button.addEventListener('click', () => {
                    const deskMode = root.querySelector('[name="booking_kind"]:checked');
                    if (deskMode?.value === 'urgent') {
                        selection.textContent = 'برای نوبت فوری ساعت امروز را در فرم پایین تعیین کنید؛ این ساعت تقویم انتخاب نشد.';
                        return;
                    }
                    slots.querySelectorAll('button').forEach(item => item.setAttribute('aria-pressed', 'false'));
                    button.setAttribute('aria-pressed', 'true');
                    selection.textContent = `زمان انتخابی: ${slot.local_start_time}؛ هنوز نوبتی ثبت نشده است.`;
                    const form = root.querySelector('[data-booking-form]');
                    if (form) {
                        form.querySelector('[data-booking-start]').value = slot.start_at_utc;
                        const serviceField = form.querySelector('[data-booking-service]');
                        if (serviceField) {
                            serviceField.value = service.value;
                            form.querySelector('[data-booking-price]').value = service.selectedOptions[0].dataset.price;
                        }
                        form.querySelector('[data-booking-submit]').disabled = false;
                    }
                });
                slots.append(button);
            });
        } catch (error) {
            if (error.name === 'AbortError') return;
            slotStatus.textContent = 'دریافت ساعت‌ها انجام نشد. دوباره تلاش کنید.';
            retryAction = loadDay;
            retry.hidden = false;
        } finally {
            if (!controller.signal.aborted) slots.setAttribute('aria-busy', 'false');
        }
    }
    picker.engine.on('viewChange', () => { selectedDate = null; loadMonth(); });
    service.addEventListener('change', loadMonth);
    overrideChoice?.addEventListener('change', loadMonth);
    retry.addEventListener('click', () => retryAction?.());
    loadMonth();
    const patientChoice = root.querySelector('[data-patient-choice]');
    patientChoice?.addEventListener('change', () => {
        root.querySelector('[data-patient-name]').required = !patientChoice.value;
    });
}
