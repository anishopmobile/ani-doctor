(() => {
    'use strict';
    function fallback(image) {
        if (!(image instanceof HTMLImageElement) || image.dataset.serviceFallbackApplied) return;
        if (image.matches('[data-home-services-image]')) {
            image.dataset.serviceFallbackApplied = 'true';
            const target = new URL(image.dataset.fallbackSrc, location.href);
            if (target.origin === location.origin && image.src !== target.href) image.src = target.href;
            image.closest('.hs-frame').classList.add('hs-frame--fallback');
        } else if (image.matches('[data-home-service-card-image]')) {
            image.dataset.serviceFallbackApplied = 'true';
            image.hidden = true;
            image.parentElement.querySelector('[data-home-service-card-fallback]').hidden = false;
        }
    }
    document.addEventListener('error', event => fallback(event.target), true);
    document.querySelectorAll('[data-home-services-image], [data-home-service-card-image]').forEach(image => {
        if (image.complete && image.naturalWidth === 0) fallback(image);
    });
})();
