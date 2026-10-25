# DEPLOYMENT — راهنمای خروجی GitHub Actions و نصب روی هاست
**نسخه:** 1.0.0 · **مخزن:** https://github.com/anishopmobile/ani-doctor

## 1. وضعیت کنونی و محدودیت صریح
مخزن ابتدا خالی بوده و در فاز P0 تنها اسناد و workflow قرار گرفته است. **تا زمانی که فایل‌های اصلی اپلیکیشن Laravel وجود نداشته باشند، هیچ ZIP نصب سایت واقعی ساخته نمی‌شود.** GitHub Actions در حالت docs-only صحت وجود اسناد را بررسی و با پیام «Package skipped» تمام می‌شود. پس از تکمیل اسکلت برنامه در P1، همان workflow به‌طور خودکار بسته نصب تولید می‌کند.

PHP برخلاف JS یک «compile» اجباری ندارد؛ ساخت خروجی در این پروژه به معنی نصب وابستگی‌های Composer، اجرای آزمون‌ها، تهیه `vendor/` و بسته‌بندی فایل‌های لازم است. فایل‌های Bootstrap/Tabler/datepicker باید به‌صورت نسخه آماده و با مجوز معتبر در `public/assets` ثبت شوند تا npm/Vite/Node روی هاست لازم نباشد.

## 2. GitHub Actions چگونه اجرا می‌شود؟
فایل workflow: [`.github/workflows/release-package.yml`](.github/workflows/release-package.yml)

Triggerها: هر `push` به شاخه‌ها و اجرای دستی از `Actions → Host-ready package → Run workflow`. پس از ایجاد app و موفقیت CI، به همان run بروید و از بخش **Artifacts** فایل `ani-doctor-host-ready-<run number>` را دریافت کنید. داخل artifact یک ZIP مانند `ani-doctor-<short-sha>.zip` وجود دارد.

دسترسی به artifact معمولاً به مجوزهای repository/Actions بستگی دارد. ZIP خروجی را در فضای خصوصی مدیریت کنید؛ این ZIP نباید اطلاعات کاربر/دیتابیس واقعی داشته باشد.

## 3. پیش‌نیاز فعال‌شدن بسته‌بندی
در ریشه مخزن باید حداقل این موارد موجود باشند:

- `composer.json`
- `composer.lock` نسخه‌بندی‌شده
- `artisan`
- `public/index.php`

پروژه باید با PHP 8.4 و Laravel 13 سازگار باشد. CI اگر بخشی از اسکلت موجود ولی ناقص باشد، عمداً fail می‌شود و بسته غیرقابل نصب نمی‌سازد.

**شرط assets:** صفحات باید از فایل‌های static prebuilt `public/assets` استفاده کنند. اگر صفحات Blade به `@vite` وابسته باشند، CI شکست می‌خورد تا اشتباهاً ZIP دارای Frontend ناقص تولید نشود.

## 4. محتوای ZIP
- پوشه‌های `app`, `bootstrap`, `config`, `database`, `lang`, `public`, `resources`, `routes` (اگر موجود باشند)
- فایل‌های `artisan`, `composer.json`, `composer.lock`
- پوشه کامل `vendor/` برای production (بدون dev dependency)
- پوشه‌های خالی لازم برای storage/cache که در زمان نصب writable می‌شوند
- فایل متنی راهنمای استقرار

**عمداً خارج از ZIP:** `.env` و گونه‌های آن، secretها، private keyها، تست‌ها، `.git`، داده runtime، فایل پزشکی، dump دیتابیس و فایل‌های توسعه. اگر فایل بالقوه محرمانه در پکیج شناسایی شود، job باید fail کند.

## 5. الزامات هاست
قبل از خرید هاست بررسی شود:
- PHP 8.4 (سازگار با Laravel 13، حداقل نسخه 8.3) و extensionهای لازم مثل mbstring, openssl, PDO MySQL, xml, curl, fileinfo, tokenizer, ctype, dom, session, zip؛
- MySQL/MariaDB سازگار، InnoDB، دیتابیس و کاربر با حداقل مجوز مورد نیاز؛
- SSL/TLS معتبر و امکان HTTPS اجباری؛
- امکان تنظیم DocumentRoot دامنه/ساب‌دامنه روی پوشه `public`؛
- دسترسی مدیریت فایل خارج `public_html`، Cron و دسترسی لازم برای اجرای `php artisan` حداقل در راه‌اندازی؛
- `storage` و `bootstrap/cache` writable باشند؛
- queue worker بلندمدت یا راهکار Cron جایگزین برای SMS، انقضای نوبت‌ها و reconcile.

**مهم:** اگر هاست اجازه قرار دادن اپ بیرون DocumentRoot و تنظیم DocumentRoot=public را نمی‌دهد، آن هاست برای نصب مستقیم این ZIP مناسب نیست. اپ را صرفاً برای ساده‌ترشدن نصب در `public_html` رها نکنید؛ در صورت اجبار، طراحی layout جدا/بازنویسی مسیر امن و تست حفاظتی مستقل لازم است.

## 6. روش نصب نسخه کامل روی هاست (پس از P1)
1. ZIP را از GitHub Actions دریافت و محتوای آن را در پوشه خصوصی مثل `/home/account/ani-doctor` استخراج کنید.
2. DocumentRoot سایت را به `/home/account/ani-doctor/public` اشاره دهید.
3. دیتابیس و کاربر کم‌دسترسی بسازید.
4. فایل `.env` را **روی هاست**، خارج از public، براساس کلیدهای `.env.example` نسخه پروژه بسازید؛ APP_ENV=production و APP_DEBUG=false؛ نام/رمز دیتابیس، پیامک، درگاه و APP_URL حقیقی را فقط اینجا وارد کنید.
5. `APP_KEY` را روی سرور یک‌بار با `php artisan key:generate` تولید کنید و امن نگه دارید. **در هر deployment بعدی APP_KEY را عوض نکنید.**
6. مالکیت/مجوز نوشتن `storage` و `bootstrap/cache` را اصلاح کنید؛ بدون permission 777 عمومی.
7. ابتدا بکاپ؛ سپس migration با `php artisan migrate --force` در نسخه‌ای که migrationهای واقعی و بررسی‌شده دارد.
8. cache را با پیکربندی واقعی سرور ایجاد کنید: `php artisan optimize:clear` و سپس `php artisan optimize`؛ کش تنظیمات پیش‌ساخته از CI داخل ZIP نباید وجود داشته باشد.
9. Cron Scheduler را هر دقیقه اجرا کنید: `* * * * * cd /home/account/ani-doctor && /usr/bin/php artisan schedule:run >> /dev/null 2>&1` (مسیر PHP در هاست بررسی شود). Worker صف متناسب با امکانات هاست تنظیم شود.
10. HTTPS، صفحه ورود، تقویم، مجوز مدیر/منشی، رزرو sandbox، SMS و PDF را بررسی کنید و بعد از آن تراکنش واقعی را فعال کنید.

## 7. روش نصب نسخه جدید
- از دیتابیس و storage خصوصی نسخه پشتیبان قابل بازیابی تهیه کنید.
- نسخه جدید ZIP را در مسیر release جدا استخراج و سلامتی آن را بررسی کنید؛ secret و storage runtime قبلی را به‌صورت امن استفاده مجدد کنید.
- زمان کوتاه maintenance متناسب با تغییرات و migration برنامه‌ریزی کنید؛ migration را کنترل‌شده اجرا کنید.
- هرگز `.env` تولیدی یا تصاویر/فایل‌های بیماران را با ZIP تازه overwrite نکنید.
- symlink/current release در صورت قابلیت هاست برای rollback امن مناسب است.
- نسخه قدیمی و برنامه بازگشت قبل از فعال‌سازی نگهداری شود؛ تغییرات DB ناسازگار باید راهبرد بازگشت جدا داشته باشند.

## 8. محدودیت‌های CI و مسائل امنیتی
- Actions خروجی **فقط بسته نصب** می‌سازد؛ خودش سرور را تغییر نمی‌دهد و استقرار آنلاین خودکار انجام نمی‌شود.
- GitHub artifact شامل `vendor` است؛ باید مرتب dependencyها و هشدارهای امنیتی بررسی شوند.
- اگر هاست محدودیت Cron/queue یا PHP extension دارد، قبل از توسعه فاز P1 باید تطابق تأیید شود.
- دسترسی public GitHub به سورس و مستندات به‌معنای مجازبودن قرار دادن secrets/داده واقعی در repo نیست.
- برای امنیت بیشتر نسخه actionها بعداً به commit SHA تأییدشده pin شوند.

## 9. ساخت ChatGPT Project برای استفاده از اسناد
1. در ChatGPT از نوار کناری **New project** را انتخاب و نام `Ani Doctor — Development` بدهید.
2. فایل‌های `AI_RULES.md`, `PRD.md`, `ARCHITECTURE.md`, `UI_GUIDELINES.md`, `COMPONENTS.md`, `SECURITY.md` را از GitHub دانلود و به Project Files اضافه کنید؛ `DEPLOYMENT.md` را نیز بهتر است ضمیمه کنید.
3. در قسمت **Project instructions** بنویسید: «قبل از پاسخ/کدنویسی فایل AI_RULES.md و سپس PRD.md, ARCHITECTURE.md, COMPONENTS.md, UI_GUIDELINES.md, SECURITY.md را مبنا قرار بده. تنها در محدوده فاز اعلام‌شده کار کن. مخزن اصلی: anishopmobile/ani-doctor. هیچ تست اجرا‌نشده یا deployment انجام‌نشده‌ای را موفق اعلام نکن.»
4. اگر GitHub به ChatGPT متصل است، کار روی مخزن و PRها را با همان اتصال پیش ببرید؛ فایل‌های Project پس از هر تغییر مهم باید با نسخه repo هماهنگ شوند.
5. برای هر مرحله یک گفت‌وگوی جدا در Project ایجاد کنید، مثلاً «P1 — Foundation / Identity»؛ نام فاز و معیار پذیرش را در درخواست بنویسید.
6. هنگام شروع P1 مخزن و وضعیت Actions را بخوانید؛ پس از commit، تب Actions را برای نتیجه تست و artifact بررسی کنید.

## 10. پیوندهای فنی
- https://laravel.com/docs/13.x/deployment
- https://docs.github.com/en/actions
- https://github.com/actions/upload-artifact
- https://github.com/shivammathur/setup-php
