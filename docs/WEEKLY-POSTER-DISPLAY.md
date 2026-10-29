# Public weekly schedule display

The public `/weekly-schedule` presentation follows the user-supplied `weekly-schedule.html`: doctor identity/photo, a rounded title, a vertical list with colored weekday labels on the right, time and location information, and a contact block. Every day includes its real Jalali date immediately below its weekday. Existing site navigation/footer and the administration editor retain their layouts.

Only the public Blade view and its new `weekly-poster.css` define the product-facing change. All new selectors are scoped to `.weekly-poster`; there are no body/global resets, external fonts, or new dependencies. The existing theme view composer now supplies this view with the published physician identity/photo/contact data, as it does for the other public views. Published portrait is preferred, with the existing local WebP as fallback. No sample Unsplash photo, sample shifts/locations, or Google Fonts import from the reference is included.

The working schedule, booking services, announcement overrides, management view/CSS/JS, date conversion, visibility setting, and database are unchanged. The view still displays all shifts, today's badge, ordinary/official holidays, unknown days, outside-office visits, optional escaped notes, and the stored contact phone. Empty general notes render no extra box.

Responsive behavior keeps the weekday/date together on the right and stacks time/location within the left section on narrow screens. All columns can shrink/wrap. Multiple shifts, long location strings and multiline notes are supported. The phone icon retains a circular shape in both contact layouts.

Validation completed locally using synthetic data:

- Full PHP suite: 321 tests / 2422 assertions passed, including a new DOM-based check of all seven date-under-weekday headers, multiple shifts and public/admin/home stylesheet isolation.
- Existing general Chromium suite: 29 scenarios / 99 route-and-viewport checks passed, with no JS errors, failed assets, external requests, or CSP violations.
- Expanded weekly Chromium suite: public and management pages at 1440, 1024, 768, 640, 375, and 320px; all seven Jalali dates beneath weekdays, public CSS isolation, 200% text size at 640px, real validation/save, public display, permissions, mobile menu, JS/assets/CSP/external-request checks passed.
- Additional synthetic edge checks at 320, 375, 768 and 1440px: two shifts, long location, multiline notes, unknown/official/closed statuses, circular contact icon, no horizontal card/page overflow passed.
- Desktop/mobile screenshots were visually reviewed. Pint, JavaScript syntax, Blade rendering/compilation and `git diff --check` passed.

CI now runs the dedicated weekly browser fixture and test after the existing general browser suite, preserving its assertions. No migrations, seed changes for production, package installation, or frontend build are required. Physical-device and full screen-reader testing were not performed.
