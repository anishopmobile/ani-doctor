# شواهد اجرای P1

تاریخ: ۲۰۲۶-۱۰-۲۵ (Asia/Tehran). مبنا: شاخه feat/p1-identity-foundation؛ مخزن پیش از این تغییر فقط اسناد و CI داشت.

| بررسی اجراشده | نتیجه واقعی |
|---|---|
| نصب composer.lock با PHP 8.4.27 و package discovery | موفق؛ Laravel 13.38.0 |
| PHPUnit، SQLite در حافظه | ۲۷ تست، ۱۵۷ assertion، موفق |
| همان مجموعه PHPUnit روی MySQL 8.4.3 | ۲۷ تست، ۱۵۷ assertion، موفق |
| هم‌زمانی واقعی OTP، دو پردازه مستقل روی MySQL/InnoDB | ۲ تست، ۱۳ assertion، موفق؛ صدور یک challenge و مصرف یک‌باره |
| migrate --seed → rollback → migrate --seed، MySQL دورریختنی | موفق؛ چهار migration |
| PHP 8.4 syntax | ۵۲ فایل PHP برنامه/تنظیمات/تست؛ موفق |
| Pint --test | موفق |
| composer validate --strict | موفق |
| composer audit --locked | هیچ advisory شناخته‌شده گزارش نشد؛ گواهی امنیت نیست |
| راه‌اندازی محلی PHP روی localhost با MySQL و نشست دیتابیس | صفحه /login بارگذاری شد |
| مرورگر در viewport 320×740 | بدون سرریز افقی؛ فونت محاسبه‌شده 16px؛ خطای فارسی کنار فیلد و summary، فوکوس summary، ارسال دوباره قابل استفاده |
| کنسول مرورگر در بررسی صفحه ورود/خطای فرم | خطا یا هشدار مشاهده نشد |

در اجرای نخست تست‌ها، مقدار اولیه is_active در مدل تازه‌ساخته‌شده موجود نبود؛ باعث رد ورود حساب جدید می‌شد. مقدار پیش‌فرض مدل اصلاح شد و نتایج موفق بالا از اجرای پس از اصلاح هستند. اسکن نحو اولیه با PHP 8.3 نیز انجام شد؛ مبنای نهایی، اجرای PHP 8.4 بالا است.

تست‌ها شامل hash و پاک‌شدن OTP، انقضای دقیق، ۵ تلاش ناموفق، cooldown، ابطال challenge قبلی، replay، جعل purpose/session، قطعی Provider، محدودیت IP/شماره، نقش‌های مثبت/منفی، حساب غیرفعال/تأییدنشده، مالکیت بیمار با موبایل مشترک، عدم ارتقای نقش با ورودی عمومی، عدم enumeration، نشست و خروج، CSRF واقعی با غیرفعال‌کردن bypass تست، prune و رفت‌وبرگشت migration هستند. قرارداد SMS.ir با HTTP fake تست شده است.

## اجرا نشده / محدودیت‌ها

- پیامک واقعی SMS.ir، صحت credential و تحویل اپراتور: اجرا نشده؛ credential واقعی ارائه نشده است.
- نصب staging/هاست، HTTPS تولیدی، queue/scheduler روی هاست و تست بازیابی بکاپ: اجرا نشده.
- zoom 200٪، دستگاه موبایل واقعی، تست نفوذ و ارزیابی کامل دسترس‌پذیری: اجرا نشده؛ بررسی موبایل فقط viewport مرورگر بوده است.
- نوبت، پرداخت، تقویم و claim نوبت مهمان: خارج از P1 و پیاده‌سازی نشده‌اند.
- CI و ZIP: وضعیت نهایی از run مربوط به commit در GitHub خوانده شود؛ نتایج محلی بالا معادل تأیید CI یا استقرار نیستند.

فایل‌های تغییرکرده شامل اسکلت استاندارد app/bootstrap/config/database/public/resources/routes/storage/tests، composer.json/lock، تنظیم PHPUnit/Pint، ماژول Identity، مدل/Policy پایه Patients، دارایی‌های محلی دارای مجوز، workflow و اسناد مرجع است. `sources/` پروژه ChatGPT تغییر نکرده است. فایل‌های runtime، vendor، کلید، OTP واقعی و دیتابیس وارد Git نمی‌شوند.
