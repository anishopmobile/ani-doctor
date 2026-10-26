# ARCHITECTURE — معماری فنی آنی دکتر
**نسخه:** 1.0.0 · **مرجع قواعد محصول:** [PRD.md](PRD.md)

## 1. تصمیم معماری (ADR-001)
**Modular Monolith + MVC + Service Layer** در یک مخزن و یک استقرار PHP. درخواست کاربر → Route → Controller → FormRequest/Policy → Application Service → Domain/Repository → Database/Adapter → Response (Blade یا JSON). نه میکروسرویس، نه SPA، نه Node build اجباری و نه اتصال مستقیم JS به دیتابیس.

**فناوری‌ها:** Laravel 13، PHP 8.4 (حداقل الزامی Laravel 13: 8.3)، MySQL 8/InnoDB، Blade، Bootstrap 5، Tabler، Vanilla JS + Fetch API، فونت Vazirmatn، تقویم جلالی inline. Laravel به‌صورت سروررندر اجرا شود؛ فایل‌های CSS/JS استفاده‌شده، build شده و نسخه‌بندی‌شده در `public/assets/` نگهداری شوند. @vite / asset pipeline وابسته به npm در تولید نسخه اول مجاز نیست.

## 2. محدوده ماژول‌ها
| ماژول | مسئولیت مالک | وابستگی مجاز |
|---|---|---|
| Identity | کاربران، OTP، مجوزها | Notification Provider از طریق port |
| Patients | اطلاعات پایه بیمار، رزروکننده/بیمار | Identity |
| Catalog | خدمت، قیمت، مدت، بافر، ظرفیت | بدون وابستگی به پرداخت |
| Scheduling | شیفت، تعطیلی، استثنا، تقویم/availability | Catalog |
| Booking | وضعیت نوبت، hold، رزرو، تغییر/لغو، overbook | Scheduling, Patients, Pricing ports |
| Billing | قیمت نهایی snapshot، تخفیف، بدهی، وضعیت مالی | Booking events |
| Payments | Gateway، تلاش پرداخت، verify، reconciliation | Billing ports, Booking application interface |
| Wallet | اعتبار ناشی از لغو، دفترکل و مصرف اعتبار | Billing |
| Notifications | event-to-message، SMS، ارسال زمان‌بندی‌شده | Provider port |
| DynamicForms | تعریف فرم، نسخه، پاسخ | Patients/Booking IDs |
| Reporting | خواندن داده، خروجی Excel/PDF | read-model |
| SettingsAudit | تنظیمات نسخه‌دار، audit | همه از طریق interface |

**قاعده:** مدل‌ها/جدول‌های یک ماژول را دیگران مستقیماً تغییر ندهند؛ از service، query port یا event استفاده کنند. برای یک تراکنش رزرو و پرداخت حساس، هماهنگ‌کننده Application Service مالک atomic boundary خواهد بود. Eventهای داخل اپلیکیشن بعد از commit منتشر شوند (outbox یا after-commit).

## 3. ساختار پیشنهادی repository
```text
ani-doctor/
├── app/
│   ├── Http/Controllers/{Web,Patient,Admin,Secretary,Api}/
│   ├── Http/Requests/
│   ├── Policies/
│   ├── Modules/
│   │   ├── Identity/{Application,Domain,Infrastructure}/
│   │   ├── Patients/{Application,Domain,Infrastructure}/
│   │   ├── Catalog/{Application,Domain,Infrastructure}/
│   │   ├── Scheduling/{Application,Domain,Infrastructure}/
│   │   ├── Booking/{Application,Domain,Infrastructure}/
│   │   ├── Billing/{Application,Domain,Infrastructure}/
│   │   ├── Payments/{Application,Domain,Infrastructure}/
│   │   ├── Wallet/{Application,Domain,Infrastructure}/
│   │   ├── Notifications/{Application,Domain,Infrastructure}/
│   │   ├── DynamicForms/{Application,Domain,Infrastructure}/
│   │   ├── Reporting/{Application,Domain,Infrastructure}/
│   │   └── SettingsAudit/{Application,Domain,Infrastructure}/
│   ├── Providers/
│   └── Jobs/
├── bootstrap/           # استاندارد Laravel
├── config/              # config فقط با env
├── database/{migrations,factories,seeders}/
├── lang/fa/
├── public/{index.php,assets}/
├── resources/views/{layouts,website,patient,admin,secretary,components}/
├── routes/{web.php,console.php,api.php}/
├── storage/              # خارج از دسترس عمومی؛ داده اجرا در git نیست
├── tests/{Unit,Feature,Integration}/
├── .github/workflows/
├── docs/                 # تصمیم‌های فنی و سناریوها
├── artisan
├── composer.json
├── composer.lock
└── .env.example         # صرفاً مقادیر نمونه غیرواقعی
```
جزئیات مسیرهای bootstrap و routes باید با Laravel 13 سازگار باشد؛ مسیر api.php تنها در صورت نیاز ثبت شود. namespace هر ماژول با PSR-4/Composer autoload کار کند؛ اصل سادگی مهم‌تر از تولید کلاس‌های صوری است.

## 4. طراحی داده (فهرست اولیه، نه migration نهایی)
- `users`, `roles`, `permissions`, `user_roles`, `patient_user_links`: کاربر و مالکیت داده.
- `patients`: داده پایه حداقلی با شناسه مستقل؛ موبایل متعلق به شخصِ تماس ممکن است بین چند بیمار مشترک باشد.
- `services`, `service_price_histories`: نام، مدت، ظرفیت، تعرفه، تاریخچه.
- `weekly_schedules`, `work_shifts`, `schedule_exceptions`, `holidays`: برنامه، تعطیلات و بازگشایی استثنایی.
- `appointments`: شناسه، بیمار، رزروکننده، خدمت، start_at_utc، end_at_utc، status، payment_status_projection، price_snapshot، hold_expires_at، override_reason/actor، booking_channel، created_by؛ snapshots برای حفظ حقیقت تاریخی.
- `appointment_events`: immutable append-only، اقدام‌کننده، زمان، وضعیت قبل/بعد و علت.
- `payments`, `payment_attempts`, `payment_gateway_settings`, `payment_reconciliations`: وضعیت‌ها، شناسه یکتای درگاه، idempotency key، کارمزد و مبلغ با واحد دقیق.
- `wallet_accounts`, `wallet_entries`: دفترکل بستانکار/بدهکار با محدودیت تراکنشی و مرجع علت.
- `discounts`, `discount_redemptions`: تخفیف، رزرو تعداد استفاده در پرداخت/بازگشت امن.
- `forms`, `form_versions`, `form_fields`, `form_submissions`, `form_answers`: انتشار نسخه فرم و نگهداری پاسخ‌ها.
- `sms_templates`, `message_outbox`, `sms_delivery_logs`: صف و گزارش پیامک.
- `settings`, `audit_logs`: تنظیمات با نوع داده، دلیل تغییر و ممیزی.

**کلیدها و قیود:** InnoDB، foreign key، unique index روی provider+reference/transaction و idempotency، شاخص روی appointments(patient_id, start_at)، بازه و status، کلید یکتای wallet entry برای یک رویداد برگشت. حذف نرم منطقی برای نوبت‌ها؛ تراکنش مالی و audit حذف فیزیکی ندارند.

## 5. مدل تقویم و زمان
- تاریخ/ساعت منطقی در DB با UTC ذخیره شوند؛ نمایش در timezone `Asia/Tehran` و تبدیل جلالی فقط در ورودی/خروجی.
- محاسبه شروع/پایان خدمت با `duration_minutes + buffer_minutes` و محدودیت شیفت. تاریخ شمسی رشته‌ای، منبع حقیقت دیتابیس نیست.
- گام نمایش زمان از مدت خدمت جداست؛ مثلاً گام ۱۵ دقیقه با خدمت ۴۵ دقیقه.
- روز تعطیل بدون جایگزین = هیچ نوبت جدید عمومی؛ استثناهای برنامه اولویت مشخص و مستند دارند: override تاریخ، سپس تعطیلات، سپس برنامه هفتگی.
- در نسخه نخست شیفت‌های عبورکننده از نیمه‌شب به دو بخش تقسیم شوند تا قفل روزانه و تقویم دقیق باقی بماند.

## 6. کنترل ظرفیت هم‌زمان (ADR-002)
**یک پزشک، منبع مشترک زمان.** حتی با ظرفیت بیشتر از یک، نباید دو رزرو متداخل از ظرفیت مجاز عبور کنند.

الگوریتم در BookingService:
1. اعتبارسنجی نقش، خدمت، افق رزرو، محدودیت نوبت فعال و شرایط تعطیلی.
2. آغاز DB transaction.
3. قفل ردیف `booking_day_locks` برای (doctor ثابت، روز تقویمی مطب) با `SELECT ... FOR UPDATE`. ردیف برای هر تاریخ باید از قبل/به‌صورت امن ساخته شود. این قفل درخواست‌های آن روز را serialize می‌کند؛ از قفل‌کردن همه روزها پرهیز شود.
4. محاسبه دوباره availability از DB؛ رزروهای confirmed و pending با `hold_expires_at > now()` اشغال‌کننده‌اند؛ hold منقضی بدون انتظار Cron نادیده گرفته می‌شود.
5. بررسی ظرفیت در **تمام زیربازه‌های پوشش داده‌شده** با احتساب طول خدمات و بافر. نوبت‌های مجازِ خارج از ظرفیت با علامت override ثبت می‌شوند و مجوز عمومی رزرو اضافی ایجاد نمی‌کنند.
6. درج hold و نوبت و رویداد با idempotency key؛ commit.
7. تماس شبکه با درگاه **بیرون از تراکنش DB**؛ callback تأیید و نهایی‌سازی در تراکنش جداگانه.
8. تلاش شکست‌خورده به کلاینت پیام قابل فهم می‌دهد؛ هیچ double booking رخ نمی‌دهد.

برای ظرفیت متغیر و رزرو‌های با طول متفاوت، منطق شمارش صرفاً overlap count ساده بدون بررسی نقاط تغییر کافی نیست؛ از sweep-line/segmentation روی مرزهای بازه‌ها استفاده شود. قفل‌های روزانه فقط یک روش ابتدایی مناسب نسخه تک‌پزشک‌اند و با benchmark تأیید شوند.

## 7. وضعیت‌ها و گردش پرداخت (ADR-003)
اجرای P4: PaymentService مرز تسویه، FinancialBookingService مالک تغییر نوبت و BookingFinanceInterface اتصال لغو است. WalletService دفترکل و رزرو اعتبار، RefundService مسیر دستی بانکی و OfflinePaymentService حالت اضطراری/دریافت حضوری را پیاده می‌کنند. قفل و قرارداد جاری، به‌جای guard مالی موقت P3: [docs/P4.md](docs/P4.md).
**Booking lifecycle:** `pending_payment → confirmed` یا `pending_payment → expired`، `confirmed → cancelled/completed/no_show` با guardها. `confirmed_unpaid` به‌صورت `confirmed` + `balance_due>0` در حالت مجاز ثبت شود؛ از ضرب حالت‌های نامنسجم جلوگیری شود.

**Payment attempt lifecycle:** `created → redirected → verified` یا `failed/expired/requires_reconciliation`. نتیجه callback صرفاً ورودی غیرقابل‌اعتماد است؛ verify سرور-به-سرور/استعلام و قفل رکورد شرط نهایی‌سازی است.

**Expiry:** صف/Cron مرتباً رزروهای منقضی را علامت می‌زند، ولی شرط `hold_expires_at > server_now` در availability و پرداخت مبنای نهایی است. پرداخت دیرهنگام پس از انقضا/اشغال به `reconciliation_required` می‌رود، هرگز مجوز رزرو دوم نیست.

**Credit refund:** لغوِ مجاز یک entry بستانکار یکتا تولید می‌کند؛ برگشت دستی/بانکی چرخه و سند خودش را دارد؛ اعتبار قابل استفاده تنها به مالک احراز‌شده و با ledger تخصیص داده می‌شود. مجموع بدهکار/بستانکار برای هر رویداد قابل حسابرسی باشد.

**Offline fallback:** مدیر زمان فعال‌شدن و علت را مشخص می‌کند؛ رزرو در حالت اضطراری confirmed و unpaid می‌شود. یادداشت مالی منشی هیچ ادعای موفقیت بانکی ندارد.

## 8. API و قرارداد پاسخ AJAX
Endpoints JSON ترجیحاً زیر مسیر `/api/v1/` یا وب با prefix مشخص و middleware session/CSRF:
- `GET /api/v1/calendar?service_id=&month=` فقط ظرفیت/وضعیت بدون نام بیمار
- `GET /api/v1/availability?service_id=&date=`
- `POST /api/v1/appointments` با idempotency key
- `PATCH /api/v1/appointments/{id}/reschedule`
- `POST /api/v1/appointments/{id}/cancel`
- `POST /api/v1/payments/{appointment}/initiate`
- `POST /payments/zarinpal/callback` با تأیید مستقل و status code مناسب
- مسیر admin/secretary جدا و از سمت backend مجوزسنجی‌شده

پاسخ ثابت: `{success: boolean, data: object|null, error: {code, message, details?}|null, meta: {...}}`. هر timestamp با timezone مشخص؛ ریال/تومان صریح؛ هیچ داده شخصی در پاسخ تقویم عمومی.

## 9. توسعه‌پذیری Providerها
پورت‌های `SmsProviderInterface` (sendOtp، sendTemplate، getDeliveryStatus) و `PaymentGatewayInterface` (createPayment، verifyPayment، queryPayment، capabilities) در Domain/Application، adapterهای SmsIr و Zarinpal در Infrastructure. ورودی/خروجی adapter به DTO استاندارد تبدیل شوند. credential از .env/secret store، هرگز frontend یا repo. افزودن درگاه جدید بدون تغییر اساسی BookingService.

## 10. سازگاری و مهاجرت آینده
فقط یک پزشک و یک مطب در مدل جاری؛ service layer نباید پزشک یا نشانی را به‌صورت تکراری در تمام جداول hardcode کند. در صورت افزودن چندمطب، migration مجزا و نسخه‌بندی API لازم است؛ فعلاً complexity بی‌فایده ممنوع.

## 11. بسته نصب و محیط
Workflow در `.github/workflows/release-package.yml` پس از push و فقط وقتی `composer.json`, `composer.lock`, `artisan` و `public/index.php` موجودند، وابستگی production را نصب و ZIP تولید می‌کند. خروجی Laravel با `vendor` همراه است و **نیازی به Composer یا Node روی هاست در زمان نصب ندارد**؛ اما هاست باید PHP/extension/MySQL/Cron و مسیر امن DocumentRoot=public داشته باشد. `.env`، بکاپ/کلید، فایل پزشکی و `storage` اجرایی داخل artifact نباشند. راهنما: [DEPLOYMENT.md](DEPLOYMENT.md).

## 12. تست، کنترل نسخه و چرخه تغییر
- Unit: محاسبه شکاف زمانی، تقویم و تاریخ، قیمت/اعتبار.
- Feature: مجوزها، فرم‌ها، endpointها و سناریوهای PRD.
- Integration: race booking، callbacks idempotent، failure/late settlement، زمان‌بندی jobs، gateway sandbox.
- هر تغییر schema = migration قابل بازگشت + تست؛ هر API breaking = نسخه جدید/انتقال.
- در هر PR: اثر بر امنیت، rollback و تغییر سند مرتبط درج شود.

## 13. تصمیم‌های تثبیت‌شده
P3 reader واقعی Booking، قفل‌های هویت/بیمار/روز، current read در InnoDB، نسخه و idempotency را اضافه می‌کند؛ [قرارداد اجرایی و ترتیب قفل‌ها](docs/P3.md). شاخه P3 مسیر تأیید پرداخت یا ledger ندارد.

در P2، قرارداد reader رزرو، قفل تغییر برنامه، جداول و اولویت استثناها در [docs/P2.md](docs/P2.md) تشریح شده‌اند؛ reader واقعی Booking و تراکنش رزرو مربوط به P3 هستند.

وضعیت اجرایی P1، جداول واقعی و قفل OTP در [docs/P1.md](docs/P1.md) ثبت شده‌اند. جداول سایر ماژول‌ها در بخش ۴ هنوز طراحی اولیه‌اند.

- ADR-001 معماری یکپارچه ماژولار / SSR.
- ADR-002 قفل دیتابیس برای روز/ظرفیت، نه mutex سمت JS.
- ADR-003 جداسازی مالی از وضعیت رزرو و دفترکل اعتبار.
- ADR-004 تقویم جلالی فقط presentation، منبع حقیقت UTC.
- ADR-005 build بدون npm؛ assets از قبل آماده در public/assets.


## افزوده P5

P5: Booking هماهنگ‌کننده DeskBookingService، DeskReadService و AttendanceService است؛ Patients فهرست و پیوند صریح بیمار را مدیریت می‌کند؛ Billing تنها مالک رسید و تسویه است. ترتیب قفل P3/P4 حفظ شده است.


## افزوده P6

DynamicForms مالک انتشار نسخه و پاسخ رمزگذاری‌شده است؛ Notifications مالک outbox تراکنشی و ارسال پس از commit است. قرارداد OTP P1 مستقل می‌ماند؛ شبکه بیرون قفل رزرو/ارسال است. قراردادها و محدودیت exactly-once در docs/P6.md.

## قرارداد اجرایی P7
Billing/DiscountService قفل سهمیه و snapshot قیمت را در تراکنش Booking نگه می‌دارد؛ Reporting گزارش read transaction و خروجی حافظه‌ای می‌سازد. قفل کد بعد از بیمار/روز است و هیچ نوبت دیگر زیر قفل کد گرفته نمی‌شود. مهاجرت، معادله تطبیق و rollback: [docs/P7.md](docs/P7.md).

## P8 Operations
ابزار توسعه محلی پرداخت: LocalPaymentMode مرز local/loopback را کنترل می‌کند؛ LocalSimulatorGateway adapter مستقلی پشت PaymentGatewayInterface است. PaymentService براساس provider ذخیره‌شده هر attempt، بین adapter واقعی و شبیه‌سازی انتخاب می‌کند؛ schema و وابستگی جدیدی ندارد. وضعیت یک‌روزه شبیه‌ساز در cache مشترک و تصمیم زیر lock؛ نبود cache به unknown می‌رود. Controller فقط نمایش و فراخوانی adapter را هماهنگ می‌کند و settlement اصلی دست‌نخورده از حیث قفل/idempotency است.
ماژول Operations مسئول readiness کنسولی، بکاپ و restore جدا و کنترل بسته است؛ بدون migration یا route مدیریت جدید. فایل بکاپ/کلید/runtime وارد ZIP نمی‌شوند؛ رمزنگاری در Infrastructure و use case در Application است. [قرارداد و محدودیت‌ها](docs/P8.md).
