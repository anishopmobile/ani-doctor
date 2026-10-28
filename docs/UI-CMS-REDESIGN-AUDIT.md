# Unified clinic design and CMS audit

Base: approved PR #13 merged as db6bdb5. New work belongs only to feat/unified-clinic-design-cms.

## Reference actually inspected

https://drmyco.ir/ was opened in the browser; desktop screenshots and read-only computed styles were inspected. Visible navigation/text: rgb(32,77,147) (#204D93); search action: rgb(19,159,164) (#139FA4); accent links: rgb(241,101,29) (#F1651D); common controls: 8px radius and 16px text. Some dynamic physician lists did not populate in the inspected browser. No claim is made about unavailable dynamic states or a mobile reference inspection. No reference assets, proprietary fonts, logo, copy or medical claims are reused.

Independent palette: primary #087B80, hover #06686C, secondary #204D93, accent #A64B1F, background #F5F7FA, surface #FFFFFF, text #20344D, muted #52657A, border #D6E1EA, success #237355, warning #956019, error #B33B4C, info #204D93, focus #204D93. Darker primary/accent support readable white/text combinations. Existing saved branding is preserved; the recommended palette is an explicit editor choice. Vazirmatn remains local.

## Existing capabilities retained

Laravel 13/PHP 8.4 modular monolith, Blade, local Bootstrap/Tabler/Pardis/HCG. routes/web.php defines public home/services/articles/pages, private booking/payment/wallet/forms, and Gate-protected desk/patients/catalog/schedule/report/content settings. Booking locks/idempotency, UTC storage, Tehran/Jalali presentation, private media, immutable published page snapshots and all role boundaries remain authoritative. No multi-doctor feature, live provider integration or frontend build is introduced.

Reusable: ThemeService, private website_media, PageService/PageSchema, section/article/link/service-content CRUD, real desk summary, existing native confirmation and calendar/select adapters. Gaps: stacked hardcoded color layers; fixed fallback-home copy; no dedicated portrait/favicon slots; fragmented settings navigation; missing general mobile public menu, consistent route context and unsaved-form feedback. Existing page builder already provides structured FAQ/content/order and previews; it is extended/reused rather than replaced.

## Settings and consumers (implementation map)

| Group | Source / management | Consumers |
|---|---|---|
| Doctor identity | ThemeService brand_name/subtitle; existing theme editor | public header/footer, introduction, workspace brand |
| Contact / SEO | existing brand JSON, extended with email/hours/website/map/footer | home/contact widgets, metadata, footer |
| Colors | three existing columns plus bounded brand JSON palette keys | generated external theme CSS and shared design tokens, both shells |
| Home copy / visibility / order | bounded structured brand JSON in same editor | fallback home hero and typed home-section partials |
| Homepage alternative | existing home PageService published snapshot | root URL; snapshot takes precedence, editor explains it |
| Logo / portrait / favicon | dedicated singleton owner types in existing private media registry | logo across shells; opted-in portrait; opted-in favicon |
| Service text/images/prices | existing Catalog + WebsiteServiceContent + media | service list/detail and reservation catalog; archived history retained |
| Sections/articles/FAQ | existing section/article CRUD and typed page-builder FAQ; structured fallback FAQ | public published content only |
| Menu/social links | existing validated header/footer WebsiteLink CRUD | shared header/footer, including optional social links |
| Schedule / booking | existing schedule/exception/settings endpoints | actual availability; informational office hours never alter slots |

Infrastructure secrets, payment credentials, security policies, arbitrary HTML/JS/CSS and free-form font URLs remain outside general settings. Layout geometry, semantic status colors, transaction rules and technical provider settings stay in code or their existing specialist pages. All new media are raster-only and publication-gated; files referenced by a published identity slot must be unpublished before deletion. No clinical data is added to test fixtures or public UI.

## Verification scope

Full PHP suites, meaningful settings/media/permission tests, locked Chromium coverage of public and administrative route families at mobile/tablet/desktop, native/date/select/dynamic-builder regressions, no external asset requests, form errors/menu/reset/unsaved-state behavior, MySQL integration and existing package/install/upgrade/security CI. Record actual evidence after execution; this document is an implementation plan, not a success claim.

## Confirmed schema addition
Only website_service_contents.sort_order (default 0, bounded 0..100000 in requests) is added for published service presentation order. Catalog, patient, booking and financial columns are unchanged. Core-record fingerprints and historic rollback/reapply tests remain release gates; no destructive migration is required for an existing installation.
