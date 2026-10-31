# Raw ZAP evidence

`zap-plan.yaml` keeps the original synthetic-website crawl and scan configuration,
policy thresholds and exit status. Additional reports record alert HTTP messages
(`traditional-json-plus`) and a HAR of all synthetic localhost traffic. The second
context is selected only by the export job; it does not broaden the active scan.

The branch-only `zap-diagnostics.yml` reproduces the disposable Apache HTTPS
installation, fixtures, load and scan without re-running the PHP/browser suites.
It does not publish an installable artifact or replace release acceptance. Raw
evidence is retained for seven days and contains only ephemeral synthetic data;
do not run this workflow against clinic installations or real patient data.

The original main run 41142344624 failed in both attempts with alert 40018 on
`GET /appointments?query=...`. Neither original report retained the compared
responses. Kernel/MySQL reproduction found unchanged SQL/data, but that alone
does not establish the reason for the scanner's repeated differential result.
The diagnostic run records the missing evidence; it must still fail on medium
or high alerts. No alert is filtered, reclassified or ignored.
