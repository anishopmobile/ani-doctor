# Homepage clinic services: three selectable models

The existing default-homepage `#services` section supports the three supplied references: `light` (model 1, pale background and consultation frame), `dark` (model 2, teal background and optional image badge/highlights), and `pastel` (model 3, organic frame and alternating pink accents). Only the selected model renders. Scoped CSS and image-error handling are loaded with this section; the standalone services page is unchanged.

## Management and real content

In `/admin/website-theme`, open **مدل نمایش خدمات مطب در صفحه اصلی**, select a model and save. The private preview uses the same component without publishing pending settings. Existing section visibility/order and published page-builder homepage precedence are preserved.

Cards retain the existing limit of six active online catalog services and editorial ordering. Names and detail links come from catalog records; summaries and card images require published service content. Editing service descriptions/images/order remains in the existing service content editor. No sample services, claims or remote photographs are inserted. Services without an available published raster image use the existing local heart icon. Failed image requests reveal that icon without an inline event handler.

The existing theme title/intro supply section copy. Button labels, the optional dark image badge and up to four optional dark highlights are stored in the existing theme JSON. Highlights start empty; no medical achievements are invented. Booking and all-services buttons use named internal routes.

## Independent section image

The identity-media editor adds a `services` slot with `services_is_published`, distinct from singular `service` media belonging to a catalog record. Upload a PNG/JPEG/WebP (existing 2MB limit) with alt text, preview it in the media editor, explicitly enable publication and save. This slot is independent of the homepage About image and physician portrait.

Upload/replacement unpublishes only this slot. Published deletion remains blocked until explicit unpublication. Existing authorization, CSRF, singleton owner guard, stale-settings protection and local storage are reused. Rendering chooses published section media, then published portrait, then local `clinic-art.webp`. Missing/unsupported paths are rejected; late image failure switches once to the local fallback.

`HomeSectionMediaService` shares the existing About image selection logic and resolves eagerly loaded service images without per-card database queries. No migration, new dependency or production seed is required. Booking/scheduling code is unchanged.

## Verification

Baseline: 345 PHPUnit tests / 2,779 assertions passed. After implementation: 351 tests / 2,875 assertions passed. New coverage includes all layouts, real ordered catalog/publication boundaries, escaped text, access denial, input validation, independent publication/replacement/deletion, absent-file/empty-state fallback, section visibility, private preview and page-builder precedence; catalog/appointment data remain unchanged.

The dedicated Chromium suite uses a guarded disposable SQLite fixture. It saves all models through the actual admin select, checks 18 combinations at 1440/1200/1024/768/375/320px, correct column counts/image position, 200% text, real links/booking CTA, unpublished summary exclusion, preview isolation, both HTTP image fallbacks, scoped assets and CSP. Desktop/mobile screenshots use explicitly synthetic content and existing local artwork. The release workflow runs the new fixture/suite after previous browser checks.
