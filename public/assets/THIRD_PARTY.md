# Prebuilt assets

No npm, Vite, remote font requests or runtime CDN is required.

| File | Version | Source | License |
|---|---|---|---|
| vendor/bootstrap.rtl.min.css | 5.3.8 | https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.rtl.min.css | MIT, vendor/bootstrap-LICENSE |
| vendor/tabler.rtl.min.css | 1.4.0 | https://cdn.jsdelivr.net/npm/@tabler/core@1.4.0/dist/css/tabler.rtl.min.css | MIT, vendor/tabler-LICENSE |
| fonts/Vazirmatn-Regular.woff2 | 33.003 | https://github.com/rastikerdar/vazirmatn/tree/v33.003 | SIL OFL 1.1, fonts/OFL.txt |
| vendor/pardis.js and pardis.css | source 3.0.0, commit 63cab98c0a5d2c37db11faa87392a582c7679966 | https://github.com/alirezakariminejad/Pardis-Jalali-Datepicker/tree/63cab98c0a5d2c37db11faa87392a582c7679966/lib | MIT, vendor/pardis-LICENSE |

Retain upstream license notices when updating assets. Only regular font weight is bundled in P1.

## Shared UI controls
HCG searchable select 1.0.4: https://github.com/html-code-generator/hcg-searchable-select/tree/081f8e76033b600cc761af849bef8b1075f20722 — MIT, vendor/hcg-select-LICENSE. Unmodified upstream hcg-select.js SHA256 4680fabe4cc05ac7ccc5cedef5a294fbc7c0c4c34d260c641d6ec6d3db3ea238; hcg-select.css SHA256 ebf166f5bd69b931a8d4f16f7fc8c62dad2184c50eedc947ca82832c8455fc4b. The application adapter and Persian labels live separately in ui-controls.js/controls.css. Upstream is single-select only; multiple uses a small native-value-preserving companion. Tabler 1.4.0 is now shared by all roles; Pardis version above is reused for date inputs. No runtime CDN or jQuery.
