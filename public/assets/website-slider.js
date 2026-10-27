/* Homepage slider: manual controls, no autoplay, no third-party dependencies. */
'use strict';

document.querySelectorAll('[data-website-slider]').forEach((slider) => {
    const slides = Array.from(slider.querySelectorAll('[data-website-slide]'));
    const previous = slider.querySelector('[data-slide-prev]');
    const next = slider.querySelector('[data-slide-next]');
    const counter = slider.querySelector('[data-slide-counter]');
    if (slides.length < 2 || !previous || !next) return;

    let active = 0;
    const show = (index) => {
        active = (index + slides.length) % slides.length;
        slides.forEach((slide, i) => {
            slide.hidden = i !== active;
        });
        if (counter) counter.textContent = `${active + 1} از ${slides.length}`;
    };

    previous.addEventListener('click', () => show(active - 1));
    next.addEventListener('click', () => show(active + 1));
    slider.addEventListener('keydown', (event) => {
        if (event.target instanceof HTMLElement &&
            ['INPUT', 'SELECT', 'TEXTAREA'].includes(event.target.tagName)) return;
        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            show(active + 1);
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            show(active - 1);
        }
    });
});
