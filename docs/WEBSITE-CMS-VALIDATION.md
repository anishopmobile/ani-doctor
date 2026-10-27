# Website CMS validation — 2026-10-27

Base: `857b312`, PR #12, branch `feat/doctor-website-dynamic-cms`.

- GitHub Actions run `40493506042`: all steps succeeded, including SQLite tests, MySQL feature/concurrency/rollback, dependency audit, secure package creation, isolated Apache HTTPS installation/load checks and artifact upload. No failure required a fix in this run.
- Local PHP 8.4.27 full PHPUnit before final media-service extraction: 265 tests, 1841 assertions, passed. Migration/booking follow-up: 10 tests, 137 assertions, passed. Final validation is recorded by the CI run for the implementation commit.
- Desktop Chrome and responsive width 320 CSS pixels: public homepage rendered in Persian RTL against a separate synthetic SQLite database at loopback. Empty service state, header navigation, brand, contact and booking CTA present; header and hero text/buttons remain within the viewport. This is browser emulation, not a physical mobile-device test.
- Color review found default primary `#ce4081` gave only 4.492:1 against white. Default changed to `#c83c7b`; theme validation now requires >=4.5:1 for primary/text against white and the configured surface. Low-contrast settings have negative coverage. Older stored palettes must be reviewed and saved again; existing DB values are not silently overwritten.
- Media lifecycle test is in the MySQL Integration suite, ensuring committed archive cleanup and rollback retention run against MySQL in CI. Local explicit lifecycle invocation passed (1 test, 8 assertions). Final related follow-up passed (11 tests, 80 assertions).
- Upload formats remain JPEG/PNG/WebP, 2 MiB maximum, required alt text. Files remain outside DocumentRoot. No malware scanning, image transformations or CDN is claimed.
- Operational release scope is isolated CI staging. Clinic hosting credentials, DNS, live gateway/SMS and clinical editorial approval were not verified by this work.
