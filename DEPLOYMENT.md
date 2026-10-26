# نصب و استقرار آنی دکتر

برای نصب تازه، راهنمای متناسب با مقصد را کامل و به‌ترتیب اجرا کنید:

- [لوکال ویندوز](docs/INSTALL-LOCAL.md): PHP 8.4/MySQL 8، HTTP فقط روی loopback، env لوکال، OTP واقعی، سرور و زمان‌بند جدا.
- [هاست اشتراکی](docs/INSTALL-SHARED-HOSTING.md): بررسی سازگاری میزبان، DocumentRoot=public، HTTPS، env مستقل production، Terminal/PHP صحیح، Cron، بکاپ و آزمون اولیه.
- [عملیات و بازیابی P8](docs/P8.md): فرمت بکاپ، کلید مستقل، grant محدود، drill و گیت‌های انتشار.

## بسته صحیح

از [GitHub Actions](https://github.com/anishopmobile/ani-doctor/actions) آخرین اجرای موفق **شاخه main** را انتخاب کنید. artifact بیرونی حاوی ZIP نصب داخلی ani-doctor-<sha>.zip است. ZIP داخلی را استخراج کنید؛ artisan و vendor باید در ریشه نصب باشند. بسته Code → Download ZIP سورس است و vendor ندارد.

بسته آماده شامل app/bootstrap/config/database/lang/public/resources/routes، artisan و composer.lock، vendor production-only، scripts/deployment، راهنماهای منتخب docs و RELEASE.json است. env واقعی، کلید، DB، بکاپ، بیمار، تست‌ها، Git و runtime داخل آن نیستند. هیچ Composer/npm/Node روی هاست برای این بسته لازم نیست. PHP 8.4، extensionهای لازم، MySQL 8/InnoDB، دسترسی نوشتن runtime و اجرای Artisan/Cron همچنان ضروری‌اند.

قبل از افزودن .env و runtime/cache، در پوشه استخراج‌شده اجرا کنید:

```sh
php scripts/verify-package.php --root .
```

از PHP صحیح مقصد استفاده کنید. verified:true و منشأ/digest معتبر artifact را بررسی کنید؛ manifest امضا نیست. این verifier مخصوص بسته خام است و برای نصب دارای .env و cache روزانه استفاده نمی‌شود.

## قواعد مشترک مهم

1. فقط پوشه public در دسترس وب باشد؛ ریشه Laravel را در public_html منتشر نکنید. تنظیم DocumentRoot ممکن است نیازمند پشتیبانی میزبان باشد.
2. لوکال HTTP: APP_ENV=local و SESSION_SECURE_COOKIE=false؛ هاست HTTPS: production/debug=false و cookie امن. env لوکال را به هاست کپی نکنید.
3. نصب خالی APP_KEY تازه می‌گیرد؛ ارتقا یا انتقال داده رمزگذاری‌شده **همان APP_KEY قبلی** را حفظ می‌کند. تولید دوباره کلید داده قبلی را قابل بازیابی نمی‌کند.
4. migration فقط از کنسول مجاز و پس از بررسی/بکاپ؛ نه درخواست وب و نه هنگام استخراج ZIP. migrate:fresh/db:wipe/rollback دستور نصب نیستند.
5. Seeder فقط نقش‌هاست؛ کاربر پیش‌فرض ندارد. identity:provision-staff از کنسول نقش می‌دهد؛ OTP همچنان لازم است. fixtureهای tests برای نصب عادی نیستند.
6. env، فایل خصوصی، بکاپ و کلید خارج public بمانند؛ chmod 777، storage:link به داده خصوصی یا installer عمومی ساخته نشود.
7. برای جریان فعلی اعلان/انقضا/reconcile، Cron دقیقه‌ای schedule:run کافی است؛ queue:work جدا شرط این پیاده‌سازی نیست. heartbeat تازه باید از Cron واقعی بیاید.
8. بکاپ داخلی به client رسمی MySQL 8 و proc_open و کلید مستقل نیاز دارد؛ restore به مقصد خالی با نام ani_doctor_restore_* و حساب فقط مجاز به همان مقصد. پیشوند اجباری بعضی cPanelها با این قرارداد سازگار نیست؛ راهکار drill/بازیابی باید قبل داده واقعی با میزبان تعیین شود.
9. فقط بعد از تنظیم/آزمون مستقل، SMS رویداد و درگاه را فعال کنید؛ callback زرین‌پال پایه HTTPS ثابت دارد و UUID را خود برنامه اضافه می‌کند.
10. ادغام کد، ساخت ZIP و موفقیت staging موقت به معنی استقرار روی هاست واقعی، موفقیت پرداخت/SMS واقعی یا تأیید حقوقی/نفوذ نیستند.

## منابع رسمی زیرساخت

- [Laravel deployment](https://laravel.com/docs/13.x/deployment)
- [cPanel Domains](https://docs.cpanel.net/cpanel/domains/domains/)
- [cPanel Cron Jobs](https://docs.cpanel.net/cpanel/advanced/cron-jobs/)
- [cURL CA verification](https://curl.se/docs/sslcerts.html)

پارامترهای واقعی حساب، دامنه، مسیر PHP، CA و باینری MySQL از محیط مقصد گرفته شوند؛ مثال راهنما جای credential یا اطلاعات تأییدشده میزبان نیست.
