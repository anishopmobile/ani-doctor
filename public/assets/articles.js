(() => {
    'use strict';
    function fallback(image) {
        if (!(image instanceof HTMLImageElement) || !image.matches('[data-article-image]') || image.dataset.fallbackApplied) return;
        image.dataset.fallbackApplied = 'true';
        const target = new URL(image.dataset.fallbackSrc, location.href);
        if (target.origin === location.origin && image.src !== target.href) image.src = target.href;
    }
    document.addEventListener('error', event => fallback(event.target), true);
    document.querySelectorAll('[data-article-image]').forEach(image => {
        if (image.complete && image.naturalWidth === 0) fallback(image);
    });
    document.querySelectorAll('[data-copy-article]').forEach(button => button.addEventListener('click', async () => {
        const status = document.querySelector('[data-share-status]');
        button.disabled = true;
        status.textContent = 'در حال کپی پیوند…';
        try {
            await navigator.clipboard.writeText(button.dataset.copyArticle);
            status.textContent = 'پیوند مقاله کپی شد.';
        } catch {
            status.textContent = 'کپی خودکار در دسترس نیست؛ پیوند مقاله را از کادر کنار دکمه کپی کنید.';
        } finally {
            button.disabled = false;
        }
    }));
})();
