# SECURITY — سیاست امنیت و حفاظت داده‌های آنی دکتر
**نسخه:** 1.0.0 · **سطح:** الزامی برای همه فازها

> این سند راهنمای پیاده‌سازی امنیت است، نه گواهی امنیت سامانه. تا پایان تست نفوذ، ارزیابی حریم خصوصی و بررسی قوانین حوزه فعالیت، نباید ادعای انطباق رسمی یا امنیت کامل شود.

## 1. دامنه و تهدیدها
سامانه اطلاعات تماس بیماران، تاریخ مراجعه، هزینه و احتمالاً پاسخ‌های فرم‌های پزشکی را نگهداری می‌کند. مخاطرات اصلی: تصاحب OTP، جستجوی نامحسوس پرونده دیگران (IDOR)، افشای رزرو/وضعیت سلامت، رزرو رقابتی و ظرفیت اضافی، جعل نتیجه پرداخت، استرداد دوباره، دستکاری اعتبار کیف پول، حمله به فرم‌ساز/فایل، افشای اطلاعات در پشتیبان/ZIP و نقش پرقدرت منشی.

## 2. اصول پایه (least privilege + deny by default)
- هر درخواست به داده شخصی باید کاربر و مجوز **همان داده** را بررسی کند؛ نام/موبایل/ID صرفاً locator است نه مجوز.
- Policy/Gate به تفکیک `patient`, `booker`, `secretary`, `admin` و privilegeهای دقیق. جدا بودن حساب و بیمار اجبار است.
- هیچ endpoint مدیریت با داشتن URL پنهان امن نمی‌شود.
- منشی فقط مجوزهای عملیاتی؛ تغییر درگاه، کلید، تنظیم حالت اضطراری پرداخت، دسترسی گسترده به پاسخ پزشکی، تنظیم نقش‌ها = مدیر/role ویژه.
- override نوبت و لغو خارج مهلت همراه علت اجباری و audit با user ID و timestamp.
- دسترسی گزارش مالی و پاسخ فرم پزشکی جدا از فهرست نوبت‌ها کنترل شود.

## 3. OTP و مدیریت نشست
استثنای صریح توسعه محلی به درخواست کاربر: LOCAL_OTP_PREVIEW=true فقط همراه APP_ENV=local، APP_URL و Host محلی و REMOTE_ADDR واقعی 127.0.0.1/::1 مجاز است. پیامک OTP ارسال نمی‌شود و کد تصادفی فقط یک بار در flash نشست خود درخواست‌کننده و صفحه ورود private/no-store نمایش می‌یابد؛ challenge فقط hash دارد. در production/staging یا اتصال غیرمحلی، حتی با روشن‌بودن گزینه، SMS واقعی اجرا و کد مخفی می‌ماند. انقضا، تلاش محدود، مصرف یک‌باره و ACL برقرارند. این حالت تأیید مالکیت شماره واقعی نیست؛ فقط داده ساختگی و بدون تونل/پراکسی عمومی. پیش از انتقال خاموش و نشست آزمایشی خارج شود. راهنما: [نصب لوکال](docs/INSTALL-LOCAL.md).
کنترل‌های اجرایی P1 در [docs/P1.md](docs/P1.md) و شواهد آزمون در [docs/P1-VALIDATION.md](docs/P1-VALIDATION.md) ثبت می‌شوند؛ وجود کد تأیید انتشار عمومی نیست.

- OTP فقط برای موبایل معتبر ایران، با انقضای کوتاه (پیشنهادی ۲ دقیقه)، محدودیت تلاش (مثلاً ۵ بار برای هر OTP)، resend cooldown (مثلاً ۶۰ ثانیه) و سقف درخواست IP/موبایل.
- OTP تصادفی امن، حداقل ۶ رقم، نگهداری فقط hash و مدت انقضا؛ پس از موفقیت یا اتمام تلاش یک‌بارمصرف است.
- rate limit سمت backend و پاسخ خطای جلوگیری‌کننده از enumeration؛ provider downtime ثبت شود اما کد در log قرار نگیرد.
- session cookie: HttpOnly، Secure، SameSite متناسب با جریان پرداخت؛ regenerate session پس از ورود؛ logout همه نشست‌ها در صورت تغییر حساس حساب.
- برای مدیر و منشی 2FA و محدودیت نشست توصیه می‌شود. ریسک CSRF در callback درگاه با route استثنا و بررسی امضا/verify سمت سرور مدیریت شود.
- Guest claim: پیوند نوبت مهمان با OTP شماره رزروکننده و توکن یک‌بارمصرف/قواعد مالکیت امن، نه صرفاً شماره واردشده.

## 4. امنیت رزرو هم‌زمان
در شاخه P3، reader و مالکیت واقعی و قفل روز/بیمار/شماره پیاده شده‌اند؛ نتیجه تست‌های چندپردازه و حدود اثبات در [گزارش P3](docs/P3-VALIDATION.md) است. این شواهد گواهی امنیت یا تأیید پرداخت P4 نیستند.

P2 فقط preview و مدیریت برنامه دارد؛ قفل استثناها و هشدار نوبت متأثر با قرارداد reader تست شده‌اند. اتصال به Booking و اتمیک‌بودن با رزرو واقعی باید در P3 برقرار و آزمایش شود. [جزئیات P2](docs/P2.md).

- همه availabilityها فقط پیش‌نمایش هستند؛ هنگام ثبت، قفل ردیف روزانه/منبع و DB transaction با بازخوانی داده.
- hold معتبر با انقضای مطلق از سمت سرور؛ هیچ اعتماد به countdown کلاینت.
- فرآیند تغییر نوبت اتمیک؛ بدون آزادسازی قبل از اخذ ظرفیت جدید.
- create/edit/cancel به idempotency key و قواعد انتقال مجاز نیاز دارند؛ replay و درخواست موازی تست شوند.
- override ظرفیت به صورت صریح، با دلیل، شناسه منشی/مدیر، و بدون تغییر quota اینترنتی.
- تغییر زمان، خدمت یا قیمت بعد از آغاز پرداخت فقط با نسخه‌بندی quote و تشخیص stale request.

## 5. امنیت پرداخت و کیف اعتبار
استثنای توسعه محلی: LOCAL_PAYMENT_SIMULATOR=true فقط APP_ENV=local با APP_URL/Host حلقه محلی و REMOTE_ADDR واقعی loopback مجاز است؛ forwarded جعلی آن را فعال نمی‌کند. checkout همان نشست با nonce خصوصی، POST محافظت‌شده CSRF، تصمیم نخست زیر cache lock، مبلغ/مرجع/callback فقط سمت سرور. Status=OK بدون تصمیم سمت سرور اثبات پرداخت نیست. provider تلاش local_simulator پایدار است تا تغییر flag درگاه واقعی را محلی verify نکند؛ رسید نیز provider مستقل و ارجاع LOCAL- دارد. انقضا، idempotency، قفل تسویه، مغایرت و ACL اصلی حفظ می‌شوند. خاموش‌شدن گزینه یا production، verify شبیه‌سازی را می‌بندد. cache گم‌شده = نتیجه نامشخص، نه موفق. فقط داده ساختگی؛ جمع‌های مالی این DB شامل اعتبار ساختگی‌اند و با هشدار در خروجی مشخص می‌شوند. هیچ داده/نشست/DB آزمایشی به تولید منتقل نشود؛ تونل/پراکسی عمومی مجاز نیست. این ابزار آزمون بانک/امنیت پرداخت واقعی نیست.
کنترل‌های اجرایی P4، استثنای محدود CSRF callback، خطاهای مالی بدون SQL/secret، snapshot رمزگذاری‌شده merchant، current read، قیود رسید/ledger و مجوز مدیر/منشی در [docs/P4.md](docs/P4.md) و شواهد آزمون در [P4-VALIDATION](docs/P4-VALIDATION.md) هستند. وضعیت مالی `credited` برای برگشت لغو استفاده می‌شود؛ مغایرت در جدول مستقل است. حساب/اعتبار با HMAC شماره رزروکننده مرتبط است و APP_KEY باید پایدار و محفوظ بماند. callback query نباید در access log/analytics ثبت شود. استرداد بانکی دستی نیازمند تطبیق سند واقعی و مالکیت مقصد در فرایند امن مطب است؛ ثبت سند، تأیید خودکار بانک نیست.
- کل مبلغ باید پیش از تأیید آنلاین پرداخت شود؛ فقط fallback فعال مدیر اجازه confirmed-unpaid می‌دهد.
- gateway token و Merchant ID محرمانه؛ callback/redirect کاربر **دلیل قطعی پرداخت نیست**. verify و query سمت backend انجام شود.
- هر provider reference باید unique و idempotent باشد؛ attempt/transaction برای retry مجزا ولی به یک رزرو مرتبط است.
- verify موفق پس از expiry و فروش مجدد = `reconciliation_required`; کاربر نباید دو رزرو یا دو اعتبار دریافت کند.
- ledger اعتبار append-only و دارای unique business reference؛ استرداد، لغو و مصرف با transaction و audit انجام شوند.
- حق ایجاد/ویرایش دستی موجودی برای منشی وجود ندارد؛ اصلاح ledger تنها با entry اصلاحی و مجوز مدیر.
- emergency pay-at-clinic: فقط مدیر با ثبت دلیل و مدت فعال‌سازی؛ نمایش هشدار پنل؛ تمام نوبت‌های این حالت با channel و balance_due تفکیک شوند.
- دریافت حضوری: ثبت کننده منشی، مبلغ، تاریخ، روش دریافت، شماره رسید اختیاری، رویداد audit. ثبت دریافت مجدد یک بدهی باید مسدود باشد.
- secrets یا پاسخ‌های خام درگاه به‌صورت بی‌پالایش در log ذخیره نشوند.

## 6. فرم‌ها و داده درمانی حساس
- نسخه اول شامل **اطلاعات پایه و ضرورت‌های نوبت‌دهی** است؛ پرونده درمانی کامل مجاز نیست مگر با تغییر PRD، ارزیابی امنیت و کنترل قانون.
- داده‌های حساس مثل بارداری، ناباروری، تشخیص/آزمایش فقط در صورت ضرورت مشخص، رضایت/اطلاع‌رسانی مناسب و حداقل مجوز جمع‌آوری شوند.
- پاسخ فرم حساس رمزگذاری در حالت سکون با کلید خارج از repo و کنترل کلید؛ درخواست/نمایش‌ها ثبت و قابل ممیزی باشند.
- نمایش پاسخ حساس در SMS، push عمومی، URL، عنوان export و analytics ممنوع.
- فیلدهای متن با output escaping پیش‌فرض Blade؛ ورودی HTML فرم‌ساز تنها با sanitize صریح و whitelist محدود.
- آپلود فایل فقط در صورت نیاز عملی و پس از بررسی محرمانگی؛ type با MIME واقعی، size، پسوند مجاز، اسکن بدافزار در صورت قابلیت، نام تصادفی و محل private خارج public.
- فایل خروجی قابل‌دانلود با authorization، محدودیت عمر و حذف زمان‌بندی‌شده؛ نام فایل بدون نام/کد ملی بیمار.

## 7. حفاظت وب و API
- HTTPS اجباری + HSTS پس از اطمینان از HTTPS کامل.
- CSRF برای cookie/session mutation؛ CORS بسته پیش‌فرض؛ CSP، `X-Content-Type-Options: nosniff`, محدودیت embed و referrer policy.
- ORM/parameter binding در query؛ هر SQL خام فقط parameterized.
- XSS: escape در Blade، حذف `innerHTML` برای داده کاربر در JS یا sanitize با کتابخانه معتبر.
- SSRF: Provider URLs fixed/configured، عدم اجازه URL دلخواه کاربر در درخواست خارجی.
- pagination و rate limit برای جستجوها، رزرو، login، و export.
- اعتبارسنجی سروری همه ارقام فارسی/عربی، شماره موبایل، تاریخ و مبلغ؛ JSON schema برای API.

## 8. secrets و GitHub Actions
- هرگز `.env` واقعی، کلید زرین‌پال، SMS.ir، APP_KEY، token، بکاپ، داده بیماران یا فایل پزشکی در Git ثبت نشود.
- `.env.example` فقط کلید نام‌گذاری شده با placeholder غیرواقعی و بدون مشخصات بیمار.
- `composer.lock` commit شود؛ `vendor/` در Git نه، ولی در ZIP خروجی production بله.
- CI با `permissions: contents: read` و حداقل privilege؛ خروجی Artifact نباید secret/env/runtime data داشته باشد.
- در PR از forkها هیچ credential واقعی inject نشود.
- SHA و نسخه actionها/وابستگی‌ها به‌صورت دوره‌ای بازبینی و ترجیحاً pin شوند.
- هنگام تغییر secret: rotate کلید، invalidate token و بررسی log؛ history Git خودبه‌خود پاک نمی‌شود.
- گزارش امنیتی GitHub Dependabot/Composer audit و بازبینی dependencyها در روند انتشار قرار گیرد.

## 9. زیرساخت و هاست
- **DocumentRoot باید پوشه `public/` Laravel باشد**؛ قرار دادن کل ریشه اپ در `public_html` ناامن است.
- PHP >=8.4 و extensionهای Laravel 13 و composer.json پروژه؛ MySQL با دسترسی محدود؛ دسترسی نوشتن فقط `storage/` و `bootstrap/cache/`.
- مالک فایل و permission حداقلی؛ `.env` خارج public و unreadable برای سایر کاربران هاست.
- `APP_DEBUG=false` و `APP_ENV=production`؛ `APP_KEY` روی هاست یک‌بار تولید، محفوظ و قابل بازیابی در انتقال سرور.
- scheduler: `php artisan schedule:run` دقیقه‌ای؛ queue worker با Supervisor یا راهکار cron امن و کنترل concurrency متناسب با هاست.
- Migration فقط پس از بکاپ و بررسی، **نه خودکار در request یا هنگام استخراج ZIP**.
- مسیر private storage نباید با symlink در public در معرض دسترسی مستقیم قرار گیرد.
- گزارش خطا و monitoring بدون داده پزشکی/کلید، با سیاست retention.

## 10. بکاپ، retention و پاسخ حادثه
- بکاپ رمزگذاری‌شده و خارج از DocumentRoot، جدا از artifact، با دسترسی محدود.
- نمونه سیاست: بکاپ روزانه + نگهداری ۷ روز روزانه و ۴ هفته هفتگی؛ پیش از اجرای نهایی با قانون و ظرفیت هاست تنظیم شود.
- آزمون بازگردانی واقعی حداقل فصلی؛ نسخه دیتابیس و فایل‌های خصوصی هماهنگ.
- retention داده پزشکی، مالی، تراکنش و audit بر مبنای نیاز قانونی محل فعالیت تعیین شود؛ هیچ زمان قطعی قانونی بدون بررسی حقوقی ادعا نشود.
- فرآیند حادثه: شناسایی → محدودسازی دسترسی/rotate secrets → حفظ شواهد → ارزیابی دامنه → اطلاع‌رسانی متناسب با قانون → اصلاح و بازآزمایی.
- حذف حساب بیمار نباید به حذف تراکنش و سوابق مالیِ دارای الزام نگهداری منجر شود؛ anonymization با کنترل قوانین.

## 11. Security Acceptance Gates
| Gate | تست/دلیل پذیرش |
|---|---|
| SEC-01 | IDOR: بیمار A نتواند پرونده/نوبت B را بخواند یا ویرایش کند |
| SEC-02 | OTP brute-force/resend rate-limits و زمان انقضا |
| SEC-03 | دو رزرو هم‌زمان ظرفیت را نقض نکند |
| SEC-04 | تکرار callback پرداخت، اعتبار/سفارش تکراری نسازد |
| SEC-05 | پرداخت دیرهنگام نوبت فروخته‌شده را بازتخصیص ندهد |
| SEC-06 | منشی نتواند حالت پرداخت اضطراری یا تنظیمات بانکی را تغییر دهد |
| SEC-07 | همه لغوها و دریافت‌های حضوری audit داشته باشند |
| SEC-08 | فایل/گزارش خصوصی بدون auth دانلود نشود |
| SEC-09 | Artifact ZIP هیچ .env/کلید/اطلاعات خصوصی نداشته باشد |
| SEC-10 | استقرار با DocumentRoot public و APP_DEBUG=false بررسی شود |
| SEC-11 | تنظیمات تعطیلی با وجود رزروها باعث حذف ناخواسته نشود |
| SEC-12 | استرداد اعتبار برای یک لغو فقط یک بار انجام شود |

## 12. ارزیابی‌های پیش از انتشار عمومی
Threat modeling، بررسی امنیت کتابخانه‌های Third Party، تست نفوذ محدود به سامانه، تست پرداخت sandbox و قطع اتصال، تست restore، سیاست حریم خصوصی فارسی، بررسی الزام‌های حقوقی/پزشکی محل استقرار و تعهد محرمانگی منشی‌ها. این موارد شرط انتشار تولیدی هستند، نه صرفاً تزئین مستند.


## افزوده P5

P5: مجوزهای مستقل desk.view/book، patients.manage، appointments.urgent و attendance.manage؛ ثبت حضور نسخه‌دار و idempotent با audit؛ جستجوی اطلاعات بیمار POST خصوصی و بدون نام/شماره در URL؛ بدون ادغام خودکار بیماران یا اثر مالی خودکار حضور. آزمون منفی و رقابت در docs/P5-VALIDATION.md.


## افزوده P6

P6 پاسخ فرم، مقصد و پارامتر outbox با APP_KEY رمزگذاری می‌شوند؛ مجوز forms.answers.view/write از forms.manage و مشاهده نوبت جداست و منشی پیش‌فرض ندارد. پاسخ و schema در flash، SMS، URL و لاگ نمی‌روند. فرم عمومی ناشناس/فایل پزشکی وجود ندارد؛ nonce پاسخ، revision و outbox یکتا تست شده‌اند. جزئیات و محدودیت ارسال نامشخص: docs/P6.md.

## خروجی خصوصی P7
مجوز reports.finance مستقل از reports.appointments و فرم خصوصی است؛ منشی گزارش مالی ندارد. دانلود آنی حافظه‌ای با private/no-store، نام غیرحساس و rate limit؛ audit فقط فیلتر whitelist دارد. XLSX متن را صریحاً string می‌نویسد؛ فرمول و لینک از متن ورودی ساخته نمی‌شود. سقف ۱۰۰۰ ردیف/۲۵۰ PDF و ۳۶۶ روز، پاسخ 422 بدون truncation؛ جزئیات [docs/P7.md](docs/P7.md).

## P8
HTTPS/Host و نشست امن در production fail-closed، اعتماد IP پراکسی صریح، خطای بدون payload خصوصی و هدر خطا؛ بکاپ authenticated encryption با کلید مستقل و بازیابی فقط DB خالی جدا، کنترل ZIP و staging موقت. فرمت/تهدیدها و گیت‌های انجام‌نشده انتشار عمومی در [docs/P8.md](docs/P8.md)، شواهد در [docs/P8-VALIDATION.md](docs/P8-VALIDATION.md). این شواهد گواه نفوذ مستقل یا انطباق حقوقی نیستند.

## Public editorial service media and branding (2026-10-27)
Website service images are explicitly public editorial assets only after the catalog is active/online and editorial publication is enabled; never upload patient or medical-record files to this registry. Files remain on the private local disk and are proxied via publication checks or website.manage preview, with nosniff and private/no-store. New media writes use parent row locking; archiving cleans files only after commit. MIME/size/required-alt validation, CSRF and existing auth/Gate remain mandatory. Brand/contact/SEO are escaped plain text; phone is digits with optional leading plus, colors are full hex with contrast checks on updates. Existing saved palettes require review/resave; no silent overwrite. Sitemap excludes operational routes; noindex in non-production and robots.txt are indexing hints, never authorization. Back up private media bytes with the database. File cleanup failure/orphan monitoring, malware scanning and external security review remain operational considerations; no independent security certification is claimed.

## Installation/upgrade diagnostics
ops:installation-check and scripts/check-install.php return named checks and pending migration filenames only; never raw DB exceptions, SQL, credentials, key contents or private records. They do not change schema/env or generate keys. New installation and upgrade are separate procedures; keep existing APP_KEY and private files, back up before reviewed migrations. The synthetic rollback/reapply fixture lives only under tests, is not shipped, and requires the exact disposable DB, loopback HTTPS origin and disabled live providers. No changes to public exception redaction or authentication are needed to diagnose SQLSTATE 42S02.
