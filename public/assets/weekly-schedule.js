document.querySelectorAll('[data-weekly-editor]').forEach(form => {
    const mode = form.querySelector('[name="mode"]');
    const status = form.querySelector('[name="status"]');
    const refresh = () => {
        const manual = mode.value === 'manual';
        form.querySelector('[data-weekly-manual]').hidden = !manual;
        status.disabled = !manual;
        const working = manual && status.value === 'working';
        const shifts = form.querySelector('[data-weekly-shifts]');
        shifts.hidden = !working;
        shifts.querySelectorAll('input').forEach(input => { input.disabled = !working; });
    };
    mode.addEventListener('change', refresh);
    status.addEventListener('change', refresh);
    refresh();
    form.addEventListener('submit', () => {
        form.querySelector('button[type="submit"], button:not([type])').disabled = true;
        form.querySelector('.weekly-save-state').hidden = false;
    });
});
