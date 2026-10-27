# ممیزی نیازمندی‌ها و اصلاح نصب/ارتقا — ۲۰۲۶-۱۰-۲۷

مبنا: PRD.md، ARCHITECTURE.md، SECURITY.md، COMPONENTS.md، UI_GUIDELINES.md، WEBSITE-CMS-PLAN و مشخصات انتقال پروژه؛ شاخه feat/doctor-website-dynamic-cms. این گزارش موفقیت تست خودکار را با تکمیل همه امکانات یا پذیرش هاست واقعی یکی نمی‌داند.

## خطای گزارش‌شده و شواهد

لاگ ارسالی نصب کاربر فقط booking.database_error با SQLSTATE=42S02 دارد: یک جدول موردنیاز پیدا نشده است. نام جدول عمداً در لاگ عمومی ثبت نمی‌شود. مسیر اعلام‌شده نصب کاربر روی میزبان بررسی وجود ندارد و دیتابیس او مستقیماً تغییر داده نشده است. کاربر پس از اجرای migrate و راه‌اندازی مجدد تأیید کرد سایت باز شده؛ علت نصب شخصی نیز با این نتیجه تأیید شد. اطلاعات محرمانه ارسالی وارد کد، fixture، گزارش یا بسته نشده‌اند.

روش اعلام‌شده کاربر تنها فایل‌ها و cacheها را تغییر داده بود؛ migrate اجرا نشده بود. آزمون InstallationCheckTest یک دیتابیس مصنوعی دارای جداول P8 می‌سازد، هشت migration CMS را کنار می‌گذارد، خطای ۵۰۰ صفحه اصلی را بازتولید می‌کند، سپس با migrate و همگام‌سازی نقش‌ها صفحات اصلی/خدمات/مقالات/sitemap/تقویم را باز و حفظ خدمت/تعرفه قبلی را تأیید می‌کند. این شاهد برای سازگاری علت گزارش‌شده است، نه حدس نام یک جدول در دیتابیس واقعی.

اشکال فرآیند انتشار قبلی: CI نصب تازه را آزموده بود، اما سناریوی «کد CMS روی دیتابیس قدیمی» در پذیرش ZIP نبود. راهنمای لوکال نیز یادداشت‌های patch قدیمی بدون migration، تعداد قدیمی ۱۰ migration و اشاره به main داشت. این راهنما اکنون مسیر جداگانه ارتقا، حفظ APP_KEY/storage، بررسی PHP 8.4 و اجرای migrationها دارد. ابزار مستقل scripts/check-install.php پیش از Composer نسخه PHP/extensionها و سپس وضعیت نصب را بدون چاپ راز بررسی می‌کند؛ start-local.ps1 پیش از اجرای وب/زمان‌بند همان بررسی را انجام می‌دهد. هیچ مهاجرت، تغییر کلید یا اصلاح دیتابیس به‌طور خودکار انجام نمی‌شود.

CI جدید همان ZIP را روی Apache/HTTPS نصب می‌کند، سپس فقط در دیتابیس مصنوعی نام‌گذاری‌شده، حالت ارتقای ناقص/۵۰۰ را بازتولید، migrationها را اعمال، اثرانگشت داده‌های هسته را مقایسه و صفحات عمومی را دوباره بررسی می‌کند. fixture مخرب در tests/Support است، در ZIP نیست و با flag، نام دیتابیس و مبدأ loopback محدود شده است.

## تطبیق نیازمندی‌های هسته

«کد و تست موجود» در جدول به معنی تأیید provider/هاست زنده یا اجرای تازه تمام تست‌ها نیست؛ نتیجه اجرای همین اصلاح در انتهای گزارش و PR/Actions ثبت می‌شود.

| نیازمندی | شاهد کد / آزمون | وضعیت و حد پذیرش |
|---|---|---|
| BR-01/02/06/17/18: یک پزشک/مطب، کل مبلغ، ویزیت مجدد مستقل، حداقل داده | Catalog، Booking، Payments؛ PRD بخش ۸ | در معماری/کد رعایت شده؛ چندپزشک، بیعانه و پرونده درمانی کامل خارج دامنه‌اند |
| FR-AUTH-01/02، AC-AUTH: OTP، مهمان، نقش و مالکیت | Identity، ActorResolver، AppointmentAccess؛ OtpTest، IdentityLifecycleTest، AuthorizationTest، OtpConcurrencyTest | کد و آزمون مثبت/منفی/رقابت موجود؛ SMS واقعی نیازمند تأیید جداست |
| FR-PAT-01/02: بیمار و رزرو تلفنی | Patients، DeskBookingService؛ P5ClinicTest، ClinicConcurrencyTest | موجود؛ عدم ادغام خودکار بر اساس شماره مشترک آزمون دارد |
| FR-SRV-01، BR-09/17: خدمات، قیمت و فرم | CatalogService، DynamicForms؛ P2SchedulingTest، P6PrivateFormsTest | موجود؛ اطلاعات نمایشی CMS از مبلغ/ظرفیت واقعی جداست |
| FR-SCH-01/02/03، FR-CAL-01، AC-SCH/HOL | ScheduleService، AvailabilityService، JalaliDate و calendar.js؛ P2SchedulingTest، SlotCalculatorTest، ScheduleConcurrencyTest | موجود؛ تقویم واقعی، تعطیلی/استثنا و هشدار نوبت متأثر آزمون دارند |
| FR-APT-01/02، AC-RACE: hold اتمیک/۱۵دقیقه/ظرفیت | BookingService؛ P3BookingTest، BookingConcurrencyTest | موجود؛ ظرفیت در سرور و قفل MySQL سنجیده می‌شود |
| FR-APT-03/04/05/07، AC-EDIT/OVR | Booking/FinancialBooking، سیاست لغو/تغییر؛ P3BookingTest، P4PaymentsTest، BookingConcurrencyTest | موجود؛ بازتخصیص، لغو، اعتبار و override دلیل‌دار آزمون دارند |
| FR-APT-06: حضور و وضعیت مستقل | AttendanceService؛ P5ClinicTest، ClinicConcurrencyTest | موجود؛ تأثیر مالی خودکار ندارد |
| FR-PAY-01/03/04/05، AC-PAY | PaymentService، GatewayInterface، ZarinpalGateway؛ P4PaymentsTest، ZarinpalGatewayTest، FinancialConcurrencyTest | کد و آزمون با fake/شبیه‌ساز موجود؛ موفقیت بانکی واقعی تأیید نشده |
| FR-PAY-02 و BR-11: درگاه قابل تعویض/چنددرگاه | Interface و adapter زرین‌پال و شبیه‌ساز | زرین‌پال و مرز adapter موجود؛ درگاه دوم واقعی و صفحه انتخاب چند درگاه پیاده‌سازی کامل ندارند |
| FR-PAY-06/07/09: اعتبار، لغو و استرداد | WalletService، RefundService، ledger؛ P4PaymentsTest، FinancialConcurrencyTest | موجود؛ استرداد بانکی دستی است و انتقال واقعی را خودکار تأیید نمی‌کند |
| FR-PAY-08، BR-13/14، AC-OFFLINE/REP | OfflinePaymentService، تنظیمات مالی/ممیزی؛ P4PaymentsTest، P5ClinicTest | موجود؛ فعال‌سازی مدیر و ثبت وجه منشی جدا هستند |
| FR-SMS-01/02 | NotificationOutbox با confirmed/rescheduled/cancelled/reminder/secretary، MessageDispatcher؛ P6NotificationsTest، SmsIrProviderTest، P6ConcurrencyTest | کد، صف/retry و قرارداد fake موجود؛ اعتبار حساب، تحویل اپراتور و قطعی شبکه واقعی تأیید نشده |
| FR-FRM-01/02 | FormSchema، PrivateFormService؛ P6PrivateFormsTest و رقابت P6 | انواع متن/عدد/تاریخ/انتخاب/چندانتخاب/checkbox و نسخه/رمزگذاری موجود؛ نوع فایل پزشکی طبق PRD بخش ۸ عمداً ممنوع، صفحه عمومی ناشناس پزشکی ندارد |
| FR-DIS-01 | DiscountService؛ P7DiscountsTest، P7DiscountConcurrencyTest | کد و کنترل سقف/هم‌زمانی موجود |
| FR-REP-01/02، AC-REP | ReportService، TicketService؛ P7ReportsTest | گزارش و PDF/XLSX RTL موجود؛ چاپگر واقعی و همه نسخه‌های Excel تأیید نشده‌اند |
| ماتریس نقش‌ها و تنظیمات بدون کد | Gates/Policies، کنترل‌های تنظیمات رزرو/مالی/اعلان؛ AuthorizationTest و فازهای P2–P7 | عملیات اصلی موجود؛ تخصیص نقش کارکنان ابزار CLI دارد، نه ویرایشگر کامل گرافیکی همه مجوزها |
| NFR-01/03/04 | Blade/Tabler محلی، فونت، تبدیل UTC/Tehran/Jalali، integer IRR؛ WebSecurityTest و آزمون‌های Unit | موجود؛ PHP معتبر بسته 8.4 است، متن قدیمی 8.3 در معماری اصلاح شد؛ ارزیابی جامع screen-reader/دستگاه واقعی انجام نشده |
| NFR-02/05/06 | سخت‌سازی، audit، idempotency، خروجی خصوصی، BackupService؛ P8Hardening/Archive/Readiness/Recovery و رقابت مالی | کد و شواهد مصنوعی موجود؛ TLS/backup خارج سایت و نقش‌های هاست واقعی جداگانه لازم‌اند |
| NFR-07 | load-test.php، HTTPS loopback | بار مصنوعی و هدف p95 آزمون دارد؛ ظرفیت اینترنت/سرور نهایی یا کاربر واقعی تأیید نشده |

## تطبیق Website/CMS و موارد ناتمام

| قابلیت | شاهد | وضعیت |
|---|---|---|
| صفحه عمومی و خدمات واقعی | WebsiteController، PublicServiceController؛ PublicWebsiteTest، PublicServicesTest | موجود؛ صفحات فعال/آنلاین و بدون داده بیمار |
| سکشن/اسلاید، پیش‌نویس، ترتیب، منو/فوتر | SectionService، LinkService؛ WebsiteSectionsTest، WebsiteLinksTest | ساختارهای مشخص موجود؛ صفحه‌ساز drag/drop و layout آزاد وجود ندارد |
| مقاله، زمان انتشار، دسته/برچسب | ArticleService، taxonomy؛ WebsiteArticles/TaxonomiesTest | موجود؛ متن ساده escape می‌شود، ویرایشگر HTML غنی/تاریخچه تحریریه کامل نیست |
| تصاویر مقاله/سکشن/خدمت و توضیحات خدمت | MediaService، ServiceContentService؛ WebsiteMediaTest، WebsiteServiceContentTest، WebsiteServiceMediaIntegrationTest | موجود؛ یک تصویر به ازای هر مالک، فایل خصوصی و انتشار مشروط؛ crop/thumbnail/CDN/اسکن AV موجود نیست |
| نام برند، عنوان، تماس، رنگ | ThemeService و WebsiteThemeRequest؛ WebsiteThemeTest | موجود؛ لوگو/favicon آپلودی مستقل، تایپوگرافی و preset کامل پیاده نشده‌اند |
| SEO | sitemap، canonical، meta/Open Graph، noindex محیط غیرتولیدی؛ WebsiteSitemapTest، WebsiteThemeTest | مبنای SEO موجود؛ sitemap بزرگ چندبخشی، SEO اختصاصی همه انواع صفحه و تصویر اجتماعی مستقل کامل نیست؛ دامنه robots.txt باید روی هاست بازبینی شود |
| یکپارچگی رزرو | لینک service_id به تقویم واقعی و قواعد موجود؛ PublicServicesTest و تست‌های P3/P4 | موجود؛ تقویم داخل صفحه بازاریابی embed نشده، مسیر اصلی حفظ شده است |
| پذیرش انتشار | PackageVerifier، staging-smoke، tests/Support/website-upgrade-fixture.php | نصب تازه و ارتقای بسته در CI آزموده می‌شوند؛ گواه انتشار روی هاست واقعی نیست |

## گیت‌های امنیتی

SEC-01/02: مالکیت، نقش و OTP آزمون دارند. SEC-03/04/05/12: ظرفیت/پرداخت/اعتبار با رقابت MySQL و idempotency آزمون دارند. SEC-06/07: اختیارات منشی و audit عملیات مالی آزمون دارند. SEC-08: فرم، گزارش و تصاویر خصوصی کنترل دسترسی دارند. SEC-09: بسته secrets/env/runtime/tests و وابستگی توسعه را رد می‌کند. SEC-10: DocumentRoot=public و APP_DEBUG=false و HTTPS در staging مستقل بررسی می‌شوند. SEC-11: تعطیلی، هشدار نوبت متأثر و حفظ سوابق آزمون دارند. هیچ‌کدام جای تست نفوذ مستقل، بررسی access log میزبان یا حریم خصوصی/تأیید بالینی/حقوقی را نمی‌گیرند.

## نتیجه ممیزی

ادعای «همه نیازمندی‌ها تمام و همه آزمون‌های ممکن موفق شده‌اند» درست نیست. نسخه، هسته عملیاتی و CMS محدود قابل آزمایش دارد؛ چنددرگاه، لوگوی مستقل و صفحه‌ساز کامل از موارد ناتمام کدند. اتصال زنده بانک/SMS، هاست واقعی، تست نفوذ مستقل، چاپ/موبایل واقعی و تأیید محتوای پزشکی گیت‌های تأییدنشده‌اند. بسته اصلاحی نصب/ارتقا تنها پس از موفقیت واقعی CI همان کامیت ارائه می‌شود و بسته تست/پذیرش است، نه گواه آمادگی بی‌قیدوشرط انتشار عمومی.

## شواهد اجرای این اصلاح

- بازتولید/بررسی نصب محلی: InstallationCheckTest، تعداد ۵ تست / ۳۲ assertion موفق، شامل ۵۰۰ قبل از migrate و ۲۰۰ پس از آن، حفظ خدمت، داده محرمانه در خطای DB و نبود extension.
- مجموعه کامل محلی پس از افزودن بررسی ارتقا: ۲۷۲ تست / ۱۸۸۳ assertion موفق. بررسی نصب و بسته همراه هم: ۸ تست / ۴۶ assertion موفق. Pint موفق.
- PHP پیش‌فرض میزبان بررسی 8.3.30 بود و ابزار pre-Composer آن را بدون boot Laravel رد کرد؛ این مشاهده به PHP نصب شخصی کاربر تعمیم داده نشده است.
- نتایج مجموعه کامل، MySQL، Integration، ZIP و staging کامیت نهایی در PR #12 و گزارش تحویل ثبت می‌شوند؛ موفقیت آن‌ها پیشاپیش ادعا نشده است.
