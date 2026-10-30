import assert from 'node:assert/strict';
import {mkdir} from 'node:fs/promises';
const {chromium} = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = 'http://127.0.0.1:18558';
const browser = await chromium.launch({headless: true, ...(process.env.CHROME_EXECUTABLE ? {executablePath: process.env.CHROME_EXECUTABLE} : {})});
const context = await browser.newContext();
await context.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
const page = await context.newPage();
const errors = [];
page.on('pageerror', error => errors.push(error.message));
page.on('dialog', dialog => dialog.accept());
await mkdir('results', {recursive: true});
try {
    await page.goto(base + '/management/medical-records');
    await page.getByRole('link', {name: 'بیمار کاملاً ساختگی EMR مرورگر', exact: true}).click();
    const profileUrl = page.url();
    for (const width of [1440, 768, 320]) {
        await page.setViewportSize({width, height: 1000});
        for (const path of [profileUrl, profileUrl + '/identity', profileUrl + '/new/visit', profileUrl + '/new/pregnancy', profileUrl + '/new/fertility']) {
            const response = await page.goto(path);
            assert.equal(response.status(), 200);
            assert.equal(await page.locator('html').getAttribute('dir'), 'rtl');
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${path} at ${width}`);
        }
        await page.screenshot({path: `results/emr-editor-${width}.png`, fullPage: true});
    }
    await page.setViewportSize({width: 1440, height: 1000});
    await page.goto(profileUrl + '/new/visit');
    await page.locator('[name="payload[complaint]"]').fill('EMR-BROWSER-SYNTHETIC <script>window.emrXss=true</script>');
    await page.getByRole('button', {name: 'ذخیره پیش‌نویس واقعی', exact: true}).click();
    await page.waitForURL(/\/records\/\d+$/);
    const recordUrl = page.url();
    assert.equal(await page.locator('[name="payload[complaint]"]').inputValue(), 'EMR-BROWSER-SYNTHETIC <script>window.emrXss=true</script>');
    assert.equal(await page.evaluate(() => window.emrXss), undefined);
    await page.getByRole('button', {name: 'نهایی‌سازی و امضای نسخه ذخیره‌شده', exact: true}).click();
    // Existing app confirm dialog, when enabled, must be acknowledged explicitly.
    const confirm = page.locator('#confirm-action');
    await confirm.waitFor({state: 'visible'});
    await confirm.locator('[data-confirm-choice="confirm"]').click();
    await page.waitForSelector('a:text-is("ثبت الحاقیه با حفظ اصل")');
    assert.equal(await page.locator('[name="payload[complaint]"]').isDisabled(), true);
    await page.screenshot({path: 'results/emr-signed.png', fullPage: true});
    await page.getByRole('link', {name: 'ثبت الحاقیه با حفظ اصل', exact: true}).click();
    await page.locator('[name="reason"]').fill('اصلاح ساختگی پزشک در آزمون مرورگر');
    await page.locator('[name="payload[complaint]"]').fill('EMR-BROWSER-AMENDMENT-SYNTHETIC');
    await page.getByRole('button', {name: 'ذخیره پیش‌نویس واقعی', exact: true}).click();
    await page.waitForURL(/\/records\/\d+$/);
    assert.notEqual(page.url(), recordUrl);
    await page.goto(recordUrl);
    assert.equal(await page.getByRole('link', {name: 'ثبت الحاقیه با حفظ اصل', exact: true}).count(), 0);
    assert.equal(await page.locator('[name="payload[complaint]"]').inputValue(), 'EMR-BROWSER-SYNTHETIC <script>window.emrXss=true</script>');
    await page.setViewportSize({width: 640, height: 1000});
    await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, '200% text size');
    await page.keyboard.press('Tab');
    assert.equal(await page.locator(':focus').count(), 1);
    const reception = await browser.newContext({extraHTTPHeaders: {'X-UI-Actor': 'secretary'}});
    const secretaryPage = await reception.newPage();
    await secretaryPage.goto(profileUrl);
    assert.equal(await secretaryPage.getByText('EMR-BROWSER-SYNTHETIC', {exact: false}).count(), 0);
    assert.equal((await secretaryPage.goto(recordUrl)).status(), 403);
    await secretaryPage.goto(profileUrl);
    await secretaryPage.setViewportSize({width: 320, height: 1000});
    assert.equal(await secretaryPage.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    await secretaryPage.screenshot({path: 'results/emr-secretary-320.png', fullPage: true});
    await reception.close();
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({passed: true, widths: [1440,768,320], draft: true, signed: true, amendmentPreservesOriginal: true, text200Percent: true, keyboardFocus: true, secretaryDenied: true, xssEscaped: true}));
} finally {
    await browser.close();
}
