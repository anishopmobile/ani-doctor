import assert from 'node:assert/strict';
import {mkdir} from 'node:fs/promises';
const {chromium} = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = 'http://127.0.0.1:18558';
const browser = await chromium.launch({headless: true, ...(process.env.CHROME_EXECUTABLE ? {executablePath: process.env.CHROME_EXECUTABLE} : {})});
const context = await browser.newContext();
await context.grantPermissions(['clipboard-read', 'clipboard-write'], {origin:base});
const external = [], errors = [], failed = [], violations = [];
await context.route('**/*', route => { if (new URL(route.request().url()).origin === base) return route.continue(); external.push(route.request().url()); return route.abort(); });
await context.exposeFunction('articleCsp', value => violations.push(value));
await context.addInitScript(() => document.addEventListener('securitypolicyviolation', event => window.articleCsp(event.violatedDirective)));
const page = await context.newPage();
page.on('pageerror', error => errors.push(error.message));
page.on('response', response => { if (response.status() >= 400 && ['image','stylesheet','script','font'].includes(response.request().resourceType())) failed.push(response.url()); });
await mkdir('results', {recursive:true});
try {
    for (const width of [1440, 1200, 1024, 768, 375, 320]) {
        await page.setViewportSize({width, height:1000});
        for (const path of ['/articles', '/articles/ui-article-1']) {
            assert.equal((await page.goto(base + path)).status(), 200);
            await page.evaluate(() => document.fonts.ready);
            await page.locator('[data-article-image]').evaluateAll(images => images.forEach(image => image.loading = 'eager'));
            await page.waitForFunction(() => [...document.querySelectorAll('[data-article-image]')].every(image => image.complete && image.naturalWidth > 0));
            assert.equal(await page.locator('h1').count(), 1);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${path} at ${width}`);
            if (path === '/articles') {
                const columns = await page.locator('.article-card').evaluateAll(cards => cards.filter(card => Math.abs(card.getBoundingClientRect().top - cards[0].getBoundingClientRect().top) < 2).length);
                assert.equal(columns, width > 1100 ? 4 : width > 575 ? 2 : 1);
            } else {
                const main = await page.locator('.article-main').boundingBox(), sidebar = await page.locator('.articles-sidebar').boundingBox();
                if (width > 991) assert.ok(main.x > sidebar.x, 'article is right of sidebar');
                else assert.ok(sidebar.y >= main.y + main.height, 'sidebar follows article');
                assert.match(await page.locator('.article-meta time').innerText(), /^[۰-۹]{4}\/[۰-۹]{2}\/[۰-۹]{2}$/);
                assert.match(await page.locator('.article-meta').innerText(), /نویسنده ساختگی/);
            }
            if ([1440, 320].includes(width)) await page.screenshot({path:`results/articles-${path === '/articles' ? 'index' : 'detail'}-${width}.png`, fullPage:true});
        }
    }
    await page.goto(base + '/articles');
    await page.evaluate(() => { window.articleDocumentToken = 'same-document'; });
    const documentRequests = [];
    const recordDocument = request => { if (request.isNavigationRequest() && request.frame() === page.mainFrame()) documentRequests.push(request.url()); };
    page.on('request', recordDocument);
    const ready = () => page.waitForFunction(() => document.querySelector('[data-article-results]').getAttribute('aria-busy') === 'false' && document.querySelector('[data-article-status]').textContent.includes('مقاله منتشرشده'));
    const ajax = async action => {
        await Promise.all([page.waitForResponse(response => response.request().resourceType() === 'fetch' && new URL(response.url()).pathname === '/articles'), action()]);
        await ready();
    };
    await ajax(() => page.locator('[name=category]').selectOption('ui-care'));
    await ajax(() => page.locator('[name=sort]').selectOption('oldest'));
    await ajax(() => page.locator('[name=q]').fill('مقاله ساختگی'));
    assert.equal(await page.locator('.article-card').count(), 12);
    await ajax(() => page.locator('.articles-pagination [rel=next]').click());
    assert.equal(await page.locator('.article-card').count(), 3);
    assert.equal(new URL(page.url()).searchParams.get('category'), 'ui-care');
    assert.equal(new URL(page.url()).searchParams.get('sort'), 'oldest');
    await ajax(() => page.goBack());
    assert.equal(await page.locator('.article-card').count(), 12);
    assert.equal(new URL(page.url()).searchParams.has('page'), false);
    await ajax(() => page.goForward());
    assert.equal(await page.locator('.article-card').count(), 3);
    await ajax(() => page.locator('[name=q]').fill('نتیجه ناموجود آزمون'));
    assert.equal(await page.locator('.article-empty').count(), 1);
    await ajax(() => page.locator('.articles-results a').click());
    assert.equal(await page.locator('[name=q]').inputValue(), '');
    assert.equal(await page.locator('[name=category]').inputValue(), '');
    const previousTitles = await page.locator('.article-card h2').allTextContents();
    await context.route('**/articles?**', route => route.request().resourceType() === 'fetch' ? route.fulfill({status:503,body:'unavailable'}) : route.continue());
    await page.locator('[name=q]').fill('مقاله ساختگی 17');
    await page.locator('[data-article-error]').waitFor({state:'visible'});
    assert.deepEqual(await page.locator('.article-card h2').allTextContents(), previousTitles);
    assert.equal(await page.locator('[data-article-results]').getAttribute('aria-busy'), 'false');
    await context.unroute('**/articles?**');
    await ajax(() => page.locator('[data-article-retry]').click());
    assert.equal(await page.locator('.article-card').count(), 1);
    // A superseded request must never replace the newest results.
    let releaseOld;
    const oldGate = new Promise(resolve => { releaseOld = resolve; });
    let finishOld;
    const oldDone = new Promise(resolve => { finishOld = resolve; });
    await context.route('**/articles?**', async route => {
        if (new URL(route.request().url()).searchParams.get('q') !== 'stale') return route.continue();
        await oldGate;
        try { await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({html:'<div class="articles-results"><p>stale</p></div><section class="articles-grid">STALE RESPONSE</section>'})}); } catch { /* The browser may already have cancelled the old request. */ }
        finally { finishOld(); }
    });
    await Promise.all([page.waitForRequest(request => new URL(request.url()).searchParams.get('q') === 'stale'), page.locator('[name=q]').fill('stale')]);
    await ajax(() => page.locator('[name=q]').fill('مقاله ساختگی 16'));
    releaseOld(); await oldDone;
    assert.equal(await page.locator('.article-card').count(), 1);
    assert.match(await page.locator('.article-card h2').innerText(), /16/);
    await context.unroute('**/articles?**');
    const compositionRequests = [];
    const duringComposition = request => { if (request.resourceType() === 'fetch') compositionRequests.push(request.url()); };
    page.on('request', duringComposition);
    await page.locator('[name=q]').evaluate(input => {
        input.dispatchEvent(new CompositionEvent('compositionstart', {bubbles:true}));
        input.value = 'مقاله ساختگی 17';
        input.dispatchEvent(new InputEvent('input', {bubbles:true,isComposing:true}));
    });
    await page.waitForTimeout(400); // Longer than debounce: composing input must not issue a request.
    assert.deepEqual(compositionRequests, []);
    assert.match(await page.locator('.article-card h2').innerText(), /16/);
    page.off('request', duringComposition);
    await ajax(() => page.locator('[name=q]').dispatchEvent('compositionend'));
    assert.match(await page.locator('.article-card h2').innerText(), /17/);
    await ajax(() => page.locator('[name=q]').fill('مقاله '));
    assert.equal(await page.locator('[name=q]').inputValue(), 'مقاله '); // Preserve the space while the user continues typing.
    assert.equal(await page.evaluate(() => window.articleDocumentToken), 'same-document');
    assert.deepEqual(documentRequests, []);
    page.off('request', recordDocument);
    await page.goto(base + '/articles/ui-article-1');
    const shareUrl = await page.locator('[data-copy-article]').getAttribute('data-copy-article');
    assert.equal(shareUrl, base + '/articles/ui-article-1');
    assert.equal(new URL(await page.locator('.article-share a').first().getAttribute('href')).searchParams.get('url'), shareUrl);
    await page.locator('[data-copy-article]').click();
    await page.waitForFunction(() => document.querySelector('[data-share-status]').textContent.includes('کپی شد'));
    assert.equal(await page.evaluate(() => navigator.clipboard.readText()), shareUrl);
    await page.evaluate(() => { navigator.clipboard.writeText = () => Promise.reject(new Error('denied')); });
    await page.locator('[data-copy-article]').click();
    await page.waitForFunction(() => document.querySelector('[data-share-status]').textContent.includes('دسترس نیست'));
    for (const path of ['/articles', '/articles/ui-article-1']) {
        await page.setViewportSize({width:640,height:1000}); await page.goto(base+path);
        await page.evaluate(() => document.documentElement.style.fontSize = '200%');
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, path + ' at 200% text');
    }
    // Broken HTTP responses and invalid image bytes both fall back, without inline JS or retry loops.
    await context.route('**/website-media/*', route => route.fulfill({status:200,contentType:'image/webp',body:'not an image'}));
    for (const path of ['/articles', '/articles/ui-article-1', '/articles/ui-article-2']) {
        await page.goto(base + path);
        await page.locator('[data-article-image]').evaluateAll(images => images.forEach(image => image.loading = 'eager'));
        await page.waitForFunction(() => [...document.querySelectorAll('[data-article-image]')].every(image => image.complete && image.naturalWidth > 0 && image.src.endsWith('/article-default.svg')));
    }
    await context.unroute('**/website-media/*');
    await context.route('**/website-media/*', route => route.fulfill({status:404,body:'missing'}));
    await page.goto(base+'/articles/ui-article-1');
    await page.waitForFunction(() => document.querySelector('.article-featured').src.endsWith('/article-default.svg') && document.querySelector('.article-featured').naturalWidth > 0);
    assert.ok(failed.every(url => url.includes('/website-media/'))); failed.length = 0;
    await context.unroute('**/website-media/*');
    for (const path of ['/', '/about', '/weekly-schedule', '/calendar', '/admin/website-articles']) {
        await page.goto(base + path); assert.equal(await page.locator('link[href$="/articles.css"]').count(), 0);
    }
    const native = await browser.newContext({javaScriptEnabled:false});
    const nativePage = await native.newPage(); await nativePage.goto(base+'/articles?category=ui-care&sort=oldest');
    assert.equal(await nativePage.locator('.article-card').count(), 12);
    await Promise.all([nativePage.waitForNavigation(), nativePage.locator('.articles-pagination [rel=next]').click()]); assert.equal(await nativePage.locator('.article-card').count(), 3);
    await native.close();
    assert.deepEqual(external, []); assert.deepEqual(errors, []); assert.deepEqual(violations, []); assert.deepEqual(failed, []);
    console.log('PASS: responsive 4/2/1 cards; AJAX search/selects/pagination/reset/history without document navigation; retry and stale response protection; IME; 200% text; image fallback; native forms; sharing and isolated CSS/CSP');
} finally { await browser.close(); }
