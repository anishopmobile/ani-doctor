import assert from 'node:assert/strict';
import {mkdir, writeFile} from 'node:fs/promises';
const {chromium} = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = 'http://127.0.0.1:18558';
const browser = await chromium.launch({headless: true, ...(process.env.CHROME_EXECUTABLE ? {executablePath: process.env.CHROME_EXECUTABLE} : {})});
const results = [];
await mkdir('results', {recursive: true});
async function test(name, run) { await run(); results.push({name, passed: true}); console.log(`PASS ${name}`); }
const context = await browser.newContext({viewport: {width: 1440, height: 1000}});
await context.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
const page = await context.newPage();
const errors = []; page.on('pageerror', error => errors.push(error.message));
const violations = [];
await context.exposeFunction('__recordCsp', value => violations.push(value));
await context.addInitScript(() => document.addEventListener('securitypolicyviolation', e => window.__recordCsp(`${e.violatedDirective}: ${e.blockedURI}`)));
const widget = id => page.locator(`#${id}`).locator('..');
try {
    await page.goto(`${base}/__ui-fixture`); await page.locator('#kind[data-hcg-ready]').waitFor({state: 'attached'});
    await test('live search, normal native value and no navigation', async () => {
        await widget('kind').locator('.hcg-select-control').click();
        await widget('kind').locator('.hcg-select-search').fill('مالی');
        await widget('kind').locator('[role=option]:visible').filter({hasText: 'مالی کل مطب'}).click();
        assert.equal(await page.locator('#kind').inputValue(), 'finance'); assert.equal(page.url(), `${base}/__ui-fixture`);
    });
    await test('escaped labels, no DOM injection and empty search state', async () => {
        await widget('kind').locator('.hcg-select-control').click();
        await widget('kind').locator('.hcg-select-search').fill('onerror');
        assert.equal(await widget('kind').locator('img').count(), 0); assert.equal(await page.evaluate(() => window.__unsafe), undefined);
        await widget('kind').locator('.hcg-select-search').fill('بدون نتیجه');
        assert.equal(await widget('kind').locator('.hcg-select-empty').isVisible(), true);
        await page.keyboard.press('Escape');
    });
    await test('keyboard selection and native programmatic synchronization', async () => {
        await widget('kind').locator('.hcg-select-control').focus(); await page.keyboard.press('Enter');
        await widget('kind').locator('.hcg-select-search').fill('نوبت'); await page.keyboard.press('ArrowDown'); await page.keyboard.press('Enter');
        assert.equal(await page.locator('#kind').inputValue(), 'appointments');
        await page.locator('#kind').evaluate(el => { el.value = 'finance'; });
        assert.match(await widget('kind').locator('.hcg-select-label').innerText(), /مالی/);
    });
    await test('required select blocks submission and exposes accessible error', async () => {
        await page.locator('#test-form button[type=submit]').click();
        assert.equal(page.url(), `${base}/__ui-fixture`);
        assert.equal(await widget('required-choice').locator('.hcg-select-control').getAttribute('aria-invalid'), 'true');
        await page.keyboard.press('Escape');
        await widget('required-choice').locator('.hcg-select-control').click();
        await widget('required-choice').locator('[role=option]').filter({hasText: 'گزینه معتبر'}).click();
    });
    await test('multiple selection filtering retains regular array values', async () => {
        const wrap = page.locator('#multiple').locator('..');
        await wrap.locator('input[type=search]').fill('دوم');
        await page.locator('#multiple').selectOption(['a', 'b']);
        assert.deepEqual(await page.locator('#test-form').evaluate(form => new FormData(form).getAll('services[]')), ['a', 'b']);
        await wrap.locator('input[type=search]').fill('');
    });
    await test('disabled fieldsets remain disabled and recover when enabled', async () => {
        assert.equal(await widget('conditional').locator('.hcg-select-control').getAttribute('aria-disabled'), 'true');
        assert.equal(await page.locator('#conditional-date').locator('..').locator('.ui-date-button').isDisabled(), true);
        await page.locator('#disabled-group').evaluate(el => { el.disabled = false; });
        await page.waitForFunction(() => document.querySelector('#conditional')._hcgApi.element.querySelector('.hcg-select-control').getAttribute('aria-disabled') === 'false');
        assert.equal(await page.locator('#conditional-date').locator('..').locator('.ui-date-button').isEnabled(), true);
    });
    await test('Persian picker preserves initial value and submits canonical Jalali date', async () => {
        const dateWrap = page.locator('#date').locator('..');
        await dateWrap.locator('.ui-date-button').click(); assert.equal(await page.locator('#date').inputValue(), '1405-08-03');
        await dateWrap.locator('.pardis-day[data-day="12"]').click();
        assert.equal(await page.locator('#date').inputValue(), '1405-08-12');
        assert.equal(await dateWrap.locator('.ui-date-panel').isVisible(), false);
    });
    await test('dynamic selects, changed options and disposal', async () => {
        await page.evaluate(() => { const label = document.createElement('label'); label.textContent = 'پویا'; const select = document.createElement('select'); select.id = 'dynamic'; select.append(new Option('اول', 'a'), new Option('دوم', 'b')); label.append(select); document.querySelector('#test-form').append(label); });
        await page.locator('#dynamic[data-hcg-ready]').waitFor({state: 'attached'});
        await page.locator('#dynamic').evaluate(el => { el.replaceChildren(new Option('جدید', 'new')); el.value = 'new'; });
        await page.waitForFunction(() => document.querySelector('#dynamic')._hcgApi.element.querySelector('.hcg-select-control').textContent.includes('جدید'));
        await page.locator('#dynamic').evaluate(el => el.closest('.ui-field').remove());
    });
    await test('native form payload after enhancement', async () => {
        await page.locator('#test-form button[type=submit]').click(); await page.waitForURL(`${base}/__ui-submit`);
        const body = JSON.parse(await page.locator('body').innerText()); assert.equal(body.kind, 'finance'); assert.deepEqual(body.services, ['a', 'b']); assert.equal(body.date, '1405-08-12');
    });
    await test('real page builder and clinical form builder dynamic controls', async () => {
        await page.goto(`${base}/admin/website-pages/1/edit`); await page.locator('[data-block-add]').click();
        await page.waitForFunction(() => document.querySelectorAll('[data-builder-editor] select[data-hcg-ready]').length >= 4);
        await page.screenshot({path: 'results/admin-page-builder.png', fullPage: true});
        await page.goto(`${base}/admin/forms/new`); await page.locator('[data-add-field]').click();
        await page.waitForFunction(() => document.querySelector('[data-fields] select[data-hcg-ready]'));
    });
    await test('RTL desktop, mobile 320px, menu and no page overflow', async () => {
        await page.goto(`${base}/__ui-fixture`); await page.screenshot({path: 'results/panel-desktop.png', fullPage: true});
        assert.equal(await page.locator('html').getAttribute('dir'), 'rtl');
        await page.setViewportSize({width: 320, height: 820});
        await page.locator('[data-menu-toggle]').click(); assert.equal(await page.locator('#panel-navigation').isVisible(), true);
        await page.keyboard.press('Escape'); assert.equal(await page.locator('#panel-navigation').isVisible(), false);
        await page.locator('#date').locator('..').locator('.ui-date-button').click();
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
        await page.screenshot({path: 'results/panel-mobile.png', fullPage: true}); await page.keyboard.press('Escape');
    });
    await test('patient authorization still denies admin routes', async () => {
        await page.setExtraHTTPHeaders({'X-UI-Actor': 'patient'}); const response = await page.goto(`${base}/admin/website-pages`); assert.equal(response.status(), 403);
    });
    await test('patient calendar keeps inline availability and searchable service integration', async () => {
        await page.setViewportSize({width: 390, height: 844});
        const available = page.waitForResponse(response => response.url().includes('/api/v1/calendar?') && response.status() === 200);
        await page.goto(`${base}/calendar`); await available;
        await page.locator('#service[data-hcg-ready]').waitFor({state: 'attached'});
        assert.equal(await page.locator('#inline-calendar .pardis-calendar').isVisible(), true);
        await page.waitForFunction(() => document.querySelector('#inline-calendar').getAttribute('aria-busy') === 'false');
        assert.equal(await page.locator('[data-booking-submit]').isDisabled(), true);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
        await page.screenshot({path: 'results/patient-calendar-mobile.png', fullPage: true});
    });
    await test('JavaScript-off fallback retains native fields and submission', async () => {
        const fallback = await browser.newContext({javaScriptEnabled: false}); const native = await fallback.newPage();
        await native.goto(`${base}/__ui-fixture`); assert.equal(await native.locator('#kind').isVisible(), true);
        await native.locator('#kind').selectOption('finance'); await native.locator('#required-choice').selectOption('yes');
        await native.locator('#test-form button[type=submit]').click(); await native.waitForURL(`${base}/__ui-submit`);
        assert.equal(JSON.parse(await native.locator('body').innerText()).kind, 'finance'); await fallback.close();
    });
    assert.deepEqual(errors, []);
    assert.deepEqual(violations, []);
    await writeFile('results/summary.json', JSON.stringify({passed: true, checks: results, javascriptErrors: errors, cspViolations: violations}, null, 2));
} catch (error) {
    await page.screenshot({path: 'results/failure.png', fullPage: true}).catch(() => {});
    await writeFile('results/summary.json', JSON.stringify({passed: false, checks: results, error: String(error), javascriptErrors: errors}, null, 2));
    throw error;
} finally { await browser.close(); }
