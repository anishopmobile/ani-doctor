"""Model ZAP ContextWrapper's additive seed URL / includePaths semantics."""
import pathlib
import re
import unittest


class ZapScopeTest(unittest.TestCase):
    def test_effective_context_preserves_public_cms_and_rejects_private_routes(self):
        plan = pathlib.Path(__file__).with_name("zap-plan.yaml").read_text()
        context = plan.split("- name: synthetic-website", 1)[1].split("- name: synthetic-evidence", 1)[0]
        seeds = re.findall(r"^        - (https://[^\s]+)$", context, re.M)
        includes, excludes = [], []
        target = includes
        for line in context.splitlines():
            if "excludePaths:" in line:
                target = excludes
            match = re.match(r"        - '(.*)'$", line)
            if match:
                target.append(match[1])
        # Upstream ContextWrapper.createContext adds each seed URL + '.*'.
        includes.extend(seed + ".*" for seed in seeds)

        def in_context(path):
            url = "https://localhost:18443" + path
            return any(re.fullmatch(p, url) for p in includes) and not any(re.fullmatch(p, url) for p in excludes)

        for path in ["/", "/?query=x", "/services", "/services/example?query=x", "/articles/example", "/pages/security-audit", "/website-media/image.jpg", "/website-theme.css", "/assets/app.js", "/sitemap.xml", "/robots.txt"]:
            with self.subTest(public=path):
                self.assertTrue(in_context(path))
        for path in ["/wallet", "/wallet?query=x", "/appointments", "/appointments?query=x", "/login", "/admin", "/management/patients", "/api/foo", "/payments/callback", "/otp", "/logout", "/pages/private", "/services-extra", "/unknown"]:
            with self.subTest(private=path):
                self.assertFalse(in_context(path))


if __name__ == "__main__":
    unittest.main()
