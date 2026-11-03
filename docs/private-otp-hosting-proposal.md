# Private OTP testing on shared hosting (proposal)

Status: design only; no runtime bypass is enabled by this PR.

## Diagnosis

LocalOtpPreview::enabled requires APP_ENV=local, LOCAL_OTP_PREVIEW=true, a loopback APP_URL, a loopback request host and a direct loopback REMOTE_ADDR. A public deployment deliberately fails these checks. AuthService::requestCode otherwise calls the SMS provider; failures invalidate the challenge and return SMS_UNAVAILABLE. Web OtpController removes preview_code from the challenge metadata and flashes it only when the local guard permits it. Bale removes preview_code independently. Do not relax these guards or trust forwarded headers to enable preview.

## Proposed implementation

Use a separate staging installation/database with synthetic patients. Add an explicitly enabled, time-bounded private delivery transport restricted to exact normalized test mobile numbers. Require an operator expiry timestamp (maximum 24 hours), HTTPS deployment, and staging environment; fail closed on missing/invalid configuration. Never enable it for production accounts or let an HTTP request choose the transport or destination.

The normal browser OTP request should generate its random code and preserve its challenge ID, session binding, hashing, expiry, cooldown, attempt limits and single-use verification. For allowlisted staging mobiles only, deliver the code into a dedicated private outbox outside the web document root with owner-only directory/file permissions. No HTTP response, flash, API response, application log, cron email or public download may contain the code. Reject unsafe/symlink paths. File write failure must invalidate the challenge like SMS failure. Other mobiles retain normal SMS behavior.

A console-only cron command should drain/expire the outbox into a fixed private operator inbox accessible through the hosting file manager. Encrypt stored outbox secrets using the application encryption service and erase both outbox and inbox on expiry or consumption. Do not expose a web maintenance script. Prevent overlap and stale challenge delivery; avoid mobile numbers or codes in cron arguments/output. Restrict hosting account access: the private inbox is an authentication credential.

## Hosting runbook after implementation

1. Deploy the reviewed staging branch with its dependencies; point the document root at public/ only. Keep APP_DEBUG=false, set APP_URL to the actual HTTPS staging URL, APP_ENV=staging and LOCAL_OTP_PREVIEW=false.
2. Configure the exact test-number allowlist, short expiry and private inbox location using the hosting file manager. Do not send credentials in chat.
3. Use a one-off cron invoking the hosting provider's PHP CLI binary and the absolute artisan path with config:clear, then remove that cron. This clears stale configuration; it does not enable local preview remotely.
4. Add the reviewed private delivery console command to cron every minute with overlap protection. Request OTP through the browser, read the private inbox via the hosting file manager, and enter the code within its normal lifetime.
5. Disable the transport, clear configuration cache again, remove the cron and erase private inbox/outbox files after testing. Verify normal SMS failure behavior returns.

Command names and settings for the proposed transport are intentionally not presented as working features: they require implementation and review first.

## Required acceptance tests before implementation can ship

- Existing LocalOtpPreviewTest stays green, especially production/public-origin/remote-peer and forwarded-header denial.
- Disabled, expired, malformed, production or non-HTTPS configurations cannot select private delivery.
- Only exact normalized allowlisted staging numbers select it; all other numbers use SMS.
- Web/API/Bale responses, sessions and logs never disclose private delivery codes.
- Private paths, permissions, symlink rejection, encryption and write-failure invalidation.
- Concurrent request/resend/drain cannot deliver stale challenges or extend cooldown/expiry.
- Wrong-code attempt lockout, expiry, single consumption, session binding and inactive-user rejection remain effective.
- Expiry/consumption cleanup, overlapping cron runs, and emergency disable with cached configuration.
