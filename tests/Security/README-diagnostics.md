# Raw ZAP evidence

`zap-plan.yaml` keeps the original synthetic-website crawl and scan configuration,
policy thresholds and exit status. Additional reports record alert HTTP messages
(`traditional-json-plus`) and a HAR of all synthetic localhost traffic. The second
context is selected only by the export job; it does not broaden the active scan.

The temporary branch-only workflow has been removed. The full release workflow
collects evidence only against its disposable synthetic Apache HTTPS fixture.
Do not run the plan against clinic installations or real patient data.

Raw HAR, HTML and JSON remain temporary runner files and are **not uploaded**.
An always-run sanitization step produces a new `security-evidence.json` only:
cookies and authorization/CSRF headers are omitted, request/response bodies and
query values are replaced by byte counts and SHA256 hashes, unknown URL path
segments and parameter names are hashed. Numeric rate-limit headers and redacted
redirect locations remain useful for diagnosing 302/429 response differences.
Alert IDs, severities, confidence and every alert instance are preserved; the
original scanner and summary exit status still decide security success/failure.

The upload step requires successful sanitization and uploads only that exact
file, with **seven-day retention**. Malformed or unexpected-origin evidence fails
closed without copying raw files as a fallback. Sanitizer tests run in CI.
Historical artifacts require separate deletion; changing retention does not
retroactively sanitize or shorten existing archives. No cookie/body is suitable
for a PR comment merely because its source was synthetic.

The original main run 41142344624 failed in both attempts with alert 40018 on
`GET /appointments?query=...`. Neither original report retained the compared
responses. Kernel/MySQL reproduction found unchanged SQL/data, but that alone
does not establish the reason for the scanner's repeated differential result.
The diagnostic run records the missing evidence; it must still fail on medium
or high alerts. No alert is filtered, reclassified or ignored.
