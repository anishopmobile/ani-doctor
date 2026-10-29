# Public information pages

The about, contact, services, privacy and working-policy pages use the supplied flex layouts adapted to the existing RTL theme. `public/assets/information-pages.css` is loaded only by these five views, and every selector is scoped to `.info-pages`. The existing public header, footer, booking workflow, weekly announcement and management screens retain their layouts and behavior. No new dependencies or migrations are needed.

## Content and administration

- `/about` uses the existing website theme fields and published portrait, with the existing local WebP as fallback.
- `/contact` uses the saved contact details. Optional email, response hours and map links appear only when configured. Telephone and email actions are real links; no nonfunctional submission form is introduced.
- `/services` retains the existing active/online service query, publication filter and pagination. Cards show the stored name, published summary/media, duration and price in rials. Booking links select the actual service in the existing calendar.
- `/privacy` is a new independent page. In the existing website page builder, create a page with slug `privacy`, edit its blocks, then publish it. Only its published snapshot is public. Without a published page, a factual guide to the implemented information flows appears; it does not invent a retention period or promise automatic deletion.
- `/policies` is a new independent page. Its booking horizon, hold duration and ordinary change/cancellation cutoff come from the current scheduling and booking settings. An optional published page-builder page with slug `policies` supplies additional clinic content. These public requests never update booking settings or appointments.
- Links between the five information pages are displayed in their own content area; both new routes are included in the sitemap. Existing navigation configuration is preserved.

CMS publication, authorization, validation and escaping reuse the existing PageService and block renderer. Draft edits remain private until publication; unpublishing removes that content from the dedicated public route. No production content is inserted by the synthetic browser fixture.

## Verification

Run PHP tests with PHP 8.4 and the repository's normal test environment:

```sh
php vendor/phpunit/phpunit/phpunit
php vendor/bin/pint --test app/Http/Controllers/Web/InformationPageController.php app/Modules/Website/Application/InformationPageService.php tests/Feature/InformationPagesTest.php tests/Support/information-browser-seed.php
```

`InformationPagesTest` covers saved data, live policy settings without writes, published-only CMS content, XSS escaping, optional empty fields, the sitemap and CSS isolation. The local full PHP suite passed: 325 tests, 2,488 assertions.

The existing guarded disposable browser database must be used for browser checks; never seed a clinic database. After the existing `controls.mjs` and weekly announcement checks, run:

```sh
php tests/Support/information-browser-seed.php
cd tests/Browser
node information-pages.mjs
```

The dedicated browser check covers all five pages at 1440, 1024, 900, 768, 375 and 320 pixels, 200% text, CMS contents links, actual service selection, contact links, button contrast and absence of JavaScript, asset, CSP or external-request errors. It also verifies that the new stylesheet is absent from unrelated public and management pages. The release workflow runs this check and retains screenshots through its existing browser-evidence artifact.

Local verification also passed the existing `controls.mjs` suite (including article-create focus, permission denials and actual booking/cancellation), `weekly-schedule.mjs` (12 responsive route combinations and daily-editor interactions), the dedicated information-page browser suite, Pint on the four added PHP files, and `git diff --check`. Screenshots at desktop and 320px were inspected; the contact sidebar booking-button color collision was corrected and covered by a computed-color assertion. MySQL/concurrency, production-package smoke and staging security checks remain part of the existing GitHub CI pipeline.
