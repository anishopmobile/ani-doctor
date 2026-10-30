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
    const form = document.querySelector('.articles-filters');
    const results = document.querySelector('[data-article-results]');
    if (form && results && window.fetch && window.AbortController) {
        const status = document.querySelector('[data-article-status]');
        const error = document.querySelector('[data-article-error]');
        let controller, timer, revision = 0, retry;
        const busy = value => { results.setAttribute('aria-busy', String(value)); results.inert = value; };
        function invalidate() {
            clearTimeout(timer);
            revision++;
            controller?.abort();
        }
        function formUrl() {
            const url = new URL(form.action);
            for (const [key, value] of new FormData(form)) {
                if (typeof value === 'string' && value.trim()) url.searchParams.set(key, value.trim());
            }
            return url;
        }
        function syncForm(url) {
            for (const name of ['q', 'category', 'sort', 'period']) {
                const value = url.searchParams.get(name) || ({sort:'newest', period:'all'}[name] || '');
                if (form.elements[name].value !== value) form.elements[name].value = value;
            }
            let tag = form.querySelector('[name=tag]');
            if (url.searchParams.has('tag')) {
                if (!tag) { tag = document.createElement('input'); tag.type = 'hidden'; tag.name = 'tag'; form.append(tag); }
                tag.value = url.searchParams.get('tag');
            } else tag?.remove();
        }
        async function load(url, push = true, focus = false, sync = false) {
            invalidate();
            const current = revision;
            controller = new AbortController();
            retry = () => load(url, push, focus, sync);
            busy(true); error.hidden = true; status.textContent = 'در حال دریافت مقالات…';
            try {
                const response = await fetch(url, {cache:'no-store', headers:{Accept:'application/json', 'X-Requested-With':'XMLHttpRequest'}, signal:controller.signal});
                if (!response.ok || response.redirected) throw new Error('request');
                const data = await response.json();
                if (current !== revision) return;
                if (typeof data.html !== 'string') throw new Error('response');
                const template = document.createElement('template');
                template.innerHTML = data.html;
                if (!template.content.querySelector('.articles-grid')) throw new Error('fragment');
                results.replaceChildren(template.content);
                if (push && location.href !== url.href) history.pushState(null, '', url);
                if (sync) syncForm(url);
                status.textContent = results.querySelector('.articles-results p').textContent;
                results.querySelectorAll('[data-article-image]').forEach(image => {
                    if (image.complete && image.naturalWidth === 0) fallback(image);
                });
                busy(false);
                if (focus) { results.focus({preventScroll:true}); results.scrollIntoView({block:'start'}); }
            } catch (failure) {
                if (current !== revision || failure.name === 'AbortError') return;
                status.textContent = '';
                error.querySelector('[data-article-error-text]').textContent = 'دریافت مقالات انجام نشد. اتصال و فیلترها را بررسی کنید و دوباره تلاش کنید. نتایج قبلی حفظ شده‌اند.';
                error.hidden = false;
            } finally {
                if (current === revision) busy(false);
            }
        }
        form.addEventListener('submit', event => { event.preventDefault(); load(formUrl()); });
        form.addEventListener('change', event => { if (event.target.matches('select')) load(formUrl()); });
        form.elements.q.addEventListener('input', event => {
            if (event.isComposing) return;
            invalidate(); busy(true); error.hidden = true; status.textContent = 'در حال دریافت مقالات…';
            timer = setTimeout(() => load(formUrl()), 300);
        });
        form.elements.q.addEventListener('compositionend', () => load(formUrl()));
        form.elements.q.addEventListener('compositionstart', () => {
            invalidate(); busy(true); error.hidden = true; status.textContent = 'در حال دریافت مقالات…';
        });
        results.addEventListener('click', event => {
            const link = event.target.closest('a[href]');
            if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target || link.hasAttribute('download')) return;
            const url = new URL(link.href);
            if (url.origin !== location.origin || url.pathname !== new URL(form.action).pathname) return;
            event.preventDefault(); load(url, true, Boolean(link.closest('.articles-pagination')), true);
        });
        document.querySelector('[data-article-retry]').addEventListener('click', () => retry?.());
        window.addEventListener('popstate', () => { const url = new URL(location.href); syncForm(url); load(url, false); });
    }
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
