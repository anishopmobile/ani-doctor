(() => {
    'use strict';
    function fallback(image) {
        if (!(image instanceof HTMLImageElement) || !image.matches('[data-home-contact-image]') || image.dataset.contactFallbackApplied) return;
        image.dataset.contactFallbackApplied = 'true';
        const target = new URL(image.dataset.fallbackSrc, location.href);
        if (target.origin === location.origin && image.src !== target.href) image.src = target.href;
        image.closest('.hc-frame').classList.add('hc-frame--fallback');
    }
    document.addEventListener('error', event => fallback(event.target), true);
    document.querySelectorAll('[data-home-contact-image]').forEach(image => {
        if (image.complete && image.naturalWidth === 0) fallback(image);
    });
})();
