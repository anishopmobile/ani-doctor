import base64
import importlib.util
import json
import pathlib
import subprocess
import sys
import tempfile
import unittest

spec = importlib.util.spec_from_file_location("sanitizer", pathlib.Path(__file__).with_name("sanitize-evidence.py"))
sanitizer = importlib.util.module_from_spec(spec)
spec.loader.exec_module(sanitizer)


class EvidenceSanitizerTest(unittest.TestCase):
    def fixture(self, root):
        secret = "SYNTHETIC_SESSION_SENTINEL_12345"
        url = "https://localhost:18443/appointments?query=" + secret
        report = {"site": [{"@host": "localhost", "@port": "18443", "alerts": [
            {"pluginid": "40018", "alert": "SQL Injection", "riskcode": "3", "confidence": "2",
             "instances": [{"uri": url, "param": "query", "attack": secret, "evidence": secret}]}]}]}
        entry = {"request": {"url": url, "method": "GET", "cookies": [{"name": "session", "value": secret}],
                             "headers": [{"name": "Authorization", "value": secret}], "postData": {"text": secret}},
                 "response": {"status": 429, "cookies": [{"value": secret}],
                              "headers": [{"name": name, "value": secret} for name in ["Set-Cookie", "Cookie", "Authorization", "Proxy-Authorization", "X-CSRF-TOKEN", "X-XSRF-TOKEN", "Unknown"]] +
                                         [{"name": "Location", "value": url}, {"name": "X-RateLimit-Remaining", "value": "0"}],
                              "content": {"text": base64.b64encode(secret.encode()).decode(), "encoding": "base64"}}}
        (root / "zap-report.json").write_text(json.dumps(report))
        (root / "zap-messages.har").write_text(json.dumps({"log": {"entries": [entry]}}))
        (root / "unexpected-private.txt").write_text(secret)
        return secret, report, entry

    def test_removes_headers_cookies_bodies_and_values_but_keeps_alert_and_rate_evidence(self):
        with tempfile.TemporaryDirectory() as folder:
            root = pathlib.Path(folder)
            secret, _, _ = self.fixture(root)
            result = sanitizer.sanitize(root)
            encoded = json.dumps(result)
            self.assertNotIn(secret, encoded)
            self.assertNotIn(base64.b64encode(secret.encode()).decode(), encoded)
            self.assertNotIn("Set-Cookie", encoded)
            self.assertEqual(result["high"], 1)
            self.assertEqual(result["alerts"][0]["id"], "40018")
            self.assertEqual(result["messages"][0]["status"], 429)
            self.assertEqual(result["messages"][0]["response_headers"]["x-ratelimit-remaining"], 0)
            self.assertEqual(result["messages"][0]["response_body"]["bytes"], len(secret))

    def test_unknown_paths_names_and_userinfo_do_not_expose_values(self):
        secret = "SYNTHETIC_SECRET"
        url = f"https://user:{secret}@localhost:18443/{secret}?{secret}={secret}#{secret}"
        self.assertNotIn(secret, json.dumps(sanitizer.safe_url(url)))

    def test_unexpected_target_and_malformed_data_fail_without_upload_output(self):
        with tempfile.TemporaryDirectory() as folder:
            root = pathlib.Path(folder)
            _, report, _ = self.fixture(root)
            report["site"][0]["@host"] = "outside.invalid"
            (root / "zap-report.json").write_text(json.dumps(report))
            output = root / "output"
            result = subprocess.run([sys.executable, str(pathlib.Path(__file__).with_name("sanitize-evidence.py")), str(root), str(output)], capture_output=True)
            self.assertNotEqual(result.returncode, 0)
            self.assertFalse(output.exists())

    def test_cli_writes_only_minimized_json_and_never_copies_raw_input(self):
        with tempfile.TemporaryDirectory() as folder:
            root = pathlib.Path(folder)
            secret, _, _ = self.fixture(root)
            output = root / "output"
            result = subprocess.run([sys.executable, str(pathlib.Path(__file__).with_name("sanitize-evidence.py")), str(root), str(output)], capture_output=True)
            self.assertEqual(result.returncode, 0, result.stderr)
            self.assertEqual([p.name for p in output.iterdir()], ["security-evidence.json"])
            self.assertNotIn(secret, (output / "security-evidence.json").read_text())


if __name__ == "__main__":
    unittest.main()
