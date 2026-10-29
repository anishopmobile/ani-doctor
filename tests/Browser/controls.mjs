import assert from 'node:assert/strict';
import {mkdir, writeFile} from 'node:fs/promises';
const {chromium} = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = 'http://127.0.0.1:18558';
const browser = await chromium.launch({headless: true, ...(process.env.CHROME_EXECUTABLE ? {executablePath: process.env.CHROME_EXECUTABLE} : {})});
const results = [];
await mkdir('results', {recursive: true});
async function test(name, run) { await run(); results.push({name, passed: true}); console.log(`PASS ${name}`); }
const context = await browser.newContext({viewport: {width: 1440, height: 1000}});
const externalRequests = [];
await context.route('**/*', route => { if (new URL(route.request().url()).origin === base) return route.continue(); externalRequests.push(route.request().url()); return route.abort(); });
const page = await context.newPage();
const errors = []; page.on('pageerror', error => errors.push(error.message));
context.on('page', child => { if (child !== page) child.on('pageerror', error => errors.push(error.message)); });
const failedAssets = [];
context.on('response', response => { if (response.status() >= 400 && ['stylesheet', 'script', 'font', 'image'].includes(response.request().resourceType())) failedAssets.push({url: response.url(), status: response.status()}); });
const violations = [];
const routeChecks = [];
await context.exposeFunction('__recordCsp', value => violations.push(value));
await context.addInitScript(() => document.addEventListener('securitypolicyviolation', e => window.__recordCsp(`${e.violatedDirective}: ${e.blockedURI}`)));
const widget = id => page.locator(`#${id}`).locator('..');
try {
    await test('public studio design uses local assets and fits desktop, tablet and small mobile', async () => {
        for (const width of [1440, 768, 320]) {
            await page.setViewportSize({width, height: 1000});
            for (const path of ['/', '/services', '/articles']) {
                const response = await page.goto(`${base}${path}`); assert.equal(response.status(), 200);
                await page.evaluate(() => document.fonts.ready);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${path} at ${width}px`);
            }
            await page.goto(base); await page.screenshot({path: `results/website-${width}.png`, fullPage: true});
            if (width === 1440) await page.screenshot({path: 'results/website-cover.png'});
        }
        await page.setViewportSize({width: 1440, height: 1000});
    });
    await test('real administrative desk shows data-backed overview and responsive shortcuts', async () => {
        await page.goto(`${base}/admin`); await page.locator('.desk-summary').waitFor();
        assert.equal(await page.locator('.workspace-actions a').count(), 3);
        await page.screenshot({path: 'results/admin-overview.png', fullPage: true});
        await page.screenshot({path: 'results/admin-cover.png'});
        await page.setViewportSize({width: 320, height: 820});
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
        await page.screenshot({path: 'results/admin-overview-mobile.png', fullPage: true});
        await page.setViewportSize({width: 1440, height: 1000});
    });
    await test('public mobile navigation opens, closes with Escape and keeps real booking links', async () => {
        await page.setViewportSize({width: 320, height: 820}); await page.goto(base);
        await page.locator('[data-public-menu]').click(); assert.equal(await page.locator('#public-navigation').isVisible(), true);
        await page.keyboard.press('Escape'); assert.equal(await page.locator('#public-navigation').isVisible(), false);
        assert.equal(await page.locator('.header-actions a').getAttribute('href'), `${base}/calendar`);
        await page.setViewportSize({width: 1440, height: 1000});
    });
    await test('all interactive route families fit desktop, tablet and mobile with actual synthetic records', async () => {
        const reference = '11111111-1111-4111-8111-111111111111';
        const routes = ['/about', '/contact', '/services/1', '/articles/ui-demo-article', '/pages/panel-demo', '/admin', '/management/desk', '/management/patients', '/management/patients/1', '/management/bookings/new', '/admin/services', '/management/schedule', '/management/reports', '/admin/finance', '/admin/discounts', '/admin/booking-settings', '/admin/forms', '/admin/forms/new', '/admin/forms/1', '/admin/notifications', '/admin/website-theme', '/admin/website-pages', '/admin/website-pages/1/edit', '/admin/website-sections', '/admin/website-links', '/admin/website-articles', '/admin/website-taxonomies', '/admin/website-service-content/1', '/appointments', `/appointments/${reference}`, `/private-forms/${reference}`, `/private-forms/${reference}/1`, '/wallet'];
        for (const width of [1440, 768, 320]) {
            await page.setViewportSize({width, height: 1000});
            for (const path of routes) {
                const response = await page.goto(`${base}${path}`); assert.equal(response.status(), 200, path);
                await page.evaluate(() => document.fonts.ready);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${path} at ${width}px`);
                routeChecks.push({path, width, passed: true});
            }
        }
        await page.setViewportSize({width: 1440, height: 1000});
        await page.goto(`${base}/admin/website-theme`); await page.screenshot({path: 'results/unified-settings.png', fullPage: true});
    });
    await test('administrative records filter locally and invalid edits reopen only the matching form', async () => {
        await page.goto(`${base}/admin/services`);
        const records = page.locator('[data-record-panel][data-record-create=false]');
        assert.ok(await records.count() >= 2);
        await page.locator('[data-record-filter]').fill('نام ناموجود');
        assert.equal(await page.locator('[data-record-panel][data-record-create=false]:visible').count(), 0);
        await page.locator('[data-record-clear]').click();
        const second = records.filter({hasText:'خدمت دوم ساختگی آزمون مدیریت'});
        const id = await second.getAttribute('id');
        await second.locator('summary').click();
        const form = second.locator('form').first();
        const originalName = await form.locator('[name=name]').inputValue();
        const originalPrice = await form.locator('[name=price_irr]').inputValue();
        await form.locator('[name=name]').fill('ویرایش دوم نامعتبر ساختگی');
        await form.locator('[name=price_irr]').fill('invalid');
        await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),form.locator('button').filter({hasText:'ذخیره تغییرات'}).click()]);
        const reopened = page.locator(`#${id}`);
        assert.equal(await reopened.evaluate(el => el.open), true);
        assert.equal(await reopened.locator('[name=price_irr][aria-invalid=true]').count(), 1);
        assert.equal(await page.locator('[data-record-panel] [aria-invalid=true]').count(), 1);
        assert.equal(await reopened.locator('[name=name]').inputValue(),'ویرایش دوم نامعتبر ساختگی');
        assert.equal(await page.locator('[data-record-panel][open]').count(),1);
        await page.locator('[data-errors] a').first().click();
        assert.equal(await reopened.locator('[name=price_irr]').evaluate(el => el===document.activeElement),true);
        await reopened.locator('[name=name]').fill(originalName); await reopened.locator('[name=price_irr]').fill(originalPrice);
        await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),reopened.locator('form').first().locator('button').filter({hasText:'ذخیره تغییرات'}).click()]);
        await page.screenshot({path:'results/admin-services-workflow.png',fullPage:true});
    });
    await test('CMS records keep publication visible and keyboard editors and create actions remain usable', async () => {
        await page.goto(`${base}/admin/website-articles`);
        const record = page.locator('[data-record-panel][data-record-create=false]').first();
        await record.locator('summary').focus(); await page.keyboard.press('Enter');
        assert.equal(await record.evaluate(el => el.open),true);
        assert.equal(await record.locator('textarea[name=body]').isVisible(),true);
        assert.equal(await record.locator('select[name=is_published]').inputValue(),'1');
        await page.evaluate(() => {
            document.addEventListener('click', event => {
                if (event.target.closest('[data-open-record="article-create"]')) {
                    window.articleFocusDuringActivation = document.activeElement === document.querySelector('#article-create [name=title]');
                }
            }, {once: true});
        });
        await page.locator('[data-open-record="article-create"]').click();
        assert.equal(await page.evaluate(() => window.articleFocusDuringActivation), true);
        assert.equal(await page.locator('#article-create').evaluate(el => el.open),true);
        assert.equal(await page.locator('#article-create [name=title]').evaluate(el => el===document.activeElement),true);
        await page.setViewportSize({width:320,height:844}); await page.goto(`${base}/admin/website-articles`);
        assert.equal(await page.locator('[data-site-map]').evaluate(el => el.open),false);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),true);
        await page.screenshot({path:'results/cms-articles-mobile.png',fullPage:true});
        await page.setViewportSize({width:1440,height:1000});
    });
    await test('secretary schedule distinguishes weekly hours from date exceptions and preserves hidden hours', async () => {
        await page.setExtraHTTPHeaders({'X-UI-Actor':'secretary'}); await page.goto(`${base}/management/schedule`);
        const day = page.locator('#weekday-1'); await day.locator('summary').click();
        const time = day.locator('[name="shifts[0][start]"]'); const original = await time.inputValue();
        const closed = day.locator('input[type=checkbox][name=is_closed]'); await closed.check();
        assert.equal(await day.locator('[data-schedule-shifts]').isVisible(),false);
        await closed.uncheck(); assert.equal(await time.inputValue(),original);
        await page.locator('[data-open-record="exception-create"]').click();
        assert.equal(await page.locator('#exception-create [data-schedule-shifts]').isVisible(),false);
        await page.screenshot({path:'results/secretary-schedule.png',fullPage:true});
        await day.locator('form').evaluate(form => form.reset());
        await page.setExtraHTTPHeaders({'X-UI-Actor':'admin'});
    });
    await test('finance report hides and excludes appointment filters without losing their unsent values', async () => {
        await page.goto(`${base}/management/reports`);
        const form = page.locator('[data-report-form]');
        const kind = form.locator('[name=kind]');
        await kind.locator('..').locator('.hcg-select-control').click();
        await kind.locator('..').locator('[role=option]').filter({hasText:'مالی کل مطب'}).click();
        assert.equal(await form.locator('[data-appointment-filters]').isVisible(),false);
        const payload = await form.evaluate(el => Object.fromEntries(new FormData(el)));
        assert.equal(payload.kind,'finance'); assert.equal('service_id' in payload,false); assert.equal('status' in payload,false);
        await kind.locator('..').locator('.hcg-select-control').click();
        await kind.locator('..').locator('[role=option]').filter({hasText:'نوبت‌ها'}).click();
        assert.equal(await form.locator('[data-appointment-filters]').isVisible(),true);
        await page.screenshot({path:'results/reports-workflow.png',fullPage:true});
    });
    await test('settings changes can be previewed privately, saved and restored without a false save', async () => {
        await page.goto(`${base}/admin/website-theme`);
        const original = await page.locator('[name=hero_title]').inputValue();
        await page.locator('[name=hero_title]').evaluate(el => { el.closest('details').open = true; });
        await page.locator('[name=hero_title]').fill('عنوان ساختگی پیش‌نمایش رابط');
        assert.match(await page.locator('[data-unsaved-status]').innerText(), /ذخیره‌نشده/);
        const popupPromise = context.waitForEvent('page');
        await page.locator('button[formtarget="_blank"]').click(); const preview = await popupPromise;
        await preview.waitForLoadState(); assert.match(await preview.locator('h1').innerText(), /عنوان ساختگی/); await preview.close();
        assert.match(await page.locator('[data-unsaved-status]').innerText(), /ذخیره‌نشده/);
        await Promise.all([page.waitForNavigation({waitUntil: 'domcontentloaded'}), page.locator('[data-theme-settings] button[type=submit]').first().click()]);
        await page.locator('[data-theme-settings]').waitFor();
        assert.equal(await page.locator('[name=hero_title]').inputValue(), 'عنوان ساختگی پیش‌نمایش رابط');
        await page.goto(base); assert.match(await page.locator('h1').innerText(), /عنوان ساختگی/);
        await page.goto(`${base}/admin/website-theme`); await page.locator('[name=hero_title]').evaluate(el => { el.closest('details').open = true; });
        await page.locator('[name=hero_title]').fill(original); await Promise.all([page.waitForNavigation({waitUntil: 'domcontentloaded'}), page.locator('[data-theme-settings] button[type=submit]').first().click()]); await page.locator('[data-theme-settings]').waitFor();
    });
    await test('inline server errors attach to real fields and rejected changes do not save', async () => {
        await page.goto(`${base}/admin/website-theme`);
        const original = await page.locator('[name=contact_phone]').inputValue();
        await page.locator('[name=contact_phone]').fill('invalid-phone');
        await Promise.all([page.waitForNavigation({waitUntil: 'domcontentloaded'}), page.locator('[data-theme-settings] button[type=submit]').first().click()]);
        await page.locator('[name=contact_phone][aria-invalid=true]').waitFor({state: 'attached'});
        assert.equal(await page.locator('[name=contact_phone]').getAttribute('aria-describedby') !== null, true);
        await page.goto(`${base}/admin/website-theme`); assert.equal(await page.locator('[name=contact_phone]').inputValue(), original);
    });
    await test('settings ordering, repeater removal, palette selection and account menu stay usable', async () => {
        await page.goto(`${base}/admin/website-theme`);
        await page.locator('#home-order > summary').click();
        const order = await page.locator('[name="section_order[]"]').evaluateAll(inputs => inputs.map(input => input.value));
        await page.locator('[data-order-row]').first().locator('[data-order-move=down]').click();
        await page.locator('[data-order-row]').nth(1).locator('[data-order-move=up]').click();
        assert.deepEqual(await page.locator('[name="section_order[]"]').evaluateAll(inputs => inputs.map(input => input.value)), order);
        const faq = page.locator('[data-settings-repeater=faq_items]'); await faq.locator('summary').click();
        const count = await faq.locator('[data-settings-row]').count(); await faq.locator('[data-settings-add]').click();
        assert.equal(await faq.locator('[data-settings-row]').count(), count + 1);
        await faq.locator('[data-settings-remove]').last().click(); assert.equal(await faq.locator('[data-settings-row]').count(), count);
        await page.locator('#palette > summary').click(); await page.locator('[data-palette-preset]').click();
        assert.equal(await page.locator('[name=primary_color]').inputValue(), '#087b80');
        await page.locator('[data-theme-settings]').evaluate(form => form.reset());
        assert.equal(await page.locator('[data-unsaved-status]').innerText(), '');
        await page.setViewportSize({width: 320, height: 820}); await page.locator('.account-menu summary').click();
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
        await page.keyboard.press('Escape'); assert.equal(await page.locator('.account-menu').getAttribute('open'), null);
        await page.setViewportSize({width: 1440, height: 1000});
    });
    await test('unsaved settings prompt before leaving and preserve the edited input when cancelled', async () => {
        await page.goto(`${base}/admin/website-theme`); await page.locator('[name=brand_name]').click(); await page.locator('[name=brand_name]').fill('ویرایش ذخیره‌نشده ساختگی');
        const dialogPromise = page.waitForEvent('dialog'); const navigation = page.goto(base).catch(error => error);
        const dialog = await dialogPromise; assert.equal(dialog.type(), 'beforeunload'); await dialog.dismiss(); await navigation;
        assert.equal(page.url(), `${base}/admin/website-theme`); assert.equal(await page.locator('[name=brand_name]').inputValue(), 'ویرایش ذخیره‌نشده ساختگی');
        await page.locator('[data-theme-settings]').evaluate(form => form.reset()); await page.goto(base);
    });
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
    await test('guided patient booking selects an existing person, rejects stale time and creates then cancels a real hold', async () => {
        await page.setExtraHTTPHeaders({'X-UI-Actor': 'patient'});
        await page.setViewportSize({width: 1440, height: 1000});
        await page.goto(`${base}/calendar`);
        await page.waitForFunction(() => document.querySelector('#inline-calendar').getAttribute('aria-busy') === 'false');
        if (await page.locator('.pardis-day.day-available:not([disabled])').count() < 4) {
            const nextMonth = page.waitForResponse(response => response.url().includes('/api/v1/calendar?') && response.status() === 200);
            await page.locator('#inline-calendar [data-action="nextMonth"]').click(); await nextMonth;
            await page.waitForFunction(() => document.querySelector('#inline-calendar').getAttribute('aria-busy') === 'false');
        }
        assert.ok(await page.locator('.pardis-day.day-available:not([disabled])').count() >= 4);
        await page.locator('[data-patient-open]').click();
        assert.equal(await page.locator('[data-patient-dialog]').evaluate(el => el.open), true);
        await page.locator('[data-patient-filter]').fill('UI-SYNTHETIC-01');
        await page.locator('[data-patient-options] button').click();
        assert.equal(await page.locator('[data-new-patient]').isVisible(), false);
        assert.equal(await page.locator('[data-new-patient]').evaluate(el => el.disabled), true);
        assert.equal(await page.locator('[data-selected-patient]').innerText().then(t => t.includes('09000000552')), true);
        assert.equal(await page.locator('[data-patient-open]').evaluate(el => el === document.activeElement), true);
        await page.locator('[data-patient-open]').click(); await page.keyboard.press('Escape');
        assert.equal(await page.locator('[data-patient-dialog]').evaluate(el => el.open), false);
        await page.locator('[data-patient-open]').click(); await page.locator('[data-patient-new]').click();
        assert.equal(await page.locator('[data-patient-name]').isVisible(), true);
        await page.locator('[data-patient-open]').click(); await page.locator('[data-patient-options] button').click();
        await page.locator('.pardis-day.day-available:not([disabled])').nth(2).click();
        await page.locator('#slots button:not([disabled])').first().click();
        assert.equal(await page.locator('[data-booking-submit]').isEnabled(), true);
        assert.equal(await page.locator('[data-summary-continue]').isVisible(), true);
        await page.route('**/api/v1/availability?**', route => route.fulfill({status:503, contentType:'application/json', body:JSON.stringify({success:false})}));
        await page.locator('.pardis-day.day-available:not([disabled])').nth(3).click();
        await page.locator('#calendar-retry').waitFor({state:'visible'});
        assert.equal(await page.locator('[data-booking-submit]').isDisabled(), true);
        assert.equal(await page.locator('[data-booking-start]').inputValue(), '');
        await page.unroute('**/api/v1/availability?**'); await page.locator('#calendar-retry').click();
        await page.locator('#slots button:not([disabled])').first().click();
        const payload = await page.locator('[data-booking-form]').evaluate(el => Object.fromEntries(new FormData(el)));
        assert.ok(payload.patient_id); assert.equal('patient_name' in payload, false); assert.equal('contact_mobile' in payload, false);
        await page.screenshot({path:'results/guided-booking-desktop.png', fullPage:true});
        await page.locator('[data-booking-submit]').click(); await page.waitForURL(/\/appointments\/[a-f0-9-]+$/);
        assert.ok((await page.locator('.ux-next-step').first().innerText()).includes('گام بعدی: پرداخت'));
        const columns = await page.locator('.appointment-workspace').evaluate(el => getComputedStyle(el).gridTemplateColumns.split(' ').length);
        assert.equal(columns, 2);
        await page.screenshot({path:'results/appointment-desktop.png',fullPage:true});
        await page.setViewportSize({width:320,height:844});
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
        await page.screenshot({path:'results/appointment-mobile.png',fullPage:true});
        const cancel = page.locator('form[action$="/cancel"]');
        await cancel.locator('..').locator('summary').click();
        await cancel.locator('[name="reason"]').fill('لغو رزرو ساختگی آزمون رابط');
        await cancel.locator('button[type="submit"]').click();
        await page.locator('[data-confirm-choice="confirm"]').click();
        await page.waitForLoadState('networkidle');
        assert.ok((await page.locator('.ux-next-step').first().innerText()).includes('این نوبت لغو شده است'));
    });
    await test('secretary searches private patients in a modal and chooses without creating a duplicate', async () => {
        await page.setExtraHTTPHeaders({'X-UI-Actor':'secretary'});
        await page.setViewportSize({width:390,height:844});
        await page.goto(`${base}/management/bookings/new`);
        await page.locator('[data-patient-open]').click();
        await page.locator('[data-patient-search]').fill('UI-SYNTHETIC-01');
        await page.locator('[data-patient-find]').click();
        await page.locator('[data-patient-results] button').click();
        assert.equal(await page.locator('[data-patient-dialog]').evaluate(el => el.open), false);
        assert.equal(await page.locator('[data-new-patient]').isVisible(), false);
        assert.ok((await page.locator('[data-selected-patient]').innerText()).includes('UI-SYNTHETIC-01'));
        await page.locator('[data-patient-open]').click(); await page.locator('[data-patient-filter]').fill('نام ناموجود');
        assert.equal(await page.locator('[data-patient-empty]').isVisible(), true);
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('[data-patient-dialog]').evaluate(el => el.open), false);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
        await page.screenshot({path:'results/secretary-booking-mobile.png',fullPage:true});
        await page.setExtraHTTPHeaders({'X-UI-Actor':'patient'});
    });
    await test('guest login and patient account routes keep a clear action and fit mobile', async () => {
        for (const width of [1440,320]) {
            await page.setViewportSize({width,height:900});
            await page.setExtraHTTPHeaders({'X-UI-Actor':'guest'});
            assert.equal((await page.goto(`${base}/login`)).status(), 200);
            assert.equal(await page.locator('form[action$="/otp/request"]').isVisible(), true);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
            await page.setExtraHTTPHeaders({'X-UI-Actor':'patient'});
            for (const path of ['/dashboard','/patient']) {
                assert.equal((await page.goto(`${base}${path}`)).status(),200);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),true);
            }
        }
    });
    await test('JavaScript-off fallback retains native fields and submission', async () => {
        const fallback = await browser.newContext({javaScriptEnabled: false}); const native = await fallback.newPage();
        await native.goto(`${base}/__ui-fixture`); assert.equal(await native.locator('#kind').isVisible(), true);
        await native.locator('#kind').selectOption('finance'); await native.locator('#required-choice').selectOption('yes');
        await native.locator('#test-form button[type=submit]').click(); await native.waitForURL(`${base}/__ui-submit`);
        assert.equal(JSON.parse(await native.locator('body').innerText()).kind, 'finance');
        await native.goto(`${base}/admin/services`);
        assert.equal(await native.locator('[data-record-tools]').isVisible(),false);
        const record = native.locator('[data-record-panel][data-record-create=false]').first();
        await record.locator('summary').click(); assert.equal(await record.locator('[name=name]').isVisible(),true);
        await fallback.close();
    });
    assert.deepEqual(errors, []);
    assert.deepEqual(externalRequests, []);
    assert.deepEqual(failedAssets, []);
    assert.deepEqual(violations, []);
    await writeFile('results/summary.json', JSON.stringify({passed: true, checks: results, routes: routeChecks, javascriptErrors: errors, cspViolations: violations, externalRequests, failedAssets}, null, 2));
} catch (error) {
    await page.screenshot({path: 'results/failure.png', fullPage: true}).catch(() => {});
    await writeFile('results/summary.json', JSON.stringify({passed: false, checks: results, error: String(error), javascriptErrors: errors}, null, 2));
    throw error;
} finally { await browser.close(); }
