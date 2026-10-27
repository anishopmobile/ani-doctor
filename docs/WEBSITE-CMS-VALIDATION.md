# Website CMS validation — 2026-10-27

Base: `857b312`, PR #12, branch `feat/doctor-website-dynamic-cms`.

- GitHub Actions run `40493506042`: all steps succeeded, including SQLite tests, MySQL feature/concurrency/rollback, dependency audit, secure package creation, isolated Apache HTTPS installation/load checks and artifact upload. No failure required a fix in this run.
- Local PHP 8.4.27 full PHPUnit before final media-service extraction: 265 tests, 1841 assertions, passed. Migration/booking follow-up: 10 tests, 137 assertions, passed. Final validation is recorded by the CI run for the implementation commit.
- Desktop Chrome: public homepage rendered in Persian RTL against a separate synthetic SQLite database at loopback. Empty service state, header navigation, brand, contact and booking CTA present. Mobile/zoom and keyboard checks are recorded separately once actually performed.
- Upload formats remain JPEG/PNG/WebP, 2 MiB maximum, required alt text. Files remain outside DocumentRoot. No malware scanning, image transformations or CDN is claimed.
- Operational release scope is isolated CI staging. Clinic hosting credentials, DNS, live gateway/SMS and clinical editorial approval were not verified by this work.
