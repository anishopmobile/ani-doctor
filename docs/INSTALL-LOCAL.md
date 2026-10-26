# نصب تازه روی لوکال ویندوز

این راهنما مخصوص **نصب تازه با داده آزمایشی** است؛ روی نصب قبلی `.env` را جایگزین و APP_KEY را دوباره تولید نکنید. محیط هدف PHP 8.4 و MySQL 8 است. مسیر نمونه `C:\laragon\www\ani-doctor` است؛ اگر مسیر دیگری دارید همه دستورها را همان‌جا اجرا کنید. برای وب از `artisan serve` استفاده می‌کنیم و تنظیم Apache/SSL ویندوز لازم نیست.

## ۱. پیش‌نیازها

MySQL را در Laragon روشن کنید. نسخه PHP ترمینال باید 8.4 باشد؛ داشتن PHP 8.4 در منوی برنامه کافی نیست اگر ترمینال PHP دیگری اجرا کند. PowerShell یا Terminal همان محیط را باز کنید:

```powershell
php -v
php --ini
php -m
```

در `php -m` این‌ها باید موجود باشند: `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `gd`, `iconv`, `intl`, `libxml`, `mbstring`, `openssl`, `PDO`, `pdo_mysql`, `session`, `SimpleXML`, `tokenizer`, `xml`, `xmlreader`, `xmlwriter`, `zip`, `zlib`. نبودن gd/intl/zip می‌تواند PDF/Excel/تقویم را خراب کند. extension را در همان php.ini نمایش‌داده‌شده فعال و ترمینال/سرور PHP را دوباره باز کنید. نسخه سرور MySQL با `SELECT VERSION();` باید MySQL 8 باشد؛ شماره نسخه phpMyAdmin یا MariaDB معادل آن نیست. PHP/MySQL مورد استفاده این راهنما با پروژه تست شده‌اند؛ جایگزین را بدون آزمون برابر فرض نکنید.

## ۲. بسته صحیح را دریافت کنید

از [Actions مخزن](https://github.com/anishopmobile/ani-doctor/actions) آخرین اجرای **موفق شاخه main** را باز و artifact `ani-doctor-host-ready-...` را دانلود کنید. فایل بیرونی artifact را باز کنید؛ داخل آن ZIP دیگری مانند `ani-doctor-<sha>.zip` است. **ZIP داخلی** را در مسیر نمونه استخراج کنید.

باید مستقیماً فایل `artisan` و پوشه‌های `app`, `public`, `vendor` را در `C:\laragon\www\ani-doctor` ببینید؛ دو پوشه هم‌نامِ تو در تو نسازید. این بسته vendor دارد؛ Composer، npm، Node و Docker برای این مسیر نصب لازم نیستند. Code → Download ZIP مخزن، vendor ندارد و جایگزین این بسته نیست.

```powershell
Set-Location C:\laragon\www\ani-doctor
php scripts/verify-package.php --root .
```

پاسخ باید `verified:true` داشته باشد. این کار **قبل از ساخت .env و cache اجرایی** است؛ پس از نصب، این verifier مخصوص بسته خام است و ممکن است وجود env/cache را رد کند. digest artifact نیز از همان اجرای معتبر GitHub بررسی شود؛ manifest به‌تنهایی امضا نیست.

## ۳. دیتابیس مستقل بسازید

در ابزار Database لارگون یا ابزار مدیریت MySQL، با حساب مدیریتی خود وصل شوید. روی یک سرور لوکال می‌توانید SQL زیر را اجرا کنید. ابتدا عبارت رمز نمونه را با یک رمز واقعی قوی و مخصوص همین دیتابیس جایگزین کنید؛ مقدار نمونه رمز قابل استفاده نیست:

```sql
CREATE DATABASE anidoctorlocal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'anidoctorlocal'@'127.0.0.1' IDENTIFIED BY 'REPLACE_WITH_A_LONG_UNIQUE_PASSWORD';
GRANT ALL PRIVILEGES ON anidoctorlocal.* TO 'anidoctorlocal'@'127.0.0.1';
```

اگر پیام already exists گرفتید، چیزی حذف نکنید؛ نام تازه انتخاب کنید یا نصب قبلی را بررسی کنید. این حساب فقط برای دیتابیس خودش است. سرور MySQL را برای شبکه عمومی باز نکنید. اگر پورت MySQL شما 3306 نیست، مقدار واقعی را در مرحله بعد بگذارید.

برای نمونه SQL بالا، رمز تصادفی طولانی (مثلاً ۳۲ نویسه حروف لاتین و رقم) انتخاب کنید تا نقل‌قول SQL/env اشتباه نشود؛ رمز نمونه را نگه ندارید. ساخت کاربر از رابط امن مدیریت MySQL نیز ممکن است و در آن صورت همان نام/رمز واقعی را در env وارد کنید.

## ۴. فایل محیط لوکال

فقط در نصب تازه:

```powershell
Copy-Item .env.example .env
notepad .env
```

نام فایل باید دقیقاً `.env` باشد، نه `.env.txt`. آن را با UTF-8 ذخیره کنید. کل فایل نمونه حفظ شود و فقط موارد زیر با مقادیر واقعی شما تنظیم شوند:

```dotenv
APP_ENV=local
APP_DEBUG=false
APP_URL=http://127.0.0.1:8000
APP_KEY=
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=anidoctorlocal
DB_USERNAME=anidoctorlocal
DB_PASSWORD="YOUR_LOCAL_DATABASE_PASSWORD"
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=false
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
EVENT_SMS_ENABLED=false
ZARINPAL_ENABLED=false
OPS_HSTS_ENABLED=false
OPS_TRUSTED_PROXIES=
```

رمز داخل کوتیشن را جایگزین کنید. `SESSION_SECURE_COOKIE=false` و HTTP فقط برای لوکال‌اند؛ این `.env` را به هاست انتقال ندهید. محیط production پروژه HTTP را عمداً رد می‌کند. APP_KEY را دستی یا از این راهنما کپی نکنید؛ در مرحله بعد خود برنامه کلید تازه می‌سازد.

## ۵. کلید و جدول‌ها

```powershell
php artisan config:clear
php artisan key:generate
php artisan migrate --seed
php artisan migrate:status
```

برای نصب تازه، ۱۰ migration موجود باید Ran باشند. `--seed` فقط نقش/مجوز می‌سازد؛ حساب مدیر یا رمز پیش‌فرض وجود ندارد. از APP_KEY تولیدشده نسخه امن بگیرید و در نصب‌های بعدی تغییرش ندهید. فرمان‌های `migrate:fresh`, `db:wipe` و rollback جزو نصب نیستند و می‌توانند داده را از بین ببرند. تست‌های PHPUnit را روی همین DB یا DB واقعی اجرا نکنید؛ تست‌ها به DB جداگانه دورریختنی نیاز دارند.

## ۶. ورود OTP را تنظیم کنید

حتی روی لوکال، adapter اصلی از SMS.ir استفاده می‌کند؛ **کد ثابت، ورود بدون پیامک یا نمایش OTP در لاگ وجود ندارد**. برای ورود باید اینترنت، حساب/اعتبار SMS.ir، API key و قالب OTP تأییدشده داشته باشید. این تنظیمات را فقط در `.env` خودتان بگذارید:

```dotenv
SMS_IR_API_KEY=YOUR_PRIVATE_SMSIR_KEY
SMS_IR_OTP_TEMPLATE_ID=YOUR_APPROVED_NUMERIC_TEMPLATE_ID
SMS_IR_OTP_PARAMETER=CODE
```

نام پارامتر باید عین نام پارامتر قالب باشد. مقادیر YOUR_... نمونه‌اند و باید جایگزین شوند؛ کلید/کد/شماره خصوصی را در Git، چت یا تصویر عمومی نگذارید. اگر محدودیت IP در پنل ارائه‌دهنده دارید، IP خروجی اینترنت خود را با تنظیم حساب هماهنگ کنید. بدون این مرحله صفحه ورود باز می‌شود اما پیامک/ورود موفق نخواهد شد. ارسال OTP واقعی ممکن است هزینه پیامک داشته باشد.

`EVENT_SMS_ENABLED=false` فقط اعلان‌های نوبت را خاموش می‌کند، نه پیامک ورود. فعلاً آن و درگاه را خاموش نگه دارید. بعد از ویرایش:

```powershell
php artisan config:clear
```

## ۷. مدیر و منشی خودتان را بسازید

```powershell
$adminMobile = Read-Host 'شماره موبایل مدیر با 09'
php artisan identity:provision-staff $adminMobile admin
```

در صورت نیاز به حساب منشی جدا:

```powershell
$secretaryMobile = Read-Host 'شماره موبایل منشی با 09'
php artisan identity:provision-staff $secretaryMobile secretary
```

شماره واقعی قابل دریافت OTP را وارد کنید. این دستور فقط نقش می‌دهد؛ جای OTP نیست. Seeder و نصب هیچ کاربر آزمایشی آماده‌ای نمی‌سازند. scriptهای fixture پوشه tests برای نصب عادی نیستند و نباید اجرا شوند.

## ۸. وب و زمان‌بند را اجرا کنید

در ترمینال اول:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

در مرورگر **همیشه همین آدرس** را باز کنید: `http://127.0.0.1:8000/login`. میان localhost و 127.0.0.1 جابه‌جا نشوید؛ cookie/CSRF متعلق به همان میزبان است. کد OTP را با شماره مدیر دریافت و وارد کنید.

در ترمینال دوم، با همان PHP و مسیر پروژه:

```powershell
Set-Location C:\laragon\www\ani-doctor
php artisan schedule:work
```

هر دو پنجره باز بمانند. با بستن/خوابیدن کامپیوتر اجرای منظم زمان‌بند متوقف می‌شود؛ اجراشده فرضش نکنید. برای توقف، در هر پنجره Ctrl+C بزنید. نسخه فعلی اعلان‌ها و انقضا را با Scheduler/DB outbox پردازش می‌کند و برای همین جریان به اجرای جداگانه `queue:work` نیاز ندارد.

## ۹. بررسی اولین استفاده

به‌ترتیب: ورود مدیر → تعریف خدمت در `/admin/services` → شیفت‌ها در `/management/schedule` → تقویم `/calendar` → رزرو موقت مصنوعی → مشاهده انقضا/لغو → ورود منشی و نوبت تلفنی → ثبت حضور → گزارش و PDF/Excel. برای دریافت حضوری فقط وجه واقعاً دریافت‌شده را ثبت کنید؛ در مرحله آزمایش از داده کاملاً ساختگی استفاده کنید. با خاموش‌بودن gateway، پرداخت اینترنتی انجام نمی‌شود؛ رزرو پولی تا پرداخت/انقضا hold می‌ماند. کلید اضطراری را برای سبزکردن آزمایش بی‌دلیل فعال نکنید.

برای نام پزشک و نشانی روی برگه نوبت، CLINIC_PUBLIC_DOCTOR_NAME و CLINIC_PUBLIC_ADDRESS را در env با متن عمومی مطب و داخل کوتیشن تنظیم و config:clear کنید؛ برنامه مقدار ساختگی پیش‌فرض چاپ نمی‌کند.

`php artisan ops:check` مخصوص کنترل production است؛ در این نصب HTTP/local، fail شدن production/https/secure-cookie و کلید بکاپ تنظیم‌نشده طبیعی است. برای سبزکردنش محیط لوکال را بی‌مقدمه production نکنید. قبل از نصب عمومی، راهنمای [هاست اشتراکی](INSTALL-SHARED-HOSTING.md) اجرا شود.

## ۱۰. خطاهای رایج

| نشانه | اقدام |
|---|---|
| vendor/autoload.php پیدا نمی‌شود | ZIP نصب دارای vendor را بگیرید؛ احتمالاً ZIP سورس یا پوشه تو در تو را باز کرده‌اید. |
| PHP باید 8.4 باشد | خروجی php -v و php --ini ترمینال را اصلاح کنید؛ فقط نسخه PHP مرورگر کافی نیست. |
| could not find driver | pdo_mysql را در php.ini همین CLI فعال و سرور را دوباره اجرا کنید. |
| SQLSTATE 1045/1049/2002 | نام کامل DB، کاربر، رمز، Host، پورت و روشن‌بودن MySQL را بررسی کنید؛ جدول‌های DB دیگری را حذف نکنید. |
| 419 | همان 127.0.0.1:8000، SESSION_SECURE_COOKIE=false، مرور مجدد صفحه ورود و cookie همان سایت را بررسی کنید؛ CSRF را خاموش نکنید. |
| 426/503 پیکربندی امن | APP_ENV=local و APP_URL HTTP لوکال را بررسی و config:clear کنید؛ production را برای HTTP به‌کار نبرید. |
| پیامک نمی‌رسد | کلید، شناسه قالب، نام پارامتر، اعتبار حساب، IP مجاز و اینترنت را بررسی کنید؛ cooldown ۶۰ثانیه‌ای/سقف درخواست را رعایت کنید. |
| SSL certificate error در پیامک/درگاه | CA معتبر PHP/cURL را از منبع رسمی تنظیم کنید؛ verify=false یا خاموش‌کردن بررسی SSL راه‌حل نیست. |
| PDF یا Excel خراب | gd/mbstring/zip/xml، نوشتن storage و وجود TTF در resources/fonts را بررسی کنید. برای فونت Excel می‌توان Vazirmatn را روی رایانه نمایش‌دهنده نصب کرد؛ در XLSX embed نشده است. |

راهنمای معتبر CA: [cURL TLS certificates](https://curl.se/docs/sslcerts.html). برای تغییر CA در PHP، مسیر واقعی فایل معتبر را در curl.cainfo و openssl.cafile همان php.ini بگذارید و سرور را دوباره اجرا کنید؛ فایل/مسیر تصادفی وارد نکنید.
