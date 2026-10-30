(() => {
    'use strict';
    function fallback(image) {
        if (!(image instanceof HTMLImageElement) || !image.matches('[data-home-about-image]') || image.dataset.fallbackApplied) return;
        image.dataset.fallbackApplied = 'true';
        const target = new URL(image.dataset.fallbackSrc, location.href);
        if (target.origin === location.origin && image.src !== target.href) image.src = target.href;
        image.closest('.ha-frame').classList.add('ha-frame--fallback');
    }
    document.addEventListener('error', event => fallback(event.target), true);
    document.querySelectorAll('[data-home-about-image]').forEach(image => {
        if (image.complete && image.naturalWidth === 0) fallback(image);
    });
})();
