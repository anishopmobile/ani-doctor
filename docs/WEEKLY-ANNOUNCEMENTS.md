# برنامه هفتگی اطلاع‌رسانی پزشک

## معماری بررسی‌شده و دامنه

مبنای بررسی `0676180` روی شاخه `main`، با working tree پاک و remote برابر `anishopmobile/ani-doctor` بود. شاخه قابلیت `feature/weekly-doctor-schedule` است.

نسخه قفل‌شده Laravel برابر `v13.38.0`، PHP موردنیاز `^8.4` و نسخه اجرای آزمون `8.4.27` است. پروژه Modular Monolith با MVC و Application Service، Blade، Bootstrap/Tabler محلی، Vanilla JS و فایل‌های آماده در `public/assets` است؛ وابستگی جدید یا build جدید ندارد. اسناد AI_RULES، PRD، ARCHITECTURE، SECURITY، COMPONENTS و UI_GUIDELINES و ترجیح طراحی محلی بررسی شدند. این افزوده، قرارداد اطلاع‌رسانی مستقل `FR-SCH-04 / AC-WEEKLY` است؛ قواعد FR-SCH-01 تا 03، AC-SCH، AC-HOL و FR-APT-01 حفظ می‌شوند.

مسیر برنامه کاری، `ScheduleController`، `ScheduleRequest`، `ScheduleService`، جداول `weekly_schedules` و `work_shifts` و استثناهای تاریخ بررسی شدند. `ScheduleService::range` اولویت استثنای تاریخ بر برنامه معمول را اعمال می‌کند. بازه بدون منبع در اطلاع‌رسانی «برنامه‌ای اعلام نشده است» است؛ تعطیلی رسمی از علت داخلی تعطیلی حدس زده نمی‌شود. منبع معتبر تعطیلات رسمی در مدل جاری وجود ندارد و تعیین آن دستی است.

رزرو واقعی از `BookingService::create/slot`، قفل‌های `BookingLocks` و `AvailabilityService::candidate/days` می‌گذرد. `AvailabilityService` فقط `ScheduleService` و reader نوبت‌ها و `SlotCalculator` را می‌خواند. هیچ‌کدام از این کلاس‌ها برای این قابلیت تغییر نکرده‌اند. اطلاعات بیمار، امور مالی و نوبت‌ها وابستگی به جدول اطلاع‌رسانی ندارند.

احراز هویت و Gate موجود `schedules.manage` به کار رفته است؛ طبق RoleSeeder، مدیر و منشی دارای این مجوز هستند. نقش، مجوز عمومی یا دسترسی رزرو تازه‌ای اضافه نشده است. Routeهای تغییردهنده session/CSRF و FormRequest دارند. متن عمومی با خروجی escaped Blade نمایش داده می‌شود. دلیل خصوصی استثناهای برنامه کاری وارد خروجی عمومی نمی‌شود.

## داده و همگام‌سازی یک‌طرفه

Migration مستقل `2026_10_29_000001_create_weekly_announcements` فقط یک جدول اضافه می‌کند:

| ستون | وظیفه |
| --- | --- |
| `date` | تاریخ میلادی روز محلی، یکتا؛ مطابق تاریخ‌های استثنای موجود |
| `source_snapshot` | وضعیت و ساعت‌های نمایشی منبع در آخرین ذخیره آگاهانه |
| `presence_override` | `null` یعنی پیروی زنده از منبع؛ در حالت دستی شامل status و آرایه بازه‌های start/end/label است |
| `location`, `outside_office`, `notes` | اطلاعات تکمیلی مستقل روز؛ محل جدید متن آزاد و امن است |
| `version`, timestamps | تشخیص ویرایش قدیمی و زمان تغییر |

وضعیت‌های منبع `working / closed / unknown` هستند؛ حضور دستی `working / closed / official_holiday` دارد. ساعت و وضعیت یک گروه قابل همگام‌سازی هستند. محل و توضیحات همیشه مستقل‌اند. ویرایش صرفاً محل با حالت «پیروی از برنامه کاری» باعث ثابت‌شدن ساعت‌ها نمی‌شود.

همگام‌سازی، خواندن زنده در زمان نمایش است؛ job یا نوشتن هنگام GET ندارد. بازخوانی چندباره رکورد تکراری نمی‌سازد. خواندن هفت روز حداکثر شش query دارد و query به ازای هر روز ایجاد نمی‌کند. تمام شیفت‌های منبع نمایش داده می‌شوند؛ فرم دستی تا هشت بازه را مانند محدودیت برنامه کاری می‌پذیرد.

در حالت دستی، تغییر/حذف منبع حضور دستی را حفظ می‌کند و تفاوت با snapshot به‌صورت هشدار در پنل مشخص می‌شود. ذخیره مجدد، اطلاعات جاری منبع را به‌عنوان وضعیت بررسی‌شده ثبت می‌کند. انتخاب «پیروی از برنامه کاری / بازگرداندن حضور به منبع» و ذخیره، فقط override حضور را پاک می‌کند؛ محل و توضیحات مطابق مقادیر فرم باقی می‌مانند. حذف منبع برای روز تابع منبع، وضعیت `unknown` می‌دهد. حذف رکورد اطلاع‌رسانی فقط آن اطلاع‌رسانی را حذف می‌کند؛ هیچ FK آبشاری به نوبت/برنامه کاری وجود ندارد.

ذخیره در transaction، درج یکتای تاریخ و قفل سطر انجام می‌شود. `expected_version` ویرایش قدیمی را رد می‌کند؛ تغییر موفق با AuditService موجود ثبت می‌شود. تنظیمات از JSON موجود `website_theme_settings.brand_settings` و قفل/نسخه موجود ThemeService استفاده می‌کنند؛ جدول موازی تنظیمات ساخته نشده است.

تاریخ ورودی و خروجی با `JalaliDate` موجود و ساعت‌ها به وقت تهران است. روز هفته از تاریخ واقعی محاسبه می‌شود؛ هفته شنبه تا جمعه است. ارقام فارسی/عربی تاریخ و ساعت در سرور normalize می‌شوند. بازه‌های ناقص، هم‌پوشان و عبورکننده از نیمه‌شب رد می‌شوند، مطابق برنامه کاری موجود؛ پایان `24:00` مجاز است. بازه عبوری باید در دو روز وارد شود. تعطیلی، ساعت‌های قبلی را از override حذف می‌کند و محل ذخیره‌شده در روز تعطیل به‌عنوان محل حضور نمایش داده نمی‌شود.

## Routeها و رابط

| Method | Route | دسترسی |
| --- | --- | --- |
| GET | `/management/weekly-schedule?week=1405-08-03` | auth + schedules.manage؛ تاریخ انتخابی شمسی |
| PUT | `/management/weekly-schedule/day` | همان مجوز، CSRF، validation و version |
| PUT | `/management/weekly-schedule/settings` | همان مجوز، CSRF و نسخه تنظیمات |
| GET | `/weekly-schedule` | عمومی؛ وقتی نمایش خاموش است 404 |

آیتم مدیریت کنار «برنامه کاری» در `panel-navigation`، با component و آیکون موجود است. صفحه مدیریت هفت کارت، هفته قبل/بعد/جاری، انتخاب تاریخ شمسی، اطلاعات منبع، نشان دستی و هشدار تغییر منبع دارد. هر روز فرم مستقل دارد؛ خطا فقط فرم مربوط را باز می‌کند و ورودی آن حفظ می‌شود. JavaScript فقط نمایش/غیرفعال‌سازی ساعت‌ها و نشان ذخیره را بهبود می‌دهد؛ بدون JavaScript نیز validation و ذخیره سرور معتبرند.

`show_weekly_schedule` پیش‌فرض false و مستقل از سایر منوهاست. آیتم کنترل‌شده در `public-navigation` با آن ظاهر می‌شود؛ لینک سفارشی دقیقاً همین مسیر در هدر دوباره نمایش داده نمی‌شود و سوییچ را دور نمی‌زند. فعال‌بودن فقط صفحه اطلاع‌رسانی را باز می‌کند. `weekly_schedule_notes` اختیاری است؛ مقدار خالی هیچ کادر/عنوانی تولید نمی‌کند و مقدار موجود فقط متن escaped است.

صفحه عمومی کارت‌های RTL، تاریخ شمسی، روز جاری با متن «امروز» و aria-current، ساعت و آیکون، محل، برچسب خارج از مطب و وضعیت‌های مجزای تعطیلی دارد. رنگ‌ها از توکن‌های فعلی پروژه استفاده می‌کنند؛ وضعیت‌ها متنی نیز هستند. تصویر مرجع صرفاً برای ساختار کارت، ساعت و محل استفاده شد؛ متن، ساعات و اطلاعات تصویر وارد DB واقعی نشده‌اند.

## فایل‌ها

| فایل | مسئولیت |
| --- | --- |
| `database/migrations/2026_10_29_000001_create_weekly_announcements.php` | جدول مستقل و rollback محدود به آن |
| `app/Modules/Scheduling/Infrastructure/WeeklyAnnouncement.php` | مدل با fillable محدود و castهای JSON/boolean |
| `app/Modules/Scheduling/Application/WeeklyAnnouncementService.php` | projection زنده، override، snapshot، هشدار اختلاف، ذخیره اتمیک و audit |
| `app/Http/Requests/WeeklyAnnouncementRequest.php` | مجوز، قواعد فرم روز/تنظیمات/تاریخ، normalize و خطاهای فارسی |
| `app/Http/Controllers/Admin/WeeklyAnnouncementController.php` | نمایش مدیریت، ذخیره روز و تنظیمات |
| `app/Http/Controllers/Web/WeeklyAnnouncementController.php` | کنترل فعال‌بودن و نمایش هفته جاری |
| `app/Modules/Website/Application/ThemeService.php` | دو کلید پیش‌فرض مستقل در تنظیمات موجود |
| `routes/web.php` | چهار Route فوق |
| `config/ui.php` | عنوان صحیح مسیر در breadcrumb پنل |
| `resources/views/layouts/panel-navigation.blade.php` | آیتم مدیریت همراه با Gate و حالت فعال |
| `resources/views/website/partials/header.blade.php` | منوی عمومی وابسته به تنظیم، جلوگیری از تکرار |
| `resources/views/admin/weekly-announcements.blade.php` | کارت‌ها، فرم‌های روزانه و تنظیمات |
| `resources/views/website/weekly-schedule.blade.php` | صفحه عمومی با حالات تعطیلی/امروز/نبود داده |
| `resources/views/components/weekly-presence.blade.php` | نمایش مشترک وضعیت و چند بازه حضور |
| `resources/views/components/ui-icon.blade.php` | افزودن دو آیکون clock و pin به سیستم محلی |
| `public/assets/weekly-schedule.css` | کارت واکنش‌گرا و حالات متنی/بصری |
| `public/assets/weekly-schedule.js` | بهبود فرم بدون جایگزینی validation سرور |
| `tests/Feature/WeeklyAnnouncementTest.php` | ۱۴ سناریو رفتار، امنیت، تاریخ، migration و عدم تداخل |
| `tests/Integration/WeeklyAnnouncementConcurrencyTest.php` | رقابت دو ویرایشگر برای اولین ذخیره |
| `tests/Support/weekly-announcement-worker.php` | worker مستقل آزمون MySQL |
| `tests/Support/weekly-announcement-browser-seed.php` | توسعه fixture محافظت‌شده موجود، صرفاً DB ساختگی |
| `tests/Browser/weekly-schedule.mjs` | آزمون مرورگر و screenshot موبایل/دسکتاپ |
| `tests/Feature/P3MigrationTest.php` تا `P7MigrationTest.php` | یک rollback اضافه برای عبور از migration جدید؛ تمام assertions قبلی حفظ شدند |
| `tests/Feature/WebsiteUnifiedSettingsTest.php` | مرز جدید rollback و assertion حذف فقط جدول اطلاع‌رسانی |
| `PRD.md`, `ARCHITECTURE.md`, همین سند | قرارداد و پذیرش قابلیت |

## اجرا و بازگشت

در محیط مقصد با PHP 8.4 و افزونه‌های نصب‌شده پروژه، پس از پشتیبان‌گیری طبق رویه فعلی:

```sh
php artisan migrate --force
php artisan view:clear
```

به seed مجدد، تغییر `.env`، نصب وابستگی، job همگام‌سازی یا build نیاز نیست. نمایش عمومی را از صفحه مدیریت روشن کنید. ساعت/محل واقعی را مسئول مطب بررسی و ثبت می‌کند؛ برنامه هفتگی به‌خودی‌خود مجوز رزرو نیست.

برای rollback همین انتشار، تنها اگر این migration آخرین migration است، `php artisan migrate:rollback --step=1 --force` جدول اطلاع‌رسانی و داده‌های دستی آن را حذف می‌کند. ابتدا از آن پشتیبان بگیرید و کد نسخه قبلی را بازگردانید. کلیدهای تنظیم موجود بی‌اثر باقی می‌مانند. rollback برنامه کاری، رزرو، بیمار و داده مالی را حذف نمی‌کند؛ این رفتار با تست بررسی شده است.

هیچ migration روی DB اصلی کاربر یا محیط تولید اجرا نشد. آزمون‌ها از SQLite حافظه‌ای، DB مستقل MySQL و فایل SQLite محافظت‌شده مرورگر استفاده کردند. فایل‌های runtime، `.env`، vendor، تصاویر آزمون و لاگ‌ها وارد commit نمی‌شوند.

## شواهد اعتبارسنجی

فرمان‌ها از PHP 8.4.27 محلی و ابزارهای نصب‌شده همین پروژه استفاده می‌کنند؛ PHP پیش‌فرض میزبان 8.3 بود و برای تست مناسب نبود. محدودیت تشخیص `is_readable` در sandbox ویندوز با اجرای مجاز تست خارج از sandbox رفع شد، بدون تغییر vendor یا ضعیف‌کردن تست.

| بررسی اجراشده | نتیجه واقعی |
| --- | --- |
| نسخه پایه دست‌نخورده `0676180`، استخراج از Git با وابستگی‌های یکسان؛ `php vendor/phpunit/phpunit/phpunit` | موفق: ۳۰۶ تست، ۲۲۴۷ assertion |
| مجموعه نهایی Unit/Feature؛ همان فرمان در شاخه قابلیت | موفق: ۳۲۰ تست، ۲۳۸۵ assertion |
| تست اختصاصی قابلیت روی SQLite؛ `php vendor/phpunit/phpunit/phpunit tests/Feature/WeeklyAnnouncementTest.php` | موفق: ۱۴ تست، ۱۳۱ assertion |
| مجموعه Integration موجود روی MySQL 8.4.3؛ `php vendor/phpunit/phpunit/phpunit -c phpunit.mysql.xml` | موفق: ۴۹ تست، ۳۵۳ assertion؛ آغاز این اجرا پیش از اضافه‌شدن فایل race جدید بود |
| قابلیت و race جدید روی MySQL مستقل؛ `php vendor/phpunit/phpunit/phpunit -c phpunit.mysql.xml tests/Feature/WeeklyAnnouncementTest.php tests/Integration/WeeklyAnnouncementConcurrencyTest.php` | موفق: ۱۵ تست، ۱۴۱ assertion |
| `php vendor/bin/pint --test` | موفق برای کل پروژه |
| `php artisan route:list --path=weekly-schedule` | موفق؛ چهار Route مورد انتظار |
| `php artisan view:cache` | موفق؛ تمام Bladeها کامپایل شدند |
| `git diff --check` | موفق |
| `node tests/Browser/weekly-schedule.mjs` با Chrome محلی و Playwright موجود | موفق؛ شش ترکیب مسیر/عرض، فرم، خطا/ذخیره، محل، روز جاری، منوی موبایل، مجوزها و نبود خطای JS/Asset/CSP |

آزمون مرورگر در عرض‌های ۳۲۰، ۷۶۸ و ۱۴۴۰ پیکسل، با screenshot و بازبینی بصری صفحات عمومی موبایل و مدیریت دسکتاپ انجام شد. کارت‌ها بدون اسکرول افقی‌اند. آزمون صفحه‌کلید Escape برای منوی عمومی انجام شد. تصویر خروجی و DB fixture صرفاً ساختگی و خارج از commit هستند. آزمون روی دستگاه فیزیکی و ارزیابی کامل screen reader/بزرگ‌نمایی ۲۰۰٪ انجام نشده است.

اجرای اولیه پیش از آماده‌سازی محیط، با PHP 8.3 و سپس افزونه‌های غیرفعال/تشخیص دسترسی sandbox متوقف شد؛ این اجراها موفق شمرده نشده‌اند. یک اجرای میانی پس از افزودن migration، شش شکست مرز rollback با شمارش ثابت داشت؛ همان شش تست با عبور از migration تازه اصلاح شدند و همه assertions حفظ شدند. خطای تست درگاه در همان اجرای میانی از تنظیم‌نبودن `zend.exception_ignore_args=On` محیط بود؛ پیکربندی PHP محلی موجود پروژه اعمال شد. نسخه پایه واقعی و نتیجه نهایی هر دو با همان تنظیمات مناسب موفق‌اند؛ شکست باقی‌مانده‌ای گزارش نشده است.

شواهد عدم تداخل شامل مقایسه دقیق داده/نسخه برنامه کاری، خروجی availability و attributes نوبت موجود پیش/پس از تغییر اطلاع‌رسانی است. ثبت حضور روستا در یکشنبه تعطیل هنوز هیچ slot ایجاد نمی‌کند و درخواست واقعی رزرو 409 می‌گیرد. تغییر ساعت اطلاع‌رسانی، خاموش‌کردن نمایش و حذف رکورد اطلاع‌رسانی، ظرفیت یا نوبت موجود را تغییر نمی‌دهد. تست‌های هم‌زمانی رزرو، مالی و برنامه کاری قبلی نیز روی MySQL موفق‌اند.

محدودیت استقرار: روی هاست تولید deploy یا migration انجام نشده و branch به GitHub push نشده است. فعال‌کردن نمایش و ثبت محل/توضیحات واقعی به مسئول مجاز مطب واگذار می‌شود. Rollback جدول اطلاع‌رسانی داده‌های دستی همان جدول را حذف می‌کند، پس نسخه پشتیبان لازم است. برای اجرای محلی از PHP 8.4 و تنظیمات افزونه درست استفاده کنید؛ `.env` و کلید اصلی برنامه در این کار تغییر نکردند.
