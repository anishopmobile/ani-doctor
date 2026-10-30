import assert from 'node:assert/strict';
import {mkdir} from 'node:fs/promises';
const {chromium} = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = 'http://127.0.0.1:18558';
const browser = await chromium.launch({headless:true,...(process.env.CHROME_EXECUTABLE ? {executablePath:process.env.CHROME_EXECUTABLE} : {})});
const context = await browser.newContext();
const external = [], errors = [], failed = [], violations = [];
await context.route('**/*', route => { if (new URL(route.request().url()).origin === base) return route.continue(); external.push(route.request().url()); return route.abort(); });
await context.exposeFunction('homeContactCsp', value => violations.push(value));
await context.addInitScript(() => document.addEventListener('securitypolicyviolation', event => window.homeContactCsp(event.violatedDirective)));
const page = await context.newPage();
page.on('pageerror', error => errors.push(error.message));
page.on('response', response => { if (response.status() >= 400 && ['image','stylesheet','script','font'].includes(response.request().resourceType())) failed.push(response.url()); });
const choose = async layout => {
    await page.locator('#home-contact-settings > summary').click();
    const widget = page.locator('[name=home_contact_layout]').locator('..');
    await widget.locator('.hcg-select-control').click();
    const labels = {clinic:'مدل اول؛ روشن و اطلاعات مطب',dark:'مدل دوم؛ تیره',pastel:'مدل سوم؛ لطیف و صورتی'};
    await widget.getByRole('option',{name:labels[layout],exact:true}).click();
};
await mkdir('results',{recursive:true});
try {
    for (const layout of ['clinic','dark','pastel']) {
        await page.goto(base+'/admin/website-theme'); await choose(layout);
        await Promise.all([page.waitForNavigation(),page.locator('[data-theme-settings] button[type=submit]').first().click()]);
        for (const width of [1440,1200,1024,768,375,320]) {
            await page.setViewportSize({width,height:1000}); await page.goto(base);
            await page.evaluate(() => document.fonts.ready);
            const section = page.locator('#contact.home-contact');
            assert.equal(await section.count(),1); assert.ok((await section.getAttribute('class')).includes('home-contact--'+layout));
            assert.equal(await section.locator('.hc-card').count(),layout === 'dark' ? 7 : 6);
            assert.equal(await section.locator('.hc-form').count(),layout === 'dark' ? 0 : 1);
            assert.equal(await section.locator('a[href^="https://wa.me/"]').count(),layout === 'pastel' ? 0 : 1);
            assert.equal(await section.locator('a[href^="https://maps.google.com/"]').count(),1);
            await section.locator('img').evaluateAll(images => images.forEach(image => image.loading = 'eager'));
            await page.waitForFunction(() => document.querySelector('[data-home-contact-image]').complete && document.querySelector('[data-home-contact-image]').naturalWidth > 0);
            await section.locator('img').scrollIntoViewIfNeeded(); await section.locator('img').evaluate(image => image.decode());
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),true,`${layout}/${width}`);
            if (width > 850) {
                const text = await section.locator('.hc-content').boundingBox(), image = await section.locator('.hc-visual').boundingBox(); assert.ok(text.x > image.x);
            }
            if ([1440,320].includes(width)) await section.screenshot({path:`results/home-contact-${layout}-${width}.png`});
        }
        await page.setViewportSize({width:640,height:1000}); await page.goto(base); await page.evaluate(() => document.documentElement.style.fontSize = '200%');
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),true,layout+' 200% text');
    }
    await page.goto(base+'/admin/website-theme'); await choose('clinic');
    const previewPromise = context.waitForEvent('page'); await page.locator('button[formtarget="_blank"]').click();
    const preview = await previewPromise; await preview.waitForLoadState();
    assert.equal(await preview.locator('#contact.home-contact--clinic').count(),1); assert.equal(await preview.locator('.hc-form button').isDisabled(),true); await preview.close();
    page.once('dialog', dialog => dialog.accept()); await page.goto(base); assert.equal(await page.locator('#contact.home-contact--pastel').count(),1);
    const imageUrl = await page.locator('[data-home-contact-image]').getAttribute('src');
    await context.route(imageUrl,route => route.fulfill({status:404,body:'missing'})); await page.reload(); await page.locator('#contact img').evaluate(image => image.loading = 'eager');
    await page.waitForFunction(() => document.querySelector('[data-home-contact-image]').src.endsWith('/clinic-art.webp') && document.querySelector('[data-home-contact-image]').naturalWidth > 0);
    assert.equal(await page.locator('#contact .hc-frame--fallback').count(),1); assert.ok(failed.every(url=>url===imageUrl)); failed.length=0; await context.unroute(imageUrl);
    // A real guest submission becomes visible only in the authorized private inbox.
    const guest = await browser.newContext({extraHTTPHeaders:{'X-UI-Actor':'guest'}}); const publicPage = await guest.newPage();
    await publicPage.goto(base); const form = publicPage.locator('#contact .hc-form');
    await form.locator('[name=contact_sender]').fill('پیام ساختگی آزمون خطا'); await form.locator('[name=contact_mobile]').fill('invalid'); await form.locator('[name=contact_message]').fill('متن ساختگی آزمون اعتبارسنجی');
    await Promise.all([publicPage.waitForNavigation(),form.locator('button[type=submit]').click()]);
    assert.equal(await publicPage.locator('.hc-errors').count(),1); assert.equal(await form.locator('[name=contact_mobile]').getAttribute('aria-invalid'),'true');
    await form.locator('[name=contact_sender]').fill('پیام ساختگی آزمون مرورگر'); await form.locator('[name=contact_mobile]').fill('۰۹۰۰۰۰۰۰۰۰۰'); await form.locator('[name=contact_message]').fill('متن ساختگی آزمون ارسال واقعی فرم');
    await Promise.all([publicPage.waitForNavigation(),form.locator('button[type=submit]').click()]);
    assert.equal(await publicPage.locator('.hc-notice').innerText(),'پیام شما ثبت شد و در اختیار مدیر مطب قرار گرفت.'); await guest.close();
    await page.goto(base+'/admin/website-contact-messages'); const message = page.locator('article').filter({hasText:'متن ساختگی آزمون ارسال واقعی فرم'}).first();
    assert.equal(await message.count(),1); await Promise.all([page.waitForNavigation(),message.getByRole('button',{name:'علامت‌گذاری خوانده‌شده'}).click()]);
    assert.ok((await page.locator('article').filter({hasText:'متن ساختگی آزمون ارسال واقعی فرم'}).first().innerText()).includes('خوانده‌شده'));
    await Promise.all([page.waitForNavigation(),page.locator('article').filter({hasText:'متن ساختگی آزمون ارسال واقعی فرم'}).first().getByRole('button',{name:'حذف پیام'}).click()]);
    assert.equal(await page.getByText('متن ساختگی آزمون ارسال واقعی فرم',{exact:true}).count(),0);
    for (const path of ['/contact','/about','/services','/articles','/calendar','/admin/website-theme']) { await page.goto(base+path); assert.equal(await page.locator('link[href$="/home-contact.css"]').count(),0); }
    assert.deepEqual(external,[]); assert.deepEqual(errors,[]); assert.deepEqual(failed,[]); assert.deepEqual(violations,[]);
    console.log('PASS: three contact models saved through admin; 18 responsive combinations; 200% text; real contact/map/social URLs; private preview; image fallback; actual guest submission and private read/delete inbox; isolated assets/CSP');
} finally { await browser.close(); }
