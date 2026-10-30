# Dynamic public footer

The supplied footer HTML and screenshot are visual references. The implementation uses the existing local font, local SVG icons, published brand logo and website theme. Reference phone numbers, services, social `#` placeholders, non-existent FAQ/review routes and unconfigured claims of online consultation are not inserted into production.

## Layout and data

`website.partials.footer` is shared by the independent home template and all public website layouts. A dedicated view composer obtains read-only data from FooterService. `public/assets/footer.css` is loaded only with this partial, with every selector scoped to `.ani-footer`; the operational/calendar/panel layouts retain their styles. The existing public header gains an anchor target for the native, keyboard-operable back-to-top link.

- The light upper band has the existing appointment button label linked to the real calendar, an optional footer booking description and up to three independently editable feature items.
- Brand name, subtitle, published logo and footer introduction reuse the current theme. Social links and an optional slogan follow them.
- The services column reads up to eight active, online services using existing catalog order, with real detail routes and an all-services link. Media/editorial relations are not loaded for these names.
- The quick-links column uses published `footer` links in stored order, with the existing real-route fallback when none are available.
- Public contact fields supply the address, primary and optional secondary phone, optional email/map and optional response-hours text. Response hours are informational, not booking availability.
- The bottom band uses the actual brand and current Jalali year. Published `footer_legal` links replace its default privacy/policies/XML sitemap links.

## Management

Use the existing website theme editor for `footer_text`, `footer_booking_text`, `footer_slogan`, `footer_features` and `contact_secondary_phone`. The footer feature repeater has a client/server maximum of three, can be emptied and is independent of home features. Optional empty content has no visible empty block. Private theme preview also previews footer content without publishing it.

Use the existing website links editor with placements `footer`, `social` or `footer_legal`; labels, URLs, ordering, publication and new-tab behavior remain manageable there. Social links require credential-free HTTPS URLs. Known social hosts select a local icon; other hosts get a generic link icon. Unpublished or unsafe legacy footer links are omitted, and custom weekly-announcement links respect the weekly visibility switch. External new-tab links retain `noopener noreferrer`.

These values use existing JSON settings and the existing string placement column. No migration, production seed, new dependency, remote font/icon CDN or asset build is required. Existing authorization, CSRF protection, optimistic settings-version checks and Blade escaping are preserved. Footer reads do not modify bookings, working schedules, weekly announcements or patient information.

## Verification

The pre-change PHP baseline passed 334 tests / 2,595 assertions. Dedicated feature coverage checks actual stored data, optional empty values, publication, URL safety, authorization, independent theme persistence, private preview and escaping. Browser coverage spans nine public routes at six widths (54 combinations), actual 4/2/1 footer columns, real links, Jalali year, native back-to-top, footer zoom at 320/640, real admin save and the three-item limit, plus asset/JS/CSP/external-request checks. The release workflow adds the guarded disposable footer seed and browser check after its existing suites.

The full local PHP suite passed 339 tests / 2,680 assertions. The dedicated footer browser check and the existing full browser controls also passed, including article-create focus, role denials, real hold creation/cancellation and the existing eight-item repeaters. Pint, JavaScript syntax and whitespace checks passed. Desktop/mobile footer screenshots were inspected with labeled synthetic data. MySQL/concurrency, production package installation and security scans are checked by the GitHub workflow before merge.

At 320px with 200% root text, the unchanged public header's booking button has a small pre-existing horizontal overrun; zoom assertions at that width measure the footer itself. At 640px/200% text and all six normal widths, full-page overflow is checked. The header's layout CSS is outside this change.
