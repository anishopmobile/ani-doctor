import assert from 'node:assert/strict';
import {mkdir} from 'node:fs/promises';
const {chromium} = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = 'http://127.0.0.1:18558';
const browser = await chromium.launch({headless: true, ...(process.env.CHROME_EXECUTABLE ? {executablePath: process.env.CHROME_EXECUTABLE} : {})});
const context = await browser.newContext();
const external = [], errors = [], failed = [], violations = [];
await context.route('**/*', route => {
    if (new URL(route.request().url()).origin === base) return route.continue();
    external.push(route.request().url());
    return route.abort();
});
await context.exposeFunction('informationCsp', value => violations.push(value));
await context.addInitScript(() => document.addEventListener('securitypolicyviolation', event => window.informationCsp(event.violatedDirective)));
const page = await context.newPage();
page.on('pageerror', error => errors.push(error.message));
context.on('response', response => { if (response.status() >= 400 && ['image','stylesheet','script','font'].includes(response.request().resourceType())) failed.push(response.url()); });
await mkdir('results', {recursive: true});
try {
    for (const width of [1440, 1024, 900, 768, 375, 320]) {
        await page.setViewportSize({width, height: 1000});
        for (const path of ['/about', '/contact', '/services', '/privacy', '/policies']) {
            assert.equal((await page.goto(base + path)).status(), 200);
            await page.evaluate(() => document.fonts.ready);
            assert.equal(await page.locator('.info-pages').count(), 1);
            assert.equal(await page.locator('h1').count(), 1);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${path} at ${width}px`);
            assert.equal(await page.locator('.info-legal-links a').count(), 5);
            if (path === '/privacy' || path === '/policies') {
                const targets = await page.locator('.info-toc a').evaluateAll(links => links.every(link => document.getElementById(link.hash.slice(1))));
                assert.equal(targets, true);
                await page.locator('.info-toc a[href="#topic-1"]').click();
                assert.equal(new URL(page.url()).hash, '#topic-1');
            }
            if (width === 320 || width === 1440) await page.screenshot({path: `results/info-${path.slice(1)}-${width}.png`, fullPage: true});
        }
    }
    await page.goto(base + '/contact');
    assert.equal(await page.locator('.info-contact-details .info-button').evaluate(button => getComputedStyle(button).color), 'rgb(255, 255, 255)');
    assert.equal(await page.locator('a[href="mailto:office.information@example.test"]').count() > 0, true);
    assert.equal(await page.locator('a[href="https://maps.example.test/clinic"]').count() > 0, true);
    assert.equal(await page.locator('[name="fullname"], [name="message"]').count(), 0);
    await page.goto(base + '/services');
    const bookingLink = page.locator('.info-service a[href*="/calendar?service_id="]').first();
    assert.equal(await bookingLink.count(), 1);
    const selected = new URL(await bookingLink.getAttribute('href')).searchParams.get('service_id');
    await bookingLink.click();
    assert.equal(await page.locator('#service').inputValue(), selected);
    for (const path of ['/', '/calendar', '/management/weekly-schedule', '/admin/services']) {
        assert.equal((await page.goto(base + path)).status(), 200);
        assert.equal(await page.locator('link[href$="/information-pages.css"]').count(), 0);
    }
    for (const path of ['/about', '/contact', '/services', '/privacy', '/policies']) {
        await page.setViewportSize({width: 640, height: 1000});
        await page.goto(base + path);
        await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${path} at 200% text`);
    }
    assert.deepEqual(external, []); assert.deepEqual(errors, []); assert.deepEqual(failed, []); assert.deepEqual(violations, []);
    console.log('PASS: 30 page/viewport combinations, five pages at 200% text, published CMS anchors, real service selection, contact links, isolated CSS, JS/assets/CSP/external requests');
} finally { await browser.close(); }
