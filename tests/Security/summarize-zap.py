"""Summarize synthetic ZAP evidence without suppressing any alert."""
import json
import pathlib
import sys

report = json.loads(pathlib.Path(sys.argv[1]).read_text(encoding="utf-8-sig"))
alerts = []
for site in report.get("site", []):
    if site.get("@host") != "localhost" or str(site.get("@port")) != "18443":
        raise SystemExit("Unexpected target in synthetic report")
    for alert in site.get("alerts", []):
        alerts.append({"id": alert["pluginid"], "name": alert["alert"],
                       "risk": int(alert["riskcode"]), "instances": len(alert.get("instances", []))})
summary = {"engine_version": report.get("@version"), "generated": report.get("@generated"),
           "scope": "synthetic_public_cms_loopback_only_active_scan_5_minute_cap",
           "high": sum(a["risk"] == 3 for a in alerts), "medium": sum(a["risk"] == 2 for a in alerts),
           "low": sum(a["risk"] == 1 for a in alerts), "informational": sum(a["risk"] == 0 for a in alerts),
           "alerts": alerts, "independent_human_attestation": False}
pathlib.Path(sys.argv[1]).with_name("summary.json").write_text(json.dumps(summary, indent=2), encoding="utf-8")
print(json.dumps(summary))
if summary["high"] or summary["medium"]:
    raise SystemExit(1)
