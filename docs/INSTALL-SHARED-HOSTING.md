# نصب تازه روی هاست اشتراکی

این مسیر برای **نصب تازه با دیتابیس خالی** است؛ اگر داده قبلی دارید، ابتدا بخش انتقال/ارتقا را بخوانید. `CPUSER`، دامنه و مسیر PHP مثال‌اند و قبل از اجرا باید با اطلاعات واقعی هاست جایگزین شوند. این پروژه installer تحت وب یا حساب پیش‌فرض ندارد.

اگر روی لوکال از نمایش کد آزمایشی استفاده کرده‌اید، پیش از انتقال از حساب خارج شوید و روی هاست `LOCAL_OTP_PREVIEW=false`، `APP_ENV=production` و مشخصات واقعی SMS.ir را تنظیم کنید. env، نشست و داده ساختگی لوکال را منتقل نکنید؛ config را پس از تنظیمات بازسازی کنید. حتی با روشن‌ماندن اشتباهی گزینه، preview در production فعال نمی‌شود.

## ۱. پیش از خرید/نصب، پشتیبانی این موارد را تأیید کند

- PHP 8.4 برای **وب، Terminal و Cron** و extensionهای راهنمای لوکال؛ gd/intl/mbstring/zip/xml به‌خصوص لازم‌اند. memory_limit دست‌کم 256M نقطه شروع پیشنهادی است، نه تضمین بار.
- **MySQL 8 واقعی + InnoDB**؛ MariaDB را به‌عنوان نتیجه تست‌شده برابر فرض نکنید.
- امکان گذاشتن کل برنامه بیرون public_html و تنظیم DocumentRoot دقیقاً روی پوشه public برنامه. تنظیم دامنه اصلی بعضی پنل‌ها فقط دست پشتیبانی است.
- HTTPS معتبر برای دامنه، rewrite/.htaccess در Apache یا تنظیم معادل تأییدشده در وب‌سرور میزبان؛ outbound HTTPS به SMS.ir و زرین‌پال با CA معتبر.
- Terminal/SSH یا اجرای امن دستورهای Artisan توسط پشتیبانی و **Cron هر دقیقه**؛ نبود Terminal به معنی استفاده از installer.php عمومی نیست.
- فضای خصوصی، مالکیت/دسترسی مناسب storage و bootstrap/cache و بکاپ قابل بازیابی خارج فضای عمومی.
- اگر بکاپ داخلی P8 لازم است: proc_open، client رسمی MySQL 8 یعنی mysql/mysqldump و مسیر باینری‌ها؛ صرف داشتن phpMyAdmin کافی نیست. در نبود این امکانات، پیش از دریافت داده واقعی، بکاپ رمزگذاری‌شده و restore قابل‌آزمایشِ معادل را با میزبان تعیین کنید و آماده‌بودن ops:backup را ادعا نکنید.

اگر DocumentRoot امن یا PHP 8.4/اجرای Artisan/Cron فراهم نیست، این راهنمای نصب مستقیم برای آن سرویس مناسب نیست؛ کل Laravel را داخل public_html نریزید و public/index.php را برای دورزدن مسیرها دست‌کاری نکنید. محدودیت‌های پنل را [مستند رسمی Domains](https://docs.cpanel.net/cpanel/domains/domains/) و پشتیبانی تعیین می‌کنند.

## ۲. دامنه، مسیر و PHP واقعی

دامنه/ساب‌دامنه را ایجاد و DNS را مطابق اطلاعات میزبان تنظیم کنید. AutoSSL یا گواهی معتبر را فعال کنید. قبل از ورود اطلاعات یا تنظیم production، HTTPS باید بدون هشدار گواهی باز شود. صفحه هشدار مرورگر را رد نکنید.

در Terminal، مسیر PHP 8.4 را از پشتیبانی بگیرید. مثال رایج cPanel زیر **مسیر تأییدشده شما نیست**؛ ابتدا اصلاحش کنید:

```bash
PHP_BIN=/opt/cpanel/ea-php84/root/usr/bin/php
"$PHP_BIN" -v
"$PHP_BIN" --ini
"$PHP_BIN" -m
```

اگر خروجی 8.4 نیست یا مسیر وجود ندارد، ادامه ندهید و مسیر صحیح را بگیرید. انتخاب PHP دامنه لزوماً دستور ساده `php` را تغییر نمی‌دهد. همه فرمان‌های بعدی و Cron باید از همین باینری تأییدشده استفاده کنند.

## ۳. ZIP تأییدشده را خارج public_html باز کنید

آخرین artifact موفق شاخه main را از [Actions](https://github.com/anishopmobile/ani-doctor/actions) بگیرید. ZIP بیرونی artifact حاوی ZIP نصب داخلی است. ZIP نصب را در فضای خصوصی مانند زیر استخراج کنید:

```text
/home/CPUSER/ani-doctor/artisan
/home/CPUSER/ani-doctor/vendor/
/home/CPUSER/ani-doctor/public/index.php
```

DocumentRoot دامنه باید `/home/CPUSER/ani-doctor/public` باشد. `.env`، vendor، storage خصوصی و کلیدها یک سطح بیرون آن می‌مانند. فایل‌ها و پوشه‌های hidden از جمله `.htaccess` و `.env.example` باید منتقل شده باشند. نصب از این ZIP به Composer/npm/Node/Docker روی هاست نیاز ندارد.

پیش از ساخت env/runtime:

```bash
cd /home/CPUSER/ani-doctor
"$PHP_BIN" scripts/verify-package.php --root .
```

`verified:true` لازم است؛ artifact معتبر و digest آن از GitHub نیز بررسی شوند. این فرمان برای بسته خام است و بعد از ساخت env/config cache ابزار بررسی روزانه نصب نیست. ZIPهای بارگذاری‌شده را پس از استخراج از هر مسیر قابل‌دسترسی وب حذف کنید.

## ۴. دیتابیس و کاربر اختصاصی

در بخش MySQL Databases/Database Wizard پنل، DB و کاربر جدا بسازید، رمز قوی تولید کنید و آن کاربر را **فقط به همان DB** متصل کنید. برای migrate اولیه مجوزهای لازم جدول/ایندکس هم لازم‌اند؛ گزینه ALL PRIVILEGES در پنل باید فقط روی DB انتخابی باشد، نه دسترسی global سرور. نام کامل معمولاً شامل پیشوند حساب است، مثل `CPUSER_anidoctor`؛ همان نام کامل را بردارید. DB_HOST/پورت را از پشتیبانی بگیرید؛ localhost را حدس نزنید اگر میزبان مقدار دیگری داده است.

رمز تصادفی طولانی حروف لاتین/رقم می‌تواند قوی باشد و خطای escape نقل‌قول در env را هم کمتر کند؛ رمز نمونه راهنما را استفاده نکنید. اگر رمز دارای نقل‌قول/کاراکتر ویژه انتخاب کرده‌اید، مقدار env باید طبق قواعد dotenv درست escape شود و عین رمز پنل باشد.

## ۵. env تولیدی مستقل

فقط نصب تازه:

```bash
cp .env.example .env
```

در File Manager نمایش فایل‌های مخفی را روشن و `.env` را خارج public با ویرایشگر امن تنظیم کنید. نمونه زیر باید با دامنه/DB/رمز واقعی شما کامل شود؛ کل فایل نمونه پروژه را نگه دارید:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://doctor.example.com
APP_KEY=
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=CPUSER_anidoctor
DB_USERNAME=CPUSER_anidoctor
DB_PASSWORD="YOUR_PRIVATE_HOST_DATABASE_PASSWORD"
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning
EVENT_SMS_ENABLED=false
ZARINPAL_ENABLED=false
OPS_HSTS_ENABLED=false
OPS_TRUSTED_PROXIES=
OPS_BACKUP_DIR=/home/CPUSER/ani-backups
OPS_BACKUP_KEY_FILE=/home/CPUSER/ani-secrets/backup.key
```

در صورت استفاده از DB_HOST/نام متفاوت، همان مقدار پشتیبانی را قرار دهید. اگر سرویس پشت reverse proxy است، IP/CIDR پراکسی را از پشتیبانی بگیرید؛ `OPS_TRUSTED_PROXIES=*` ممنوع است. X-Forwarded-* ورودی غیرقابل اعتماد باید در پراکسی حذف/بازنویسی و Host اصلی حفظ شود. IP پراکسی را برای رفع خطا حدس نزنید. HSTS را پس از تأیید HTTPS کامل روشن کنید؛ برای سبزشدن صفحه، APP_ENV را local یا cookie را ناامن نکنید.

CLINIC_PUBLIC_DOCTOR_NAME و CLINIC_PUBLIC_ADDRESS نیز متن عمومی واقعی پزشک/مطب در برگه نوبت‌اند؛ در صورت نیاز با مقدار داخل کوتیشن تنظیم شوند.

## ۶. مجوزها و نصب جدول‌ها

مالکیت صحیح PHP مهم‌تر از chmod عمومی است. `.env` و پوشه کلیدها باید فقط برای مالک/پردازه مجاز خواندنی باشند؛ معمولاً فایل خصوصی 600 و پوشه خصوصی 700، با تطبیق مدل اجرای میزبان. فقط storage و bootstrap/cache باید برای پردازه برنامه writable باشند. به کل پروژه یا فایل‌ها 777 ندهید؛ اگر با دسترسی محدود کار نمی‌کند، پشتیبانی مالکیت را اصلاح کند.

در Terminal همان پروژه:

```bash
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan key:generate --force
"$PHP_BIN" artisan migrate --seed --force
"$PHP_BIN" artisan migrate:status
```

`key:generate` فقط برای نصب **خالی و تازه** است. APP_KEY تولیدی را جدا و امن ذخیره کنید؛ کلید لوکال/CI را برای نصب خالی هاست کپی نکنید. اگر داده رمزگذاری‌شده قبلی منتقل می‌کنید، **باید همان APP_KEY قبلی حفظ شود** و دستور تولید دوباره کلید اجرا نشود. جدول‌ها باید Ran باشند. از `migrate:fresh`, `db:wipe` و rollback برای راه‌اندازی استفاده نکنید.

## ۷. SMS.ir و مدیر

API key، template ID عددی OTP تأییدشده و نام پارامتر قالب را در `.env` وارد کنید:

```dotenv
SMS_IR_API_KEY=YOUR_PRIVATE_SMSIR_KEY
SMS_IR_OTP_TEMPLATE_ID=YOUR_APPROVED_NUMERIC_TEMPLATE_ID
SMS_IR_OTP_PARAMETER=CODE
```

اگر ارائه‌دهنده IP مجاز می‌خواهد، IP خروجی هاست را از پشتیبانی بگیرید. پس از تغییر env:

```bash
"$PHP_BIN" artisan config:clear
read -r -p "Admin mobile (09...): " ADMIN_MOBILE
"$PHP_BIN" artisan identity:provision-staff "$ADMIN_MOBILE" admin
```

برای منشی با شماره جدا همان دستور و role=secretary اجرا شود. حساب/رمز/OTP پیش‌فرض وجود ندارد. تخصیص role جای تأیید شماره نیست؛ ورود واقعی فقط با OTP انجام می‌شود. اکنون با HTTPS و دامنه دقیق APP_URL وارد `/login` شوید. هیچ کلید یا شماره خصوصی را برای عیب‌یابی در چت عمومی نفرستید.

## ۸. کلید بکاپ و بهینه‌سازی

در صورت فراهم‌بودن ابزارهای بکاپ، مسیر باینری رسمی MySQL 8 را از پشتیبانی بگیرید و OPS_MYSQLDUMP_BINARY/OPS_MYSQL_BINARY را تنظیم کنید. فایل کلید مستقل را با فرمان خود برنامه بسازید:

```bash
"$PHP_BIN" artisan ops:backup-key /home/CPUSER/ani-secrets/backup.key
"$PHP_BIN" artisan optimize
```

کلید موجود overwrite نمی‌شود؛ اگر فایل قبلاً وجود دارد، کلید تازه جایگزین نکنید. این کلید با APP_KEY یکی نیست؛ آن دو و بکاپ رمزگذاری‌شده باید جدا نگهداری شوند. داشتن کلید به‌تنهایی اثبات backup/restore نیست. قبل از داده واقعی یک backup و drill بازیابیِ کنترل‌شده طبق [P8](P8.md) لازم است.

محدودیت فعلی: restore داخلی نام دقیق `ani_doctor_restore_*` و حساب مجزا فقط با grant همان مقصد می‌خواهد. پیشوند اجباری cPanel مانند `CPUSER_...` ممکن است با آن سازگار نباشد. نام/کد را برای دورزدن محافظ عوض نکنید؛ drill را روی MySQL جدا با نام مجاز انجام دهید یا با مسئول زیرساخت مسیر بازیابی امن را برنامه‌ریزی کنید. حساب کاربری سایت نباید برای بازیابی global/root شود.

## ۹. Cron واقعی دقیقه‌ای

در Cron Jobs پنل، پنج فیلد زمان را `*` (هر دقیقه) قرار دهید. در **کادر Command** فقط فرمان را بگذارید؛ پنج ستاره را دوباره داخل Command نچسبانید:

```bash
cd /home/CPUSER/ani-doctor && /ACTUAL/PATH/TO/PHP84 artisan schedule:run >> /dev/null 2>&1
```

مسیر PHP واقعی مرحله ۲ را جایگزین کنید؛ متغیر PHP_BIN ترمینال به Cron منتقل نمی‌شود. فقط **یک** Cron برای schedule:run بسازید، نه چند Cron هم‌زمان برای همان کار. ابتدا می‌توان خروجی را در فایل خصوصی با دسترسی محدود دید؛ پس از تأیید، بی‌دلیل لاگ حجیم عمومی نگه ندارید. پس از ۱ تا ۲ دقیقه، بدون اجرای دستی heartbeat، بزنید:

```bash
"$PHP_BIN" artisan ops:check
```

در پیکربندی صحیح production باید ready=true و همه کنترل‌ها pass باشند. `scheduler_heartbeat` نباید با اجرای دستی ops:heartbeat پنهان شود؛ درست‌بودن Cron واقعی را بررسی کنید. این ready فقط پیکربندی محلی است، نه تأیید بانک/SMS/نفوذ/هاست از بیرون. این نسخه outbox و یادآوری را از Scheduler پردازش می‌کند؛ برای همین مسیر queue:work دائمی جدا لازم نیست. [مرجع Cron در cPanel](https://docs.cpanel.net/cpanel/advanced/cron-jobs/).

## ۱۰. بررسی سایت قبل از دعوت بیمار

با مرورگر دامنه واقعی، نه IP/دامنه موقت:

1. `/login` و `/calendar` با HTTPS صحیح باز شوند؛ گواهی معتبر باشد.
2. `/.env`، `/composer.lock`، `/vendor/autoload.php` و `/storage/logs/laravel.log` باید 403/404 باشند و هیچ محتوایی ندهند. اگر فایل/محتوا قابل خواندن بود، سایت برای استفاده آماده نیست؛ DocumentRoot/مالکیت اصلاح شود.
3. پس از خروج، `/management/reports` و `/admin/finance` باید ورود بخواهند؛ حساب بیمار نتواند داده دیگران را ببیند.
4. یک خدمت و شیفت ساختگی، رزرو موقت، انقضا/لغو، حساب منشی، حضور، PDF/Excel را در محیط آزمایشی بررسی کنید؛ هیچ وجه دریافت‌نشده را دریافتی ثبت نکنید.
5. خطای واقعی را از لاگ خصوصی بدون روشن‌کردن APP_DEBUG عمومی پیگیری کنید. کلید، OTP، URL کامل callback یا پاسخ پزشکی را منتشر نکنید.

## ۱۱. درگاه و اعلان‌ها را جدا فعال کنید

تا پایان بررسی اولیه، EVENT_SMS_ENABLED و ZARINPAL_ENABLED خاموش بمانند. برای پرداخت ابتدا محیط sandbox سازگار ارائه‌دهنده، credential معتبر و callback HTTPS قابل دسترس لازم است. تنظیم پایه callback دقیقاً مانند زیر است و برنامه خودش شناسه تلاش را اضافه می‌کند؛ Authority/Status یا UUID را دستی نچسبانید:

```dotenv
ZARINPAL_ENABLED=true
ZARINPAL_SANDBOX=true
ZARINPAL_MERCHANT_ID=YOUR_VALID_MERCHANT_UUID
ZARINPAL_CALLBACK_URL=https://doctor.example.com/payments/zarinpal/callback
```

پس از تغییر: `"$PHP_BIN" artisan config:clear` و `"$PHP_BIN" artisan optimize`. صرف sandbox=true به معنی امکان آزمایش هر Merchant نیست؛ با حساب واقعی خود در پنل ارائه‌دهنده تطبیق دهید. callback اینترنتی معمولاً به localhost دسترسی ندارد؛ آزمون پرداخت کامل را روی staging HTTPS مجزا انجام دهید. در live فقط پس از تأیید مستقل، sandbox=false و Merchant تولیدی همان محیط استفاده شود. مبلغ برنامه **ریال** است.

برای اعلان‌ها ابتدا قالب‌های رویداد را در `/admin/notifications` بررسی و با دلیل/تنظیم معتبر ذخیره کنید؛ پارامترهای عمومی DATE/TIME/REFERENCE بدون پاسخ پزشکی. سپس EVENT_SMS_ENABLED=true و cache را بازسازی و با شماره مجاز تست کنید. OTP مستقل از این کلید است. پذیرش provider را تحویل گوشی فرض نکنید.

## ۱۲. انتقال از لوکال و ارتقاهای بعدی

اگر لوکال فقط داده آزمایشی داشته، نصب خالی و تنظیم واقعی هاست را انتخاب کنید؛ fixture/بیمار/پرداخت ساختگی را به محیط واقعی منتقل نکنید. اگر داده واقعی باید منتقل شود، این دیگر نصب خالی نیست: نسخه هماهنگ DB و storage خصوصی، **همان APP_KEY اصلی**، بکاپ رمزگذاری‌شده، توقف نویسنده‌ها، بازبینی restore و تطبیق مالی لازم‌اند. فایل env لوکال را روی تولید overwrite نکنید؛ کلید اصلی را همراه داده حفظ اما URL/DB/cookie/production و secrets اختصاصی هاست را جدا تنظیم کنید. مقصد restore قبل از promotion نیازمند ابطال session/OTP/cache قدیمی طبق P8 است.

در ارتقا: بکاپ قابل بازیابی → release جدا → بررسی نسخه/migration → نگه‌داشتن env/APP_KEY و storage تولیدی → migrate با --force → optimize → بررسی login/Cron/گزارش → نگه‌داشتن راه برگشت. روی DB هاست تست‌های PHPUnit یا اسکریپت داده ساختگی اجرا نکنید. `php artisan serve` نیز وب‌سرور دائمی هاست اشتراکی نیست.

## ۱۳. خطاهای پرتکرار هاست

| خطا | مسیر اصلاح |
|---|---|
| 503 پیکربندی امن | production/debug، HTTPS در APP_URL، cookieهای امن و پراکسی واقعی را بررسی کنید؛ guard را خاموش نکنید. |
| 426 | برنامه درخواست را HTTP می‌بیند؛ TLS upstream یا IP پراکسی واقعی باید اصلاح شود. |
| 421 | دامنه با APP_URL یکی نیست؛ www/بدون www و دامنه موقت را یکسان کنید. |
| 419 | دامنه و cookie/HTTPS، session DB و refresh فرم را بررسی کنید؛ CSRF را غیرفعال نکنید. |
| صفحه اصلی هست ولی مسیرها 404 | DocumentRoot=public، وجود .htaccess و rewrite/AllowOverride میزبان را بررسی کنید. |
| could not find driver یا خطای PDF | PHP وب و CLI و extensionها ممکن است متفاوت باشند؛ نسخه هر دو را از پنل/پشتیبانی تأیید کنید. |
| 500 بدون جزئیات | storage/logs خصوصی، مالکیت writableها، APP_KEY و نام کامل DB را بررسی کنید؛ APP_DEBUG عمومی را روشن نکنید. |
| ورود/پرداخت SSL خطا می‌دهد | CA خروجی PHP/cURL و اتصال HTTPS ارائه‌دهنده را اصلاح کنید؛ verify=false نگذارید. |
| heartbeat fail | مسیر PHP و CD و Cron دقیقه‌ای/مجوزها را بررسی و یک دقیقه صبر کنید؛ heartbeat دستی شاهد Cron نیست. |
| ops:backup/restore رد شد | maintenance/توقف نویسنده‌ها، key مستقل، client رسمی MySQL، grant فقط مقصد و محدودیت نام DB را بررسی کنید؛ دسترسی root راه‌حل نیست. |

قواعد استقرار و cache با [راهنمای Laravel](https://laravel.com/docs/13.x/deployment) هم‌راستا هستند. این راهنما راه‌اندازی شما را انجام‌شده فرض نمی‌کند؛ SMS/بانک واقعی، شرایط حقوقی و حریم خصوصی و ارزیابی مستقل امنیت همچنان پیش‌نیاز انتشار عمومی‌اند.
