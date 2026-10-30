import assert from 'node:assert/strict';
import {mkdir} from 'node:fs/promises';
const {chromium} = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = 'http://127.0.0.1:18558';
const browser = await chromium.launch({headless:true,...(process.env.CHROME_EXECUTABLE ? {executablePath:process.env.CHROME_EXECUTABLE} : {})});
const context = await browser.newContext();
const external = [], errors = [], failed = [], violations = [];
await context.route('**/*', route => { if (new URL(route.request().url()).origin === base) return route.continue(); external.push(route.request().url()); return route.abort(); });
await context.exposeFunction('homeAboutCsp', value => violations.push(value));
await context.addInitScript(() => document.addEventListener('securitypolicyviolation', event => window.homeAboutCsp(event.violatedDirective)));
const page = await context.newPage();
page.on('pageerror', error => errors.push(error.message));
page.on('response', response => { if (response.status() >= 400 && ['image','stylesheet','script','font'].includes(response.request().resourceType())) failed.push(response.url()); });
const choose = async layout => {
    await page.locator('#home-about-settings > summary').click();
    const widget = page.locator('[name=home_about_layout]').locator('..');
    await widget.locator('.hcg-select-control').click();
    const labels = {classic:'مدل اول؛ روشن با کارت‌های ویژگی',dark:'مدل دوم؛ تیره با آمار تأییدشده',organic:'مدل سوم؛ قاب منحنی و کارت صورتی'};
    await widget.getByRole('option',{name:labels[layout],exact:true}).click();
};
const save = async layout => {
    await page.goto(base+'/admin/website-theme');
    await choose(layout);
    await Promise.all([page.waitForNavigation(),page.locator('[data-theme-settings] button[type=submit]').first().click()]);
};
await mkdir('results',{recursive:true});
try {
    for (const layout of ['classic','dark','organic']) {
        await save(layout);
        for (const width of [1440,1200,1024,768,375,320]) {
            await page.setViewportSize({width,height:1000}); await page.goto(base);
            await page.evaluate(() => document.fonts.ready);
            const section = page.locator('#about.home-about');
            assert.equal(await section.count(),1);
            assert.ok((await section.getAttribute('class')).includes('home-about--'+layout));
            await section.locator('[data-home-about-image]').evaluate(image => image.loading = 'eager');
            await page.waitForFunction(() => document.querySelector('[data-home-about-image]').complete && document.querySelector('[data-home-about-image]').naturalWidth > 0);
            await section.locator('[data-home-about-image]').scrollIntoViewIfNeeded();
            await section.locator('[data-home-about-image]').evaluate(image => image.decode());
            assert.equal(await section.locator('.ha-feature').count(),layout === 'dark' ? 0 : 4);
            assert.equal(await section.locator('.ha-stat').count(),layout === 'dark' ? 3 : 0);
            assert.equal(await section.locator('.ha-badge').count(),layout === 'classic' ? 0 : 1);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),true,`${layout}/${width}`);
            if (width > 850) {
                const text = await section.locator('.ha-content').boundingBox(), image = await section.locator('.ha-visual').boundingBox();
                assert.equal(text.x > image.x, layout !== 'dark');
            }
            if ([1440,320].includes(width)) await section.screenshot({path:`results/home-about-${layout}-${width}.png`});
        }
        await page.setViewportSize({width:640,height:1000}); await page.goto(base);
        await page.evaluate(() => document.documentElement.style.fontSize = '200%');
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),true,layout+' 200% text');
    }
    // Previewing another model must not publish it.
    await page.goto(base+'/admin/website-theme');
    await choose('dark');
    const previewPromise = context.waitForEvent('page');
    await page.locator('button[formtarget="_blank"]').click();
    const preview = await previewPromise; await preview.waitForLoadState();
    assert.equal(await preview.locator('#about.home-about--dark').count(),1); await preview.close();
    page.once('dialog', dialog => dialog.accept());
    await page.goto(base); assert.equal(await page.locator('#about.home-about--organic').count(),1);
    const imageUrl = await page.locator('[data-home-about-image]').getAttribute('src');
    await context.route(imageUrl,route => route.fulfill({status:404,body:'missing'}));
    await page.reload();
    await page.locator('[data-home-about-image]').evaluate(image => image.loading = 'eager');
    await page.waitForFunction(() => document.querySelector('[data-home-about-image]').src.endsWith('/clinic-art.webp') && document.querySelector('[data-home-about-image]').naturalWidth > 0);
    assert.equal(await page.locator('.ha-frame--fallback').count(),1);
    assert.ok(failed.every(url=>url===imageUrl)); failed.length=0; await context.unroute(imageUrl);
    await Promise.all([page.waitForNavigation(),page.locator('#about .ha-button').click()]);
    assert.equal(new URL(page.url()).pathname,'/about');
    for (const path of ['/about','/services','/articles','/calendar','/admin/website-theme']) {
        await page.goto(base+path); assert.equal(await page.locator('link[href$="/home-about.css"]').count(),0);
    }
    assert.deepEqual(external,[]); assert.deepEqual(errors,[]); assert.deepEqual(failed,[]); assert.deepEqual(violations,[]);
    console.log('PASS: three models saved through admin; 18 responsive model/viewport combinations; 200% text; correct image/text order; single selected model; features/stats/badges; private preview; missing-image fallback; real CTA and isolated CSS/CSP');
} finally { await browser.close(); }
