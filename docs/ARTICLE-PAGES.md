# Public article listing and detail

Branch: `feature/article-pages`. These changes are for review; merging into main requires the user's later approval.

## Existing architecture and scope

Reuse the Website module's ArticleService, article/taxonomy/media models, existing publication rules, media upload authorization and article management routes. The new public request validates listing parameters. Blade views use a stylesheet scoped to `.articles-page`, local icons and local JavaScript. The public header, footer and unrelated page styles are preserved. No runtime package or frontend build is added.

The supplied SVG is copied unchanged to `public/assets/images/article-default.svg`. Screenshot examples are visual references only; their sample authors, medical copy, category counts, photos and interactions are not production data. Browser screenshots use clearly labeled disposable synthetic content.

## Routes and content

- `GET /articles`: `q` searches title, summary and body using bound parameters and escaped literal LIKE wildcards. `category` and existing `tag` filter taxonomy slugs. `sort` accepts `newest` (default), `oldest`, or `title`; stable ID ordering breaks ties. `period` accepts `all`, `month` (last 30 days), or `year` (last 365 days). `page` is validated. Pagination retains validated filters, and a form submission resets the page.
- `GET /articles/{slug}`: existing permanent slug route, actual title/summary/body, optional public author, Jalali publication date in Tehran, categories, tags, latest four other articles and up to four related articles sharing a category. Counts, latest and related queries exclude drafts, undated and future publications.
- Sharing uses the actual canonical article route. Copy reports success or a readable fallback, with a readonly URL available without JavaScript. No artificial likes, views or feedback counters are added.
- The listing has four columns on desktop, two on tablet and one on mobile. The article sits on the right of its sidebar on desktop; below 992px the sidebar follows the article. Native GET filters and pagination work without JavaScript.

The existing body field remains escaped plain text with line breaks. Raw HTML, embedded scripts and arbitrary HTML editors are not enabled. Existing images are served by the controlled media route, rather than exposing storage paths or accepting arbitrary image URLs.

## Management and migration

The existing `/admin/website-articles` editor gains an optional public `author_name` field (maximum 120 characters). It is distinct from staff account names and does not expose private user information. All previous permissions, CSRF protection, publication dates and image upload limits remain in effect. The create form still focuses the title first.

Run `php artisan migrate --force` with PHP 8.4 before using the new branch on an installed site. The additive migration `2026_10_30_000001_add_author_name_to_website_articles.php` adds a nullable column, preserving existing rows. No content seed is needed in production. Rolling it back removes the new author column; article bodies, categories, appointments and financial records remain. Legacy rollback/upgrade tests include this additional migration before their original boundaries; their preservation assertions remain intact.

## Image fallback

ArticleImage uses only supported local media paths under `website-media/` and the existing controlled image route. Missing records/files or unsupported paths use the supplied SVG immediately. The shared image component covers cards, featured images and sidebar thumbnails. An external local script handles HTTP errors and image decode failures once, including failures occurring before script execution. No inline event handler or relaxed CSP is needed. Dimensions reserve layout space; featured images load eagerly and other images load lazily. Without JavaScript, backend missing-file fallback remains available; late network/decode failures require JavaScript.

## Verification

- Existing suite before the new schema: 325 tests, 2,488 assertions passed.
- New feature suite: 8 tests, 89 assertions passed.
- Full suite with the new schema and legacy rollback boundaries: 333 tests, 2,584 assertions passed.
- Dedicated Chromium verification: both pages at 1440, 1200, 1024, 768, 375 and 320px; actual 4/2/1 card counts, sidebar order, live search/category/sort and page-two results, empty state, Jalali dates, author, real share URLs, clipboard success/denial, 200% text, corrupt-byte/HTTP-404 fallback and native no-JavaScript pagination. No unexpected asset, JavaScript, external request or CSP errors.
- Pint on added/modified article PHP code and `git diff --check` passed.
- Existing full Chromium controls passed, including title focus during article creation, unauthorized patient denials and actual booking/cancellation. Existing weekly-schedule and five information-page browser suites also passed. Desktop and mobile screenshots were visually inspected with synthetic fixture data.

The release workflow runs `article-browser-seed.php` followed by `tests/Browser/articles.mjs` after its existing browser checks. The seed reuses the strict disposable SQLite guard and is never run against a clinic database. Browser results stay in the existing ignored evidence directory. Local MySQL/concurrency and production-package security checks are not claimed here; those are exercised by the existing GitHub workflow after a push.
