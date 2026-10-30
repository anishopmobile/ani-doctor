# Home about section: three selectable models

The existing `#about` section in the default homepage now supports all three supplied designs. Exactly one layout renders at a time: `classic` (light/cards/image left), `dark` (dark background/optional statistics/image right), or `organic` (curved image/minimal features/optional pink badge). Mobile layouts stack without shrinking text to the tiny sizes in the reference. The independent `/about` page is not redesigned by this change.

## Content and management

In the existing website theme editor, open **مدل بخش درباره ما در صفحه اصلی** and choose the active model. Save publishes the setting; the existing private preview shows the selected layout without saving it. The existing about title/text and brand/subtitle supply the copy. Dedicated button-label/internal-URL and optional image-badge fields are manageable in the same theme editor.

- `home_about_features`: up to four independently editable title/text pairs, used by the light and organic models. Defaults describe existing appointment/privacy/contact capabilities, not invented medical achievements. The list can be emptied.
- `home_about_stats`: up to three optional approved value/description pairs for the dark model. It starts empty. No sample patient totals, satisfaction metrics or years of practice are inserted or inferred from private appointment data.
- `home_about_badge_title` / `home_about_badge_text`: optional floating-card text for dark/organic models; an empty badge is omitted.
- `show_about` and existing home section order retain their behavior. A published page-builder homepage retains precedence over the default template; no duplicate section is appended to it.

## Independent image publication

The existing identity-media editor gains an `about` slot, with its own `about_is_published` flag and relation. Upload a permitted raster image with alt text, preview, then explicitly enable publication and save. Existing authorization, owner ID 1 guard, 2MB/type validation, settings version and CSRF protections are reused.

Uploading/replacing the about image unpublishes that slot, without changing the portrait used by the hero and other pages. Published media cannot be deleted until unpublished. Public media requests remain inaccessible while unpublished; authorized private preview can show a pending image.

Rendering selects the published about image first, then the published physician portrait, then the existing local `clinic-art.webp`. Missing/unsupported local files are rejected by the read service. A local, scoped script handles late HTTP/decode failures once, using the same local fallback. No reference Unsplash photo, external font/icon CDN, generated patient photograph or remote URL is introduced. The example pregnancy icon already exists locally.

## Implementation and verification

The Website module owns HomeSectionMediaService and the existing ThemeService/MediaService extensions. Scoped Blade/CSS/JS render the section and reuse current local icons. Existing JSON theme settings and polymorphic media rows store the new values; no migration, dependency or production seed is needed.

The baseline passed 339 tests / 2,680 assertions. The full PHP suite passed 345 tests / 2,779 assertions; the dedicated suite passed 6 tests / 99 assertions. Coverage includes every layout, validated configuration, private preview, optional/empty fields, escaping, image publication/replacement/deletion/authorization, safe fallback, hero-portrait independence, section visibility and page-builder precedence. Existing full Chromium controls also passed, including private settings workflows, article-create focus, role denials and actual hold creation/cancellation.

The dedicated browser suite saves each layout through the actual enhanced admin select, checks 18 layout/viewport combinations at 1440/1200/1024/768/375/320px, image/text order, one active section, cards/statistics/badges, 200% text, private preview without publication, HTTP image fallback, real CTA and asset/CSP isolation. Desktop/mobile screenshots use clearly labeled synthetic fixture content and the existing local artwork. The release workflow runs this guarded disposable fixture and browser suite after its existing checks.
