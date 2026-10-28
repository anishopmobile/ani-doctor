# COMPONENTS — کاتالوگ ماژول‌ها، سرویس‌ها و کامپوننت‌ها

افزوده بازطراحی یکپارچه: SiteContent مالک schema محدود متن و ترتیب صفحه پیش‌فرض؛ ThemeService مالک برند، رنگ‌ها، نسخه تنظیمات و CSS امن؛ MediaService مالک فایل و انتشار اسلات‌های هویتی؛ ServiceContentService مالک ترتیب عمومی خدمات است. Bladeهای panel-link، route-context، appointment-status و partialهای header/footer/contact ساختار مشترک را می‌سازند. design-system.css منبع توکن‌ها و ui.js فقط مسئول تعامل‌های نمایشی است. قرارداد و نقشه مصرف‌کننده‌ها: docs/UI-CMS-REDESIGN-AUDIT.md.
**نسخه:** 1.0.0 · **مطابقت با:** [ARCHITECTURE.md](ARCHITECTURE.md)، [PRD.md](PRD.md)

## 1. اصل طراحی
Component دو نوع دارد: **ماژول backend** با مسئولیت مشخص و **جزء UI** با قرارداد ورودی/خروجی. در پروژه تک‌پزشک، پیچیدگی ایجاد سرویس مستقل/میکروسرویس ممنوع است؛ Serviceها به‌صورت کلاس‌های قابل تزریق وابستگی در Laravel پیاده‌سازی شوند.

## 2. ماژول‌های Backend و قراردادهای عمومی
| کامپوننت | مسئولیت اصلی | ورودی | خروجی/رویداد | وابستگی |
|---|---|---|---|---|
| `AuthService` | OTP، ورود، session | موبایل، کد OTP | User، Authenticated | SmsProvider |
| `PatientService` | ساخت/ویرایش بیمار، اتصال حساب | مشخصات پایه | Patient | Identity |
| `CatalogService` | خدمت و تعرفه | service_id، قیمت/مدت | Service snapshot | Settings |
| `ScheduleService` | شیفت/تعطیلی/استثنا | روز، شیفت، استثنا | EffectiveSchedule | Catalog |
| `AvailabilityService` | محاسبه ساعت‌های قابل رزرو | روز، خدمت | Slot[]، reason | Schedule, Booking read-only |
| `BookingService` | hold و قطعی‌سازی نوبت | patient، service، slot، key | Appointment | Availability, Policies |
| `RescheduleService` | انتقال اتمیک نوبت | appointment، slot جدید | UpdatedAppointment | Booking |
| `CancellationService` | لغو، ظرفیت و اثر مالی | appointment، actor، reason | Cancelled, CreditRequested | Booking, Billing |
| `PricingService` | snapshot مبلغ، تخفیف | service، coupon | PriceBreakdown | Catalog, Discounts |
| `PaymentOrchestrator` | تلاش پرداخت و verify | appointment، gateway | PaymentAttempt, Verified/Failed | GatewayInterface |
| `WalletService` | اعتبار و برگشت | patient، amount، reference | LedgerEntry | Billing |
| `SmsService` | ارسال رویداد و OTP | template، recipient | DeliveryRef | SmsProviderInterface |
| `FormBuilderService` | فرم، نسخه و فیلد | form_definition | PublishedFormVersion | Policies |
| `FormSubmissionService` | اعتبارسنجی پاسخ | form_version، values | Submission | Forms, Patients |
| `DiscountService` | محدودیت و استفاده | code، patient، service | DiscountResult | Pricing |
| `ReportService` | داده گزارش با مجوز | filters، actor | PagedResult / Export | Read models |
| `AuditService` | ممیزی تغییرات | actor، object، before/after | AuditEvent | storage |
| `SettingsService` | تنظیمات تایپ‌شده | key، value، actor | SettingChanged | Audit |

نام کلاس‌های فوق **قرارداد معماری پیشنهادی** هستند، نه کد نوشته‌شده؛ در پیاده‌سازی می‌توان آنها را به use-caseهای کوچک‌تر تقسیم کرد بدون تغییر مسئولیت.

## 3. قراردادهای کلیدی داده
### 3.1 AvailabilitySlot (DTO)
اجرای P2: AvailabilityService، SlotCalculator و BookingReadInterface در ماژول Scheduling؛ [قراردادهای اجرایی](docs/P2.md). فیلد end_at_utc پایان خدمت است؛ طول اشغال برای کنترل ظرفیت شامل buffer است.

```text
start_at_utc: ISO8601
end_at_utc: ISO8601
local_date_jalali: YYYY/MM/DD
local_start_time: HH:mm
available_capacity: integer >= 0
is_bookable: boolean
unavailable_reason?: holiday|full|past|outside_window|closed
```
داده‌های این DTO نباید نام/موبایل بیمار دیگر را افشا کنند. ظرفیت در زمان ثبت نهایی دوباره بررسی شود.

### 3.2 Appointment (نمای عمومی)
اجرای P3 در BookingService و AppointmentReader، با AppointmentPolicy و قرارداد هویت Identity است. قرارداد درخواست‌ها و claim: [docs/P3.md](docs/P3.md). عملیات مالی لغو پرداخت‌شده به P4 وابسته و فعلاً مسدود است.

```text
reference: opaque string
service_title: string
start_local: date/time
end_local: date/time
booking_status: enum
payment_status: enum
amount_display: string + currency
can_reschedule: boolean
can_cancel: boolean
can_retry_payment: boolean
```
دسترسی به جزئیات فقط پس از authorization. `can_...` صرفاً نشان UI است؛ backend مجوز را مستقل بررسی می‌کند.

### 3.3 PriceBreakdown
```text
currency: IRR (internal integer units; public display may be IRT)
service_amount: integer
discount_amount: integer
credit_applied: integer
amount_due: integer
total: integer
pricing_snapshot_id: string
```
همواره: `total = service_amount - discount_amount` و `amount_due = total - credit_applied`، مقدارها غیرمنفی، rounding به‌طور یکنواخت؛ استرداد و مانده در دفترکل مستقل.

### 3.4 GatewayPort
در P4، PaymentGatewayInterface دارای createPayment/verifyPayment/queryPayment/redirectUrl/capabilities و adapter زرین‌پال است؛ مبلغ IRR صحیح است. نتیجه inquiry به‌تنهایی قطعی‌سازی نمی‌کند. receipt/attempt/credit/refund و مالکیت شماره رزروکننده در [قرارداد P4](docs/P4.md) مشخص‌اند؛ لغو پرداخت‌شده اکنون با رسید معتبر در همان تراکنش به اعتبار برمی‌گردد.
```text
createPayment(PaymentRequest): PaymentRedirect
verifyPayment(VerifiedCallbackContext): GatewayVerification
queryPayment(ProviderReference): PaymentStatus
```
تمام adapterها باید reference/token را از یک منبع معتبر سروری بگیرند. Provider callback به نتیجه internal normalized تبدیل می‌شود، نه اینکه مستقیم appointment را تغییر دهد.

### 3.5 SmsPort
پورت اجرایی P1 فقط `Identity/Domain/SmsProviderInterface::sendOtp` را دارد؛ سایر عملیات زیر برای P6 هستند. [جزئیات](docs/P1.md).

```text
sendOtp(recipient, template, opaqueCode): MessageResult
sendTemplate(recipient, templateId, safeVariables): MessageResult
queryDelivery(providerMessageId): DeliveryStatus
```
کد OTP و اطلاعات حساس در log/error response قرار نمی‌گیرند.

## 4. رویدادهای دامنه و اثرات جانبی
| رویداد | انتشار | مصرف‌کننده نمونه |
|---|---|---|
| `AppointmentHeld` | بعد از commit | شمارش معکوس/UI |
| `AppointmentConfirmed` | بعد از commit | SMS/Reporting |
| `AppointmentExpired` | پس از پایان hold | Availability/metrics |
| `AppointmentRescheduled` | بعد از commit | SMS/Audit |
| `AppointmentCancelled` | بعد از commit | SMS/Wallet |
| `PaymentVerified` | idempotent بعد از verification | Booking/Billing |
| `PaymentNeedsReconciliation` | ناسازگاری | Admin/Finance |
| `WalletCredited` | فقط یک بار برای هر علت | Billing/Notification |
| `OfflineBookingModeChanged` | تغییر مدیر | Audit/Admin badge |
| `ScheduleChanged` | تغییر شیفت/تعطیل | Calendar cache invalidation |

در انتشار پس از commit از outbox/queue یا dispatchAfterCommit استفاده شود تا پیامک قبل از تثبیت رزرو ارسال نشود.

## 5. UI Component Catalog
| نام | ورودی | خروجی/تعامل | حالت‌های ضروری |
|---|---|---|---|
| `ServiceSelector` | services، selectedId | select service | disabled/loading |
| `InlineJalaliCalendar` | month، dayStatus، min/max | select day | holiday/full/selected/today |
| `AvailabilitySlots` | slots، selectedSlot | select slot | loading/empty/error |
| `PatientForm` | requiredFields، initialData | validated payload | invalid/complete |
| `OtpVerification` | mobile، countdown | verified identity | resend/expired/limited |
| `BookingSummary` | service، slot، price | confirm | price changed |
| `HoldCountdown` | holdExpiresAt/serverNow | expired event | expired/active |
| `GatewayChoice` | active gateways | choose gateway | one(auto)/many/none |
| `AppointmentCard` | appointment view model | view/edit/cancel/print | each status |
| `RescheduleDialog` | appointment، availability | request change | conflict/success |
| `CancelDialog` | cancellation quote | confirm cancel | credit/refund rule |
| `PaymentStatusBadge` | paymentStatus | display only | pending/paid/error/unpaid |
| `WalletBalance` | wallet summary | history | empty/loaded |
| `AdminCalendar` | schedule، appointments | admin action | exceptional/holiday |
| `AppointmentTable` | paginated data/filter | open/edit/print | empty/loading |
| `PatientSearch` | query | patient selected | no result/duplicate name |
| `OfflineModeBanner` | enabled، reason | admin sees state | warning |
| `AuditTimeline` | events | display | authorized only |
| `DataFilters` | filter schema | changed filters | reset/loading |
| `ExportMenu` | current filters | request PDF/Excel | async/error |
| `Toast/ConfirmModal` | message، severity | acknowledge/confirm | focus managed |

**UI ownership:** Blade partial/components for markup and Vanilla JS modules for behavior; JavaScript only calls backend endpoints. Global mutable application state and duplicate widget logic ممنوع است.

## 6. نمونه مرز API برای رزرو
**POST `/api/v1/appointments`:** payload دارای patient info/reference، service_id، slot start ISO و idempotency_key. نیازمند OTP verified/session مناسب و CSRF در session routes.

**پاسخ 201:** `appointment.reference`, `booking_status=pending_payment|confirmed`, `hold_expires_at`, `payment.amount_due`, `next_action`.

**خطاهای دامنه:**
- `SLOT_UNAVAILABLE` = ظرفیت تمام شده
- `DATE_CLOSED` = روز تعطیل
- `BOOKING_LIMIT_REACHED` = سقف فعال
- `EDIT_WINDOW_CLOSED` = دیرتر از محدودیت
- `HOLD_EXPIRED` = پایان مهلت
- `PAYMENT_VERIFICATION_PENDING` = پرداخت نامشخص
- `PAYMENT_ALREADY_FINALIZED` = تلاش تکراری
- `FORBIDDEN` = سطح دسترسی ناکافی
- `VALIDATION_ERROR` = ورودی نامعتبر

کد خطا پایدار و زبان‌مستقل است؛ متن پیام کاربر فارسی و قابل تغییر.

## 7. قراردادهای ACL و تنظیمات
- `appointments.create.override_capacity`: مدیر و منشی مجاز.
- `appointments.cancel.override_window`: منشی مجاز با دلیل / مدیر.
- `payments.record_cash`: منشی؛ مدیر فقط با policy صریح دیگری.
- `payments.toggle_offline_mode`: فقط مدیر.
- `wallet.refund`: مدیر/اپراتور مالی مجاز، با audit.
- `forms.responses.view_sensitive`: مجوز مجزا و حداقلی.
- `settings.update`: مدیر.

کلیدهای Settings نمونه: `booking_horizon_days=30`, `reschedule_cutoff_hours=24`, `cancellation_cutoff_hours=24`, `hold_ttl_minutes=15`, `max_active_appointments=2`, `default_slot_capacity=1`, `online_payment_enabled=true`, `emergency_pay_at_clinic=false`, `refund_default=wallet_credit`.

## 8. قواعد آماده‌بودن Component
هر جزء باید مالک مشخص، ورودی/خروجی، خطاهای دامنه، authorization، تست، مکان نمایش و دسترس‌پذیری داشته باشد. UI بدون endpoint واقعی نباید داده رزرو ساختگی را به‌عنوان قطعی نمایش دهد. Provider هرگز نباید Controller بیمار را به پیاده‌سازی زرین‌پال یا SMS.ir گره بزند.


## افزوده P5

P5: میز کار منشی/مدیر، فهرست پایه بیمار، رزرو تلفنی/فوری و حضور افزوده شد. قرارداد Serviceها، مسیرها، مجوزها و ماشین وضعیت در docs/P5.md ثبت شده است.


## افزوده P6

FormSchema و PrivateFormService، EventSmsProviderInterface/SmsIrEventProvider و NotificationOutbox/MessageDispatcher/SmsSettingsService افزوده شدند. مسیرها، مجوز مستقل پاسخ و retry محدود در docs/P6.md ثبت‌اند؛ فایل پزشکی طبق مرز PRD مجاز نیست.

## اجزای P7
DiscountService (quote/reserve/transition)، ReportService (فیلتر، snapshot و audit)، ReportExporter (mPDF/PhpSpreadsheet)، TicketService (مالکیت و برگه بدون پاسخ پزشکی) و StaffNameService (نام واقعی با دلیل) در Service Layer هستند. برآورد AJAX مصرفی ایجاد نمی‌کند. قراردادها: [docs/P7.md](docs/P7.md).

## P8
ابزار توسعه لوکال: LocalPaymentMode، LocalSimulatorGateway، LocalPaymentController/Request و Blade درگاه دو نتیجه‌ای؛ فقط localhost مستقیم با flag صریح، بدون فرم کارت/شبکه بانک. مالکیت nonce نشست، CSRF، provider مستقل و گزارش با هشدار. راه روشن/خاموش و نصب patch در docs/INSTALL-LOCAL.md.
ProductionGuard و ConfiguredProxies مرز محیط HTTP؛ Operations/ReadinessService، BackupService، PackageVerifier و Infrastructure/EncryptedArchive/MysqlSnapshot/PrivatePaths مسئول عملیات کنسولی‌اند. domain/ProxyAddresses قواعد اعتماد IP را مشترک نگه می‌دارد. هیچ API وب برای بکاپ یا restore وجود ندارد؛ [قرارداد](docs/P8.md).

## Visual CMS components
Website PageSchema/PageService, WebsitePage/Revision/Asset, admin WebsitePageController and public PublicPageController implement bounded block authoring, versioned publication and private images. Vanilla page-builder.js uses DOM textContent and same-origin Fetch; page-builder.css requires no build. WebsiteThemeCssController serves validated palette CSS under strict CSP; brand logo reuses MediaService.
