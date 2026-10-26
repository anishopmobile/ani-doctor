"use strict";
const assert = require("node:assert/strict");
const { chromium } = require("playwright");
const fs = require("node:fs");
const path = require("node:path");
const origin = process.env.UI_TEST_ORIGIN || "http://127.0.0.1:18764";
if (!["127.0.0.1", "localhost", "[::1]"].includes(new URL(origin).hostname))
    throw new Error("Loopback synthetic UI tests only");
const fixture = JSON.parse(
    fs.readFileSync(process.env.UI_TEST_FIXTURE, "utf8"),
);
assert.equal(fixture.synthetic, true);
const output = process.env.UI_TEST_OUTPUT || ".ui-test-output";
fs.mkdirSync(output, { recursive: true });
let checks = 0;
const errors = [];
const launches = { headless: true };
if (process.env.UI_TEST_BROWSER_CHANNEL)
    launches.channel = process.env.UI_TEST_BROWSER_CHANNEL;

async function ready(page) {
    await page.locator(".content-surface").waitFor();
    await page.waitForFunction(() =>
        Array.from(document.querySelectorAll("select")).every(
            (s) =>
                s.closest("template") ||
                s._hcgApi ||
                (s.multiple && s.classList.contains("hcg-select-native")),
        ),
    );
}
async function login(page, mobile) {
    await page.goto(origin + "/login");
    await page.locator("input[name=mobile]").fill(mobile);
    await page
        .locator('form[action$="/otp/request"] button[type=submit]')
        .click();
    const code = await page.locator(".alert-info strong").textContent();
    assert.match(code, /^[0-9]{6}$/);
    await page.locator("input[name=code]").fill(code);
    await page
        .locator('form[action$="/otp/verify"] button[type=submit]')
        .click();
    await page.waitForURL((url) => url.pathname !== "/login");
    await ready(page);
    checks++;
}
async function selectSearch(page, selector, query, text) {
    const id = await page
        .locator(selector)
        .evaluate((s) =>
            s._hcgApi.element
                .querySelector(".hcg-select-control")
                .getAttribute("aria-controls"),
        );
    const wrap = page
        .locator(".hcg-select")
        .filter({ has: page.locator("#" + id) });
    await wrap.locator(".hcg-select-control").click();
    await wrap.locator(".hcg-select-search").fill(query);
    await wrap
        .locator(".hcg-select-option:visible")
        .filter({ hasText: text })
        .first()
        .click();
}
async function noOverflow(page, width, label) {
    await page.setViewportSize({ width, height: 960 });
    await page.waitForTimeout(100);
    assert.ok(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth + 1,
        ),
        label + " overflow at " + width,
    );
    checks++;
}
async function main() {
    const browser = await chromium.launch(launches);
    try {
        const context = await browser.newContext({
            viewport: { width: 1440, height: 1000 },
        });
        const page = await context.newPage();
        page.on("pageerror", (e) => errors.push(e.message));
        page.on("console", (m) => {
            if (
                m.type() === "error" &&
                /Content Security|Refused|Uncaught/i.test(m.text())
            )
                errors.push(m.text());
        });
        await login(page, "09120000001");
        await page.screenshot({
            path: path.join(output, "admin-desktop.png"),
            fullPage: true,
            animations: "disabled",
        });
        for (const url of [
            "/admin",
            "/management/reports",
            "/management/schedule",
            "/admin/services",
            "/admin/discounts",
            "/admin/forms/new",
            "/calendar",
        ]) {
            console.log("Responsive:", url);
            await page.goto(origin + url);
            await ready(page);
            for (const width of [320, 390, 768, 1440])
                await noOverflow(page, width, url);
        }
        await page.goto(origin + "/management/reports");
        await ready(page);
        await selectSearch(page, "select[name=kind]", "مالی", "مالی کل مطب");
        assert.equal(
            await page.locator("select[name=kind]").inputValue(),
            "finance",
        );
        checks++;
        const from = page.locator("input[name=from]");
        await from
            .locator("..")
            .getByRole("button", { name: /انتخاب تاریخ:/ })
            .click();
        await page
            .locator(
                ".ui-date-dialog[open] .pardis-day[data-day]:not([disabled])",
            )
            .first()
            .click();
        assert.match(await from.inputValue(), /^\d{4}-\d{2}-\d{2}$/);
        assert.equal(await page.locator(".ui-date-dialog[open]").count(), 0);
        checks++;
        const posted = await page
            .locator("form")
            .filter({ has: from })
            .evaluate((form) => Object.fromEntries(new FormData(form)));
        assert.equal(posted.kind, "finance");
        assert.match(posted.from, /^\d{4}-\d{2}-\d{2}$/);
        checks++;
        const sort = page
            .locator(".hcg-select")
            .filter({ has: page.locator("select[name=sort]") });
        await sort.locator(".hcg-select-control").focus();
        await page.keyboard.press("Enter");
        await sort.locator(".hcg-select-search").fill("مبلغ");
        await page.keyboard.press("ArrowDown");
        await page.keyboard.press("Enter");
        assert.equal(
            await page.locator("select[name=sort]").inputValue(),
            "amount",
        );
        checks++;
        await page.goto(origin + "/calendar");
        await ready(page);
        await page.waitForFunction(
            () =>
                document.querySelector("#inline-calendar [aria-label]") &&
                document
                    .querySelector("#inline-calendar")
                    .getAttribute("aria-busy") === "false",
        );
        const response = page.waitForResponse(
            (r) => r.url().includes("/api/v1/calendar?") && r.status() === 200,
        );
        await selectSearch(page, "#service", "مجدد", "ویزیت مجدد");
        await response;
        await page
            .locator("#inline-calendar .pardis-day.day-available")
            .first()
            .click();
        await page.locator("#slots button").first().waitFor();
        checks++;
        await page.goto(origin + "/admin/forms/new");
        await ready(page);
        await page
            .getByRole("button", { name: "افزودن فیلد", exact: true })
            .click();
        await page.waitForFunction(() =>
            Array.from(document.querySelectorAll("[data-fields] select")).every(
                (s) => !!s._hcgApi,
            ),
        );
        assert.ok(
            (await page.locator("[data-fields] .hcg-select").count()) >= 3,
        );
        checks++;
        await page
            .locator('input[name="schema[0][label]"]')
            .fill("فیلد انتخابی ساختگی");
        await selectSearch(
            page,
            'select[name="schema[0][type]"]',
            "انتخاب",
            "انتخاب",
        );
        await page
            .getByRole("button", { name: "افزودن فیلد", exact: true })
            .click();
        await page.waitForFunction(
            () =>
                !!document.querySelector(
                    'select[name="schema[1][condition][field]"]',
                )?._hcgApi,
        );
        await selectSearch(
            page,
            'select[name="schema[1][condition][field]"]',
            "ساختگی",
            "فیلد انتخابی ساختگی",
        );
        assert.equal(
            await page
                .locator('select[name="schema[1][condition][field]"]')
                .inputValue(),
            "field_0",
        );
        checks++;
        await page.goto(origin + "/admin/discounts");
        await ready(page);
        await page.evaluate(() => {
            const select = document.querySelector("select[name=kind]");
            const option = new Option(
                '<img src=x onerror="window.uiXss=1">',
                "xss",
            );
            select.add(option);
            select.value = "xss";
            select.dispatchEvent(new Event("change", { bubbles: true }));
        });
        await selectSearch(page, "select[name=kind]", "onerror", "onerror");
        assert.equal(await page.evaluate(() => window.uiXss), undefined);
        assert.equal(await page.locator(".hcg-select img").count(), 0);
        checks++;
        await page.evaluate(() => {
            const select = document.querySelector("select[name=kind]");
            select.add(new Option("گزینه تازه", "new"));
            select.value = "new";
        });
        await page.waitForFunction(() =>
            document
                .querySelector("select[name=kind]")
                ._hcgApi.element.textContent.includes("گزینه تازه"),
        );
        checks++;
        await page.locator("select[name=kind]").evaluate((s) => s.form.reset());
        await page.waitForFunction(
            () =>
                document
                    .querySelector("select[name=kind]")
                    ._hcgApi.element.querySelector(".hcg-select-label")
                    .textContent === "درصدی",
        );
        checks++;
        await page.evaluate(() => {
            const fieldset = document.createElement("fieldset");
            fieldset.id = "disabled-probe";
            const select = document.createElement("select");
            select.name = "disabled_probe";
            select.add(new Option("یک", "one"));
            select.add(new Option("دو", "two"));
            fieldset.append(select);
            document.querySelector(".card-body").append(fieldset);
        });
        await page.waitForFunction(
            () => !!document.querySelector("[name=disabled_probe]")._hcgApi,
        );
        await page
            .locator("#disabled-probe")
            .evaluate((f) => (f.disabled = true));
        await page.waitForFunction(
            () =>
                document
                    .querySelector("[name=disabled_probe]")
                    ._hcgApi.element.querySelector(".hcg-select-control")
                    .getAttribute("aria-disabled") === "true",
        );
        checks++;
        await page
            .locator("#disabled-probe")
            .evaluate((f) => (f.disabled = false));
        await selectSearch(page, "select[name=disabled_probe]", "دو", "دو");
        assert.equal(
            await page.locator("[name=disabled_probe]").inputValue(),
            "two",
        );
        checks++;
        const patientContext = await browser.newContext({
            viewport: { width: 390, height: 844 },
        });
        const patient = await patientContext.newPage();
        patient.on("pageerror", (e) => errors.push(e.message));
        await login(patient, "09123456789");
        await patient.screenshot({
            path: path.join(output, "patient-mobile.png"),
            fullPage: true,
            animations: "disabled",
        });
        assert.equal(
            await patient
                .locator('.app-navigation a[href$="/admin/services"]')
                .count(),
            0,
        );
        checks++;
        await patient.goto(
            origin +
                "/private-forms/" +
                fixture.appointment +
                "/" +
                fixture.form,
        );
        await ready(patient);
        const multi = patient.locator("select[multiple]");
        await patient.locator(".ui-multi summary").click();
        await patient.locator(".ui-multi-panel input[type=search]").fill("دوم");
        await patient.locator(".ui-multi-options label:visible input").check();
        assert.equal(await multi.evaluate((s) => s.selectedOptions.length), 1);
        checks++;
        await patient.locator(".ui-multi summary").click();
        assert.equal(
            await patient.locator("input[data-persian-date]").isDisabled(),
            true,
        );
        await selectSearch(
            patient,
            'select[name="answers[choice]"]',
            "نمایش",
            "نمایش",
        );
        assert.equal(
            await patient.locator("input[data-persian-date]").isDisabled(),
            false,
        );
        checks++;
        await patient.locator(".ui-date-field button").first().click();
        await patient.screenshot({
            path: path.join(output, "date-mobile.png"),
            fullPage: true,
            animations: "disabled",
        });
        await patient
            .locator(
                ".ui-date-dialog[open] .pardis-day[data-day]:not([disabled])",
            )
            .first()
            .click();
        await selectSearch(
            patient,
            'select[name="answers[choice]"]',
            "پنهان",
            "پنهان",
        );
        assert.equal(
            await patient.locator("input[data-persian-date]").isDisabled(),
            true,
        );
        const values = await patient
            .locator("form[data-private-form]")
            .evaluate((form) => Array.from(new FormData(form).keys()));
        assert.ok(!values.includes("answers[visit_date]"));
        checks++;
        await patientContext.close();
        const secretaryContext = await browser.newContext({
            viewport: { width: 390, height: 844 },
        });
        const secretary = await secretaryContext.newPage();
        await login(secretary, "09120000002");
        assert.equal(
            await secretary
                .locator('.app-navigation a[href$="/admin/finance"]')
                .count(),
            0,
        );
        checks++;
        await secretary.goto(origin + "/management/bookings/new");
        await ready(secretary);
        await secretary.screenshot({
            path: path.join(output, "secretary-mobile.png"),
            fullPage: true,
            animations: "disabled",
        });
        await noOverflow(secretary, 320, "secretary booking");
        await secretaryContext.close();
        const plain = await browser.newContext({ javaScriptEnabled: false });
        const plainPage = await plain.newPage();
        await plainPage.goto(origin + "/login");
        assert.equal(
            await plainPage.locator("input[name=mobile]").isVisible(),
            true,
        );
        checks++;
        await plain.close();
        assert.deepEqual(errors, [], "Browser/CSP errors");
        const report = {
            passed: true,
            checks,
            browser: browser.version(),
            viewports: [320, 390, 768, 1440],
            roles: ["admin", "patient", "secretary"],
            scope: "synthetic loopback; no live providers",
        };
        fs.writeFileSync(
            path.join(output, "result.json"),
            JSON.stringify(report, null, 2),
        );
        console.log(JSON.stringify(report));
    } finally {
        await browser.close();
    }
}
main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
