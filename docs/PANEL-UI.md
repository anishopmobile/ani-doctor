# Tabler RTL panels and progressive controls

Scope: operational administrator, secretary and patient pages using layouts/app. The public CMS marketing shell remains separate. All CSS, JavaScript and fonts are served locally; installation needs neither Node nor a frontend build.

## Reviewed dependencies

- Existing Tabler RTL 1.4.0 provides Bootstrap-compatible components. panel.css supplies the blue gradient, soft cards, mobile navigation and focus treatment. Do not also load Bootstrap's duplicate CSS on this layout.
- HCG Searchable Select 1.0.4, MIT, source https://github.com/html-code-generator/hcg-searchable-select at commit 081f8e76033b600cc761af849bef8b1075f20722. Bundled JS/CSS and license are in public/assets/vendor. Local patches honor inherited disabled fieldsets and route native invalid events to the visible control. Upstream text escaping is retained. No HTML option content or external requests are enabled.
- Existing Pardis from the pinned source described in docs/P2.md is reused for Persian date fields. The optional babakhani picker requires jQuery; reusing Pardis avoids another runtime stack. Reservation calendars remain inline and continue to use their existing availability endpoints.

## Contracts

form-controls.js enhances normal selects without changing their names or server payloads. Single selects use HCG with internal live search. Native multiple selects keep array submission and expose a search field within the same control container. Dynamic page/form builders are observed; removed controls are disposed. Disabled fieldsets, required validation, keyboard selection and reset behavior remain native. Without JavaScript the original fields and menu remain usable.

Inputs explicitly marked data-persian-date use a Persian popup with Escape, outside click and a labelled trigger. Their value remains ASCII YYYY-MM-DD Jalali. Clinical form date answers, desk filters, reports, discounts, schedule exceptions and article publication use this adapter. Article publication combines the selected date and Tehran time on the server, validates the calendar date, and stores UTC. Empty dates remain unpublished. Existing published_at submissions remain compatible. No reservation, patient or financial schema changes are introduced.

## Verification

tests/Browser contains a locked Playwright development dependency; it is not an application dependency or build step and is excluded from the host ZIP. CI starts the guarded tests/Support router on loopback with a disposable SQLite database, synthetic accounts and disabled providers. The router is never a production entry point. Browser tests cover live search, keyboard operation, escaped hostile option text, required and disabled fields, multiple values, Jalali payloads, dynamic builders, native form submission, mobile RTL overflow, role denial and JavaScript-off fallback. Screenshots and JSON results are uploaded separately from the ZIP.

WebsiteArticlePersianDateTest verifies Tehran-to-UTC conversion across the previous UTC day, Persian digits, invalid dates/times, empty publication dates and the legacy contract. The full PHP suites, MySQL integration, package/install/upgrade checks and isolated ZAP scan remain release gates. A successful automated scan is bounded evidence; it does not certify every device, assistive technology or a live banking/SMS installation.
