# Hero feature assets and mobile readability

The user's four-feature design and `clinic-art.webp` are preserved. This correction starts from `89fe5d3` on main and changes only static presentation/assets; no database, route, authorization, or booking behavior changes.

- Replace the empty tracked file `public/assets/icons` with the actual directory containing the four user-supplied SVGs: pregnancy, pregnancy-care, heart, consultation. Files are byte-identical to the supplied originals and parse as XML. No remote icon dependency is added.
- Match the WebP's intrinsic dimensions with `width="1254" height="1254"`; preserve responsive automatic height, provide descriptive alt text, and prioritize the hero image for loading. The WebP binary is unchanged.
- Keep the existing rounded frame, palette, separators, four feature labels, and desktop four-column design. Below 1200px, use two columns so the half-width tablet hero and narrow mobile screen have room for readable labels. Mobile labels increase from 10px to 14px. Labels wrap, row-end separators are hidden in two-column mode, and the frame has 24px separation from the actions.

Validation uses an isolated SQLite fixture, with no production data:

- Full PHP suite: 320 tests / 2385 assertions passed.
- Pint, Blade compilation, and `git diff --check` passed.
- Full existing Chromium suite: 29 scenarios and 99 route/viewport checks passed, with no JavaScript errors, failed assets, external requests, or CSP violations. No existing assertions were removed or weakened.
- Targeted Chromium checks at 320, 375, 600, 768, 1024, and 1440px passed: four loaded icons, WebP intrinsic/declared aspect ratio match, font size at least 14px, two rows below 1200px and one desktop row, no card or page horizontal overflow. Mobile and desktop screenshots visually reviewed.
- SVG source/copy SHA-256 equality and XML parsing passed for all four assets.

No migrations, dependency installation, or frontend build is needed for this correction. Upload the new icon directory along with the changed template and stylesheet. Use the existing deployment/cache procedure. Physical-device and full screen-reader testing were not performed.
