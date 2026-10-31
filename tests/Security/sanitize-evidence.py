"""Minimize synthetic scan evidence before upload; never copy raw messages."""
import base64
import hashlib
import json
import pathlib
import re
import sys
import urllib.parse


def digest(value):
    raw = value if isinstance(value, bytes) else str(value).encode()
    return {"bytes": len(raw), "sha256": hashlib.sha256(raw).hexdigest()}


def safe_url(value):
    url = urllib.parse.urlsplit(value)
    if url.scheme != "https" or url.hostname != "localhost" or url.port != 18443:
        raise ValueError("Unexpected evidence target")
    known = {"appointments", "login", "services", "articles", "pages", "security-audit", "website-media", "website-theme.css", "assets", "sitemap.xml", "robots.txt", "calendar", "dashboard", "wallet", "privacy", "policies", "about", "contact"}
    path = "/".join(segment if segment in known or not segment else "[segment-" + digest(segment)["sha256"][:12] + "]" for segment in url.path.split("/"))
    return {"origin": "https://localhost:18443", "path": path,
            "query": [{"name": safe_name(k), "value": digest(v)} for k, v in urllib.parse.parse_qsl(url.query, keep_blank_values=True)]}


def safe_name(value):
    return value if value in {"query", "page", "id", "slug", "format", "_token", "code", "Cookie", "Authorization", "User-Agent", "Referer", "X-CSRF-TOKEN"} else "[name-" + digest(value)["sha256"][:12] + "]"


def headers(items):
    # Deny by default: cookies, credentials, CSRF tokens and unknown headers vanish.
    allowed = {"content-length", "x-ratelimit-limit", "x-ratelimit-remaining", "retry-after"}
    result = {}
    for item in items:
        name = item["name"].lower()
        value = str(item["value"])
        if name in allowed and value.isdigit():
            result[name] = int(value)
        elif name == "location":
            result[name] = safe_url(value)
    return result


def sanitize(source):
    report = json.loads((source / "zap-report.json").read_text(encoding="utf-8-sig"))
    alerts = []
    for site in report.get("site", []):
        if site.get("@host") != "localhost" or str(site.get("@port")) != "18443":
            raise ValueError("Unexpected report target")
        for alert in site.get("alerts", []):
            alerts.append({"id": str(alert["pluginid"]), "name": alert["alert"],
                           "risk": int(alert["riskcode"]), "confidence": str(alert.get("confidence", "")),
                           "instances": [{"url": safe_url(i["uri"]), "parameter": safe_name(i.get("param", "")),
                                          "attack": digest(i.get("attack", "")), "evidence": digest(i.get("evidence", ""))}
                                         for i in alert.get("instances", [])]})
    messages = []
    har_path = source / "zap-messages.har"
    if har_path.exists():
        har = json.loads(har_path.read_text(encoding="utf-8-sig"))
        for entry in har["log"]["entries"]:
            req, res = entry["request"], entry["response"]
            content = res.get("content", {})
            text = content.get("text", "")
            raw = base64.b64decode(text, validate=True) if content.get("encoding") == "base64" else text.encode()
            method = req["method"]
            if method not in {"GET", "POST", "HEAD", "PUT", "PATCH", "DELETE", "OPTIONS", "TRACE", "CONNECT"}:
                method = "OTHER"
            messages.append({"url": safe_url(req["url"]), "method": method,
                             "request_body": digest(req.get("postData", {}).get("text", "")),
                             "status": int(res["status"]), "response_headers": headers(res.get("headers", [])),
                             "response_body": digest(raw)})
    return {"format": 1, "sanitized": True, "raw_bodies_retained": False,
            "har_present": har_path.exists(), "alerts": alerts, "messages": messages,
            "high": sum(a["risk"] == 3 for a in alerts), "medium": sum(a["risk"] == 2 for a in alerts),
            "low": sum(a["risk"] == 1 for a in alerts)}


def main():
    source, destination = map(pathlib.Path, sys.argv[1:])
    # Validate the entire input before making anything eligible for upload.
    result = sanitize(source)
    destination.mkdir(parents=True, exist_ok=False)
    (destination / "security-evidence.json").write_text(json.dumps(result, indent=2), encoding="utf-8")
    print(json.dumps({"sanitized": True, "messages": len(result["messages"]), "alerts": len(result["alerts"])}))


if __name__ == "__main__":
    main()
