# آنی دکتر | Ani Doctor

سامانه اختصاصی نوبت‌دهی آنلاین و مدیریت مطب برای **یک پزشک متخصص زنان، زایمان و نازایی**.

> وضعیت فعلی: **مستندسازی و معماری (Phase 0)**؛ کد اصلی سامانه هنوز ایجاد نشده است.

## اسناد مرجع

1. [PRD.md](PRD.md) — نیازمندی‌ها، قوانین تجاری، معیار پذیرش و فازها
2. [ARCHITECTURE.md](ARCHITECTURE.md) — معماری و ساختار فنی
3. [AI_RULES.md](AI_RULES.md) — قواعد اجباری توسعه با کمک هوش مصنوعی
4. [UI_GUIDELINES.md](UI_GUIDELINES.md) — اصول رابط کاربری فارسی، RTL و ریسپانسیو
5. [COMPONENTS.md](COMPONENTS.md) — ماژول‌ها و قرارداد اجزا
6. [SECURITY.md](SECURITY.md) — امنیت، حریم خصوصی و حفاظت تراکنش‌ها
7. [DEPLOYMENT.md](DEPLOYMENT.md) — تولید و استقرار بسته ZIP روی هاست

## فناوری پایه

PHP 8.4، Laravel 13، MySQL 8، Blade، Bootstrap 5، Tabler، Vanilla JavaScript/Fetch API، تقویم جلالی، SMS.ir و زرین‌پال.

معماری: **Modular Monolith + MVC + Service Layer**. نیازی به میکروسرویس، SPA یا build پیچیده Frontend نیست. فایل‌های CSS/JS آماده و نسخه‌بندی‌شده در `public/assets` قرار می‌گیرند.

## سیاست توسعه

هر قابلیت تنها پس از تعریف معیار پذیرش در PRD و تست، پیاده‌سازی شود. هیچ `.env` یا کلید واقعی نباید commit شود. ارسال تغییرات به GitHub باید همراه با مستندات مرتبط و آزمون باشد.

## GitHub Actions

Workflow با هر push اجرا می‌شود. تا زمانی که پروژه Laravel و `composer.lock` ایجاد نشود، فقط صحت مستندات بررسی می‌شود و بسته نصب **ساخته نمی‌شود**. بعد از ایجاد اپلیکیشن، تست و بسته‌بندی ZIP دارای `vendor/` به‌صورت خودکار اجرا خواهد شد. خروجی در تب **Actions → Artifacts** در دسترس است.
