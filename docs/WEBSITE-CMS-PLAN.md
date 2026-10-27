# Website/CMS extension — phase A0, verified repository inventory (2026-10-27)

## Scope and source of truth
Branch: `feat/doctor-website-dynamic-cms`. This extension is for the existing single-physician Laravel system, not a greenfield booking product. The reference screenshot and the supplied website/CMS requirements are the visual/content specification. Preserve the existing P1–P8 operational modules and security controls. The README attached to the earlier planning material described P0; the *current* repository README and Composer manifest describe P1–P8. Source code, not stale planning snapshots, governs implementation.

## Verified existing interfaces
- `composer.json`: PHP ^8.4, Laravel ^13.0, mPDF, PhpSpreadsheet; the production frontend is prebuilt without Vite.
- `routes/web.php`: `/` currently redirects to `/login`; `/calendar` renders the existing booking screen.
- `GET /api/v1/calendar`, `GET /api/v1/availability`: existing read-only calendar/capacity endpoints, protected by throttle:calendar.
- `POST /api/v1/appointments`: existing booking endpoint, protected by VerifiedBooker and throttle:booking; retain validation, authorization, idempotency, capacity and payment policies.
- `CalendarController@index`: selects active and publicly online `App\Modules\Catalog\Infrastructure\Service` records; resolves the current actor; renders `resources/views/calendar.blade.php`.
- `Service` model already has `name`, `price_irr`, `duration_minutes`, `buffer_minutes`, `capacity`, `is_active` and `is_online` with soft deletes.
- `/admin/services`: real CRUD routes secured by `auth` and `can:catalog.manage`; `ServiceController` delegates mutations to `CatalogService`.
- `resources/views/layouts/app.blade.php`: existing RTL operational layout; includes Bootstrap, Tabler for authenticated users, and `public/assets` links. Do not redesign it as a public marketing shell.
- `AppServiceProvider` registers existing permissions and rate limiters, including calendar and booking limits; do not introduce a second authentication/permission system.
- `.github/workflows/release-package.yml`: runs Pint, PHP lint, Laravel tests, MySQL tests/migration rollback, dependency audit, frontend asset validation and packaged staging checks. CI result must be checked after every implementation phase.

## Safe architecture decisions
1. Create a separate reusable public marketing layout (RTL, Vazirmatn, CSS variables) and page components, retaining the operational Tabler layout. Public home should link to the *real* `route('calendar')` until a fully tested embedded calendar reuses the same server endpoints and booking flow. Never present a decorative booking form as a real booking.
2. Build CMS features within Laravel (Models/FormRequests/Policies/Services/Blade), not a second SPA or admin. Reuse the existing `services` for appointment products; use separate editorial copy only where necessary and map explicitly to a real bookable service.
3. Before any migration, inspect all existing migrations, model relationships and policies. Add only additive, rollback-safe schema updates. Never rewrite P1–P8 migrations or drop patient/financial data.
4. CMS sections need typed schemas and allowlisted layout/color/link settings; no stored arbitrary Blade/PHP. Rich HTML must be sanitized on save and safely rendered. Published-only queries protect the public site; previews require authorization.
5. Public pages must not expose names, phones, clinical form answers, financial records, or other private data. Restrict media upload MIME/type/size, store sensitive files privately, and apply scoped authorization and CSRF to all new admin endpoints.
6. Marketing treatments/service descriptions are **not** guaranteed provided procedures; publish only physician-approved content. Seed examples unpublished if medically unverified.
7. Add automated tests with each new public/admin feature, including unauthorized edits, XSS, published-vs-draft visibility, and booking regression. Keep production asset deployment buildless and included in the release ZIP.

## Implementation sequence / acceptance gates
- **A1 public shell:** homepage screenshot-inspired hero, introduction, service cards from the real catalog, CTA to existing booking, shared header/footer, mobile layouts; tests: anonymous homepage 200, only published services, calendar endpoint unaffected, no PII, responsive/static asset coverage.
- **A2 core CMS:** typed published sections, settings, navigation, footer, slides and safe preview; Tabler UI using current auth/Gates; tests: CRUD, ordering, unpublish, deny unauthorized, escaping and rollback.
- **A3 editorial catalog/pages:** extend existing catalog without breaking pricing/booking, editorial categories and services pages; tests: real service mapping, index/detail, draft visibility and filtering.
- **A4 articles/pages/media:** content lifecycle, tags/categories, secure uploads, scheduled articles and SEO; tests: permissions, upload rejection, XSS, scheduled publication, sitemap.
- **A5 booking surface:** embed or progressively enhance existing calendar and form only after proving reuse of P1–P8 APIs and validation; test: identical reservation records/rules, OTP, holds, concurrent capacity, payment statuses and cancellations.
- **A6 release:** review security, RTL mobile/Desktop, accessibility, MySQL migration/rollback, PHPUnit, Pint, CI package smoke, actual ZIP manifest; do not imply real SMS/bank or hosting validation.

## Current gate
This file documents a *source-inspected subset*, not a complete schema audit. No PHP implementation or new migration is claimed by this phase, and no new runtime tests are claimed to have passed. Continue source inspection before shipping A1 and especially before defining CMS database tables.

## A2 implementation checkpoint (2026-10-27)
- Added additive `website_sections` schema (typed hero/about/cta copy, title/subtitle, escaped body, allowlisted background, internal-only CTA links, sort order, publication state).
- Wired `SectionService`, authorization through existing role/permission tables using `website.manage`, admin CRUD and public published-only rendering.
- Existing booking/calendar endpoints remain intact; no appointment, payment or patient table was edited.
- On existing installations, run the reviewed migration after backup and sync updated role permissions using the established RoleSeeder procedure. Do not seed with real patient details or reset existing user accounts.
- Regression tests now account for additive CMS migration before rolling back the historical P3–P7 migrations; those legacy tests previously assumed no migrations would ever be added after P7.
- This is a **limited section editor**, not the complete visual Section Builder, website navigation builder, blog or media manager. Preview and image management are still pending.
- CI must complete Pint, PHPUnit, MySQL rollback, secure ZIP and staging package smoke before declaring this stage accepted.
