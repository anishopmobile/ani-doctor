import assert from 'node:assert/strict';
import {mkdir} from 'node:fs/promises';
const {chromium} = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = 'http://127.0.0.1:18558';
const browser = await chromium.launch({headless:true,...(process.env.CHROME_EXECUTABLE ? {executablePath:process.env.CHROME_EXECUTABLE} : {})});
const context = await browser.newContext();
const errors = [], external = [], failed = [], violations = [];
await context.route('**/*', route => { if (new URL(route.request().url()).origin === base) return route.continue(); external.push(route.request().url()); return route.abort(); });
await context.exposeFunction('footerCsp', value => violations.push(value));
await context.addInitScript(() => document.addEventListener('securitypolicyviolation', event => window.footerCsp(event.violatedDirective)));
const page = await context.newPage();
page.on('pageerror', error => errors.push(error.message));
page.on('response', response => { if (response.status() >= 400 && ['image','stylesheet','script','font'].includes(response.request().resourceType())) failed.push(response.url()); });
await mkdir('results', {recursive:true});
try {
    const routes = ['/', '/about', '/contact', '/services', '/articles', '/articles/ui-demo-article', '/privacy', '/policies', '/pages/panel-demo'];
    for (const width of [1440,1200,1024,768,375,320]) {
        await page.setViewportSize({width,height:1000});
        for (const path of routes) {
            assert.equal((await page.goto(base+path)).status(),200);
            await page.evaluate(() => document.fonts.ready);
            assert.equal(await page.locator('footer.ani-footer').count(),1);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),true, `${path} at ${width}`);
            const columns = await page.locator('.af-col').evaluateAll(items => items.filter(item => Math.abs(item.getBoundingClientRect().top-items[0].getBoundingClientRect().top) < 2).length);
            assert.equal(columns, width > 1100 ? 4 : width > 650 ? 2 : 1);
        }
        await page.goto(base+'/about');
        await page.locator('.ani-footer').screenshot({path:`results/footer-${width}.png`});
    }
    assert.equal(await page.locator('.af-social a[aria-label="شبکه ساختگی آزمون"]').getAttribute('href'),'https://instagram.com/example');
    assert.equal(await page.locator('.af-contact a[href="mailto:footer@example.test"]').count(),1);
    assert.equal(await page.locator('.af-contact a[href="tel:02112345678"]').count(),1);
    assert.match(await page.locator('.af-copyright').innerText(), /[۰-۹]{4}/);
    const service = page.locator('.af-services a[href*="/services/"]').first();
    await Promise.all([page.waitForNavigation(),service.click()]);
    assert.equal((await page.locator('main').innerText()).includes('خدمت ساختگی آزمون رابط'),true);
    await page.locator('.af-back-top').click();
    await page.waitForFunction(() => window.scrollY < 5);
    assert.equal(new URL(page.url()).hash,'#public-site-top');
    for (const width of [320,640]) {
        await page.setViewportSize({width,height:1000}); await page.goto(base+'/about');
        await page.evaluate(() => document.documentElement.style.fontSize = '200%');
        assert.equal(await page.locator('.ani-footer').evaluate(footer => footer.scrollWidth <= footer.clientWidth && footer.getBoundingClientRect().left >= 0 && footer.getBoundingClientRect().right <= innerWidth),true, `footer at 200%/${width}`);
        if (width === 640) assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),true,'whole page at 200%/640');
    }
    // Repeater capacity and real settings update use the existing authorized administration.
    await page.goto(base+'/admin/website-theme');
    const group = page.locator('[data-settings-repeater="footer_features"]');
    await group.locator('summary').click();
    assert.equal(await group.locator('[data-settings-row]').count(),3);
    assert.equal(await group.locator('[data-settings-add]').isDisabled(),true);
    await group.locator('[data-setting-key=title]').first().fill('ویژگی ذخیره‌شده آزمون فوتر');
    await Promise.all([page.waitForNavigation(),page.locator('[data-theme-settings] button[type=submit]').first().click()]);
    await page.goto(base+'/about');
    assert.ok((await page.locator('.af-feature').first().innerText()).includes('ویژگی ذخیره‌شده آزمون فوتر'));
    for (const path of ['/calendar','/admin/website-theme','/admin/website-links']) {
        await page.goto(base+path); assert.equal(await page.locator('link[href$="/footer.css"]').count(),0);
    }
    assert.deepEqual(errors,[]); assert.deepEqual(external,[]); assert.deepEqual(failed,[]); assert.deepEqual(violations,[]);
    console.log('PASS: 54 public route/viewport combinations; 4/2/1 footer columns; real social/contact/service links; Jalali year; native back-to-top; 200% at 320/640; admin footer editing and three-item limit; CSS/JS/CSP isolation');
} finally { await browser.close(); }
