import assert from 'node:assert/strict';
import {mkdir} from 'node:fs/promises';
const {chromium} = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = 'http://127.0.0.1:18558';
const browser = await chromium.launch({headless: true, ...(process.env.CHROME_EXECUTABLE ? {executablePath: process.env.CHROME_EXECUTABLE} : {})});
const context = await browser.newContext();
const externalRequests = [];
await context.route('**/*', route => {
    if (new URL(route.request().url()).origin === base) return route.continue();
    externalRequests.push(route.request().url());
    return route.abort();
});
const page = await context.newPage();
const errors = [];
const csp = [];
const failedAssets = [];
await context.exposeFunction('recordWeeklyCsp', value => csp.push(value));
await context.addInitScript(() => document.addEventListener('securitypolicyviolation', e => window.recordWeeklyCsp(e.violatedDirective)));
page.on('pageerror', error => errors.push(error.message));
context.on('response', response => {
    if (response.status() >= 400 && ['stylesheet', 'script', 'font', 'image'].includes(response.request().resourceType())) failedAssets.push(response.url());
});
await mkdir('results', {recursive: true});
try {
    for (const width of [1440, 1024, 768, 640, 375, 320]) {
        await page.setViewportSize({width, height: 1000});
        for (const path of ['/weekly-schedule', '/management/weekly-schedule']) {
            const response = await page.goto(base + path);
            assert.equal(response.status(), 200);
            await page.evaluate(() => document.fonts.ready);
            const publicPage = path === '/weekly-schedule';
            assert.equal(await page.locator(publicPage ? '.weekly-poster__row' : '.weekly-day').count(), 7);
            if (publicPage) {
                const dates = await page.locator('.weekly-poster__day').evaluateAll(days => days.map(day => {
                    const name = day.querySelector('h2'), date = day.querySelector('time');
                    return {text: date.textContent, iso: date.dateTime, below: date.getBoundingClientRect().top >= name.getBoundingClientRect().bottom};
                }));
                assert.equal(dates.length, 7);
                for (const date of dates) { assert.match(date.text, /^1[34]\d{2}-\d{2}-\d{2}$/); assert.match(date.iso, /^\d{4}-\d{2}-\d{2}$/); assert.equal(date.below, true); }
                assert.equal(await page.locator('link[href$="/weekly-schedule.css"]').count(), 0);
            } else {
                assert.equal(await page.locator('link[href$="/weekly-poster.css"]').count(), 0);
            }
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${path} at ${width}px`);
            if (path.includes('/management/')) {
                await page.locator('.weekly-day details[data-record-panel]').first().locator('summary').first().click();
                const form = page.locator('[data-weekly-editor]').first();
                await form.locator('[name="mode"]').selectOption('manual');
                assert.equal(await form.locator('[name="shifts[0][start]"]').isEnabled(), true);
                await form.locator('[name="status"]').selectOption('closed');
                assert.equal(await form.locator('[name="shifts[0][start]"]').isDisabled(), true);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
            }
            await page.screenshot({path: `results/weekly-${path.includes('/management/') ? 'admin' : 'public'}-${width}.png`, fullPage: true});
        }
    }
    await page.goto(base + '/weekly-schedule');
    await page.setViewportSize({width: 640, height: 1000});
    await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'weekly display at 200% text size');
    await page.setViewportSize({width: 1440, height: 1000});
    await page.goto(base + '/management/weekly-schedule');
    await page.locator('.weekly-day details[data-record-panel]').first().locator('summary').first().click();
    let form = page.locator('[data-weekly-editor]').first();
    await form.locator('[name="mode"]').selectOption('manual');
    await form.locator('[name="status"]').selectOption('working');
    await form.locator('[name="shifts[0][start]"]').fill('19:00');
    await form.locator('[name="shifts[0][end]"]').fill('18:00');
    await form.locator('button').click();
    await page.locator('[data-errors]').waitFor();
    form = page.locator('[data-weekly-editor]').first();
    assert.equal(await form.locator('[name="shifts[0][start]"]').inputValue(), '19:00');
    assert.equal(await form.locator('[name="status"]').isVisible(), true);
    await form.locator('[name="shifts[0][end]"]').fill('20:00');
    await form.locator('[name="location"]').fill('مطب آزمایشی');
    await form.locator('button').click();
    await page.locator('.alert-success').waitFor();
    await page.goto(base + '/weekly-schedule');
    assert.equal(await page.getByText('مطب آزمایشی', {exact: true}).count(), 1);
    assert.equal(await page.locator('[aria-current="date"]').count(), 1);
    await page.setViewportSize({width: 320, height: 820});
    await page.locator('[data-public-menu]').click();
    assert.equal(await page.locator('#public-navigation a[href$="/weekly-schedule"]').isVisible(), true);
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('#public-navigation').isVisible(), false);
    await context.setExtraHTTPHeaders({'X-UI-Actor': 'patient'});
    assert.equal((await page.goto(base + '/management/weekly-schedule')).status(), 403);
    await context.setExtraHTTPHeaders({'X-UI-Actor': 'secretary'});
    assert.equal((await page.goto(base + '/management/weekly-schedule')).status(), 200);
    assert.deepEqual(errors, []);
    assert.deepEqual(csp, []);
    assert.deepEqual(failedAssets, []);
    assert.deepEqual(externalRequests, []);
    console.log('PASS: 12 responsive routes, Jalali dates below weekdays, isolated public CSS, 200% text size, daily editor states, validation and save, public location, today, mobile menu, permissions, JS/assets/CSP');
} finally {
    await browser.close();
}
