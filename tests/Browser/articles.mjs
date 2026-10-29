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
    await page.locator('[name=category]').selectOption('ui-care');
    await page.locator('[name=sort]').selectOption('oldest');
    await page.locator('[name=q]').fill('مقاله ساختگی');
    await Promise.all([page.waitForNavigation(), page.locator('.articles-filters button').click()]);
    assert.equal(await page.locator('.article-card').count(), 12);
    await Promise.all([page.waitForNavigation(), page.locator('.articles-pagination [rel=next]').click()]);
    assert.equal(await page.locator('.article-card').count(), 3);
    assert.equal(new URL(page.url()).searchParams.get('category'), 'ui-care');
    assert.equal(new URL(page.url()).searchParams.get('sort'), 'oldest');
    await page.locator('[name=q]').fill('نتیجه ناموجود آزمون');
    await Promise.all([page.waitForNavigation(), page.locator('.articles-filters button').click()]);
    assert.equal(await page.locator('.article-empty').count(), 1);
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
    console.log('PASS: 12 responsive page/viewport combinations; 4/2/1 cards; RTL sidebar order; live filters, pagination, empty state, share URLs, copy feedback, 200% text, corrupt/404 image fallback, native forms and isolated styles/CSP');
} finally { await browser.close(); }
