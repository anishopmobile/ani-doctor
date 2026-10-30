import assert from 'node:assert/strict';
import {mkdir} from 'node:fs/promises';
const {chromium} = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = 'http://127.0.0.1:18558';
const browser = await chromium.launch({headless:true,...(process.env.CHROME_EXECUTABLE ? {executablePath:process.env.CHROME_EXECUTABLE} : {})});
const context = await browser.newContext();
const external = [], errors = [], failed = [], violations = [];
await context.route('**/*', route => { if (new URL(route.request().url()).origin === base) return route.continue(); external.push(route.request().url()); return route.abort(); });
await context.exposeFunction('homeServicesCsp', value => violations.push(value));
await context.addInitScript(() => document.addEventListener('securitypolicyviolation', event => window.homeServicesCsp(event.violatedDirective)));
const page = await context.newPage();
page.on('pageerror', error => errors.push(error.message));
page.on('response', response => { if (response.status() >= 400 && ['image','stylesheet','script','font'].includes(response.request().resourceType())) failed.push(response.url()); });
const choose = async layout => {
    await page.locator('#home-services-settings > summary').click();
    const widget = page.locator('[name=home_services_layout]').locator('..');
    await widget.locator('.hcg-select-control').click();
    const labels = {light:'مدل اول؛ روشن با تصویر مشاوره',dark:'مدل دوم؛ تیره',pastel:'مدل سوم؛ پاستلی و قاب منحنی'};
    await widget.getByRole('option',{name:labels[layout],exact:true}).click();
};
await mkdir('results',{recursive:true});
try {
    for (const layout of ['light','dark','pastel']) {
        await page.goto(base+'/admin/website-theme'); await choose(layout);
        await Promise.all([page.waitForNavigation(),page.locator('[data-theme-settings] button[type=submit]').first().click()]);
        for (const width of [1440,1200,1024,768,375,320]) {
            await page.setViewportSize({width,height:1000}); await page.goto(base);
            await page.evaluate(() => document.fonts.ready);
            const section = page.locator('#services.home-services');
            assert.equal(await section.count(),1);
            assert.ok((await section.getAttribute('class')).includes('home-services--'+layout));
            assert.equal(await section.locator('.hs-card').count(),6);
            assert.equal(await section.getByText('PRIVATE-SERVICE-DRAFT').count(),0);
            assert.equal(await section.locator('.hs-highlights').count(),layout === 'dark' ? 1 : 0);
            assert.equal(await section.locator('.hs-badge').count(),layout === 'dark' ? 1 : 0);
            await section.locator('img').evaluateAll(images => images.forEach(image => image.loading = 'eager'));
            await page.waitForFunction(() => [...document.querySelectorAll('#services img')].every(image => image.complete && image.naturalWidth > 0));
            await section.scrollIntoViewIfNeeded();
            await section.locator('img').evaluateAll(images => Promise.all(images.map(image => image.decode())));
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),true,`${layout}/${width}`);
            const columns = await section.locator('.hs-card').evaluateAll(cards => cards.filter(card => Math.abs(card.getBoundingClientRect().top - cards[0].getBoundingClientRect().top) < 2).length);
            assert.equal(columns,width > 1100 || (width <= 850 && width > 650) ? 3 : width > 380 ? 2 : 1,`${layout}/${width} columns`);
            if (width > 850) {
                const text = await section.locator('.hs-content').boundingBox(), image = await section.locator('.hs-visual').boundingBox();
                assert.ok(text.x > image.x);
            }
            if ([1440,320].includes(width)) await section.screenshot({path:`results/home-services-${layout}-${width}.png`});
        }
        await page.setViewportSize({width:640,height:1000}); await page.goto(base);
        await page.evaluate(() => document.documentElement.style.fontSize = '200%');
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),true,layout+' 200% text');
    }
    await page.goto(base+'/admin/website-theme'); await choose('dark');
    const previewPromise = context.waitForEvent('page'); await page.locator('button[formtarget="_blank"]').click();
    const preview = await previewPromise; await preview.waitForLoadState();
    assert.equal(await preview.locator('#services.home-services--dark').count(),1); await preview.close();
    page.once('dialog', dialog => dialog.accept()); await page.goto(base);
    assert.equal(await page.locator('#services.home-services--pastel').count(),1);
    const imageUrl = await page.locator('[data-home-services-image]').getAttribute('src');
    const cardUrl = await page.locator('[data-home-service-card-image]').getAttribute('src');
    for (const url of [imageUrl,cardUrl]) await context.route(url,route => route.fulfill({status:404,body:'missing'}));
    await page.reload(); await page.locator('#services img').evaluateAll(images => images.forEach(image => image.loading = 'eager'));
    await page.waitForFunction(() => document.querySelector('[data-home-services-image]').src.endsWith('/clinic-art.webp') && document.querySelector('[data-home-services-image]').naturalWidth > 0);
    assert.equal(await page.locator('#services .hs-frame--fallback').count(),1);
    await page.locator('[data-home-service-card-fallback]').waitFor({state:'visible'});
    assert.ok(failed.every(url=>[imageUrl,cardUrl].includes(url))); failed.length=0;
    for (const url of [imageUrl,cardUrl]) await context.unroute(url);
    const detail = await page.locator('#services .hs-card').first().getAttribute('href');
    await Promise.all([page.waitForNavigation(),page.locator('#services .hs-card').first().click()]);
    assert.equal(page.url(),detail);
    await page.goto(base+'/admin/website-theme'); await choose('light');
    await Promise.all([page.waitForNavigation(),page.locator('[data-theme-settings] button[type=submit]').first().click()]);
    await page.goto(base); await Promise.all([page.waitForNavigation(),page.locator('#services .hs-button--primary').click()]);
    assert.equal(new URL(page.url()).pathname,'/calendar');
    for (const path of ['/about','/services','/articles','/calendar','/admin/website-theme']) {
        await page.goto(base+path); assert.equal(await page.locator('link[href$="/home-services.css"]').count(),0);
    }
    assert.deepEqual(external,[]); assert.deepEqual(errors,[]); assert.deepEqual(failed,[]); assert.deepEqual(violations,[]);
    console.log('PASS: three service layouts saved through admin; 18 responsive combinations; real catalog cards/links; published summaries; 200% text; private preview; both image fallbacks; CTA; CSS/CSP isolation');
} finally { await browser.close(); }
