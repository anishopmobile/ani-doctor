<?php

namespace Tests\Feature;

use App\Modules\Reporting\Application\ReportExporter;
use App\Modules\Reporting\Application\ReportService;
use App\Modules\Reporting\Application\StaffNameService;
use App\Modules\Wallet\Application\RefundService;
use App\Modules\Wallet\Application\WalletService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\P7Fixtures;
use Tests\TestCase;

final class P7ReportsTest extends TestCase
{
    use DatabaseMigrations;
    use P7Fixtures;
    private $admin;
    private $patient;
    private $secretary;
    private $service;
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-26 08:00', 'Asia/Tehran'));
        $this->seed();
        $this->admin = $this->booker('09120000001', 'admin');
        $this->secretary = $this->booker('09120000002', 'secretary');
        $this->patient = $this->booker();
        $this->service = $this->bookingServiceFixture($this->admin);
        $this->offer();
        app(StaffNameService::class)->save($this->admin, $this->secretary->id, 'منشی ساختگی یکتا', 'تست');
    }
    public function test_secretary_has_operational_report_but_not_clinic_financial_report_and_patient_has_neither(): void
    {
        $this->actingAs($this->secretary)->get('/management/reports')->assertOk();
        $this->get('/management/reports?kind=finance')->assertForbidden();
        $this->get('/management/reports?kind=finance&format=xlsx')->assertForbidden();
        $this->actingAs($this->patient)->get('/management/reports')->assertForbidden();
        $this->get('/management/reports?format=pdf')->assertForbidden();
        $this->actingAs($this->admin)->get('/management/reports?kind=finance')->assertOk();
    }
    public function test_financial_cash_credit_and_refund_totals_reconcile_without_double_counting_wallet(): void
    {
        $first = $this->collect($this->discounted());
        $this->cancel($first);
        $refund = app(RefundService::class)->request($this->bookingActor($this->patient), 200000, (string) Str::uuid(), 'استرداد ساختگی');
        $admin = $this->bookingActor($this->admin);
        app(RefundService::class)->decide($admin, $refund->reference, 'processing', 'بررسی', null);
        $pending = app(ReportService::class)->build($this->admin, $this->reportFilters());
        $this->assertSame(200000, $pending['reconciliation']['استرداد در جریان ریال']);
        $this->assertSame(0, $pending['reconciliation']['اختلاف تطبیق ریال (باید صفر باشد)']);
        app(RefundService::class)->decide($admin, $refund->reference, 'completed', 'سند ساختگی، انتقال واقعی نیست', 'QA-REFUND-ONLY');
        $this->service->update(['price_irr' => 750000]);
        $second = $this->discounted();
        $this->actingAs($this->patient)->postJson('/api/v1/payments/'.$second->reference.'/initiate', ['credit_irr' => 600000, 'expected_version' => 1, 'idempotency_key' => (string) Str::uuid()])->assertSuccessful();
        $report = app(ReportService::class)->build($this->admin, $this->reportFilters());
        $this->assertSame(800000, $report['summary']['دریافت نقدی/بانکی بازه ریال']);
        $this->assertSame(600000, $report['summary']['مصرف اعتبار در بازه ریال (غیرنقدی)']);
        $this->assertSame(200000, $report['summary']['استرداد تکمیل‌شده بازه ریال']);
        $this->assertSame(600000, $report['summary']['خالص جریان نقد بازه ریال']);
        $this->assertSame(0, $report['reconciliation']['مانده دفترکل اعتبار ریال']);
        $this->assertSame(600000, $report['reconciliation']['ارزش تسویه‌شده نوبت لغونشده ریال']);
        $this->assertSame(0, $report['reconciliation']['اختلاف تطبیق ریال (باید صفر باشد)']);
        $this->assertSame(0, $report['reconciliation']['اختلاف مانده حساب و دفترکل ریال (باید صفر باشد)']);
        $this->assertContains('منشی ساختگی یکتا', $report['sections'][0]['rows'][0]);
    }
    public function test_unallocated_receipt_is_external_cash_and_liability_not_allocated_booking_income(): void
    {
        $appointment = $this->discounted();
        DB::transaction(function () use ($appointment): void {
            DB::table('payments')->insert(['appointment_id' => $appointment->id, 'provider' => 'zarinpal', 'provider_reference' => 'QA-LATE', 'amount_irr' => 100000, 'allocated' => false, 'created_at' => now()]);
            app(WalletService::class)->entry($appointment->booker_mobile_hash, 100000, 'qa-late', 'unallocated_payment', $appointment->id);
        });
        $report = app(ReportService::class)->build($this->admin, $this->reportFilters());
        $this->assertSame(100000, $report['summary']['دریافت تخصیص‌نیافته بازه ریال']);
        $this->assertSame(0, $report['reconciliation']['ارزش تسویه‌شده نوبت لغونشده ریال']);
        $this->assertSame(0, $report['reconciliation']['اختلاف تطبیق ریال (باید صفر باشد)']);
    }
    public function test_report_exposes_inconsistent_projection_instead_of_hiding_it(): void
    {
        $appointment = $this->collect($this->discounted());
        $this->cancel($appointment);
        DB::table('wallet_accounts')->increment('balance_irr', 1);
        $report = app(ReportService::class)->build($this->admin, $this->reportFilters());
        $this->assertSame(1, $report['reconciliation']['اختلاف مانده حساب و دفترکل ریال (باید صفر باشد)']);
        $this->assertSame(0, $report['reconciliation']['اختلاف تطبیق ریال (باید صفر باشد)']);
        $this->assertSame(1, $report['reconciliation']['تعداد حساب ناسازگار (باید صفر باشد)']);
    }
    public function test_opposite_wallet_discrepancies_do_not_cancel_out_in_account_level_check(): void
    {
        $appointment = $this->discounted();
        $owner = str_repeat('b', 64);
        DB::transaction(function () use ($appointment, $owner): void {
            app(WalletService::class)->entry($appointment->booker_mobile_hash, 100, 'qa-a', 'test_fixture', $appointment->id);
            app(WalletService::class)->entry($owner, 100, 'qa-b', 'test_fixture', $appointment->id);
        });
        DB::table('wallet_accounts')->where('owner_hash', $owner)->decrement('balance_irr', 1);
        DB::table('wallet_accounts')->where('owner_hash', $appointment->booker_mobile_hash)->increment('balance_irr', 1);
        $report = app(ReportService::class)->build($this->admin, $this->reportFilters());
        $this->assertSame(0, $report['reconciliation']['اختلاف مانده حساب و دفترکل ریال (باید صفر باشد)']);
        $this->assertSame(2, $report['reconciliation']['تعداد حساب ناسازگار (باید صفر باشد)']);
    }
    public function test_row_limit_never_silently_truncates_or_exports_a_partial_report(): void
    {
        $appointment = $this->discounted();
        $base = $appointment->getRawOriginal();
        unset($base['id']);
        foreach (array_chunk(range(1, 1000), 100) as $batch) {
            DB::table('appointments')->insert(array_map(fn () => [...$base, 'reference' => (string) Str::uuid()], $batch));
        }
        $filters = $this->reportFilters('appointments', 3);
        $query = http_build_query(array_intersect_key($filters, array_flip(['kind', 'from', 'to', 'sort', 'direction'])));
        $this->actingAs($this->admin)->getJson('/management/reports?'.$query)->assertUnprocessable();
        $this->assertDatabaseCount('report_access_events', 0);
    }
    public function test_operational_filters_include_effective_expiry_and_keep_stable_sorted_results(): void
    {
        $first = $this->discounted();
        $this->travel(16)->minutes();
        $second = $this->discounted(['start_at' => $first->start_at->addHour()->toIso8601String()]);
        $filters = [...$this->reportFilters('appointments', 3), 'status' => 'expired'];
        $report = app(ReportService::class)->build($this->admin, $filters);
        $this->assertCount(1, $report['sections'][0]['rows']);
        $this->assertSame($first->reference, $report['sections'][0]['rows'][0][0]);
        $report = app(ReportService::class)->build($this->admin, [...$filters, 'status' => 'pending_payment', 'operator_id' => $this->patient->id, 'sort' => 'amount', 'direction' => 'desc']);
        $this->assertSame($second->reference, $report['sections'][0]['rows'][0][0]);
        $this->assertDatabaseCount('report_access_events', 2);
    }
    public function test_finance_period_uses_tehran_midnight_receipts_and_opening_closing_balances(): void
    {
        $appointment = $this->collect($this->discounted());
        DB::table('payments')->where('appointment_id', $appointment->id)->update(['created_at' => '2026-10-25 20:29:59']);
        $report = app(ReportService::class)->build($this->admin, $this->reportFilters());
        $this->assertSame(0, $report['summary']['دریافت نقدی/بانکی بازه ریال']);
        $this->assertSame(800000, $report['summary']['مانده جریان نقد پیش از بازه ریال']);
        $this->assertSame(800000, $report['summary']['مانده جریان نقد تا پایان بازه ریال']);
        DB::table('payments')->where('appointment_id', $appointment->id)->update(['created_at' => '2026-10-25 20:30:00']);
        $report = app(ReportService::class)->build($this->admin, $this->reportFilters());
        $this->assertSame(800000, $report['summary']['دریافت نقدی/بانکی بازه ریال']);
    }
    public function test_invalid_report_dates_sort_and_financial_cohort_filters_are_rejected(): void
    {
        $this->actingAs($this->admin)->getJson('/management/reports?from=1405-13-01')->assertUnprocessable();
        $this->getJson('/management/reports?from=1403-01-01&to=1405-08-04')->assertUnprocessable();
        $this->getJson('/management/reports?sort=DROP_TABLE')->assertUnprocessable();
        $this->getJson('/management/reports?kind=finance&service_id='.$this->service->id)->assertUnprocessable();
        $this->getJson('/management/reports?from[]=x')->assertUnprocessable();
    }
    public function test_ticket_is_owned_and_omits_private_notes_service_and_medical_answers(): void
    {
        $appointment = $this->discounted();
        $appointment->forceFill(['override_reason' => 'PRIVATE-MEDICAL-NOTE', 'service_title' => 'PRIVATE-SERVICE-TITLE'])->save();
        $form = app(\App\Modules\DynamicForms\Application\PrivateFormService::class)->publish($this->admin, null, ['title' => 'PRIVATE-FORM', 'service_id' => $this->service->id, 'is_active' => true, 'expected_version' => 0, 'reason' => 'تست', 'schema' => [['key' => 'note', 'label' => 'اطلاعات ضروری', 'type' => 'text', 'required' => true]]]);
        app(\App\Modules\DynamicForms\Application\PrivateFormService::class)->submit($this->bookingActor($this->patient), $appointment, $form, ['version_id' => DB::table('form_versions')->sole()->id, 'expected_submission_id' => 0, 'idempotency_key' => (string) Str::uuid(), 'consent' => 1, 'answers' => ['note' => 'PRIVATE-ANSWER']]);
        $this->actingAs($this->patient)->get('/appointments/'.$appointment->reference.'/ticket')->assertOk()->assertSee('بیمار آزمایشی')->assertDontSee('PRIVATE-MEDICAL')->assertDontSee('PRIVATE-SERVICE')->assertDontSee('PRIVATE-ANSWER')->assertHeader('Cache-Control', 'no-store, private');
        $other = $this->booker('09120000003');
        $this->actingAs($other)->get('/appointments/'.$appointment->reference.'/ticket.pdf')->assertNotFound();
        $this->actingAs($this->secretary)->get('/appointments/'.$appointment->reference.'/ticket')->assertOk();
    }
    public function test_exports_are_real_xlsx_pdf_and_text_cannot_become_a_formula(): void
    {
        $appointment = $this->discounted(['patient_name' => '=HYPERLINK("https://example.invalid","ی/ک")']);
        $report = app(ReportService::class)->build($this->admin, $this->reportFilters('appointments', 3));
        $exporter = app(ReportExporter::class);
        $xlsx = $exporter->xlsx($report);
        $file = tempnam(sys_get_temp_dir(), 'p7-xlsx-');
        try {
            file_put_contents($file, $xlsx);
            $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
            $sheet = $book->getSheetByName('نوبت‌ها');
            $this->assertTrue($sheet->getRightToLeft());
            $this->assertSame('s', $sheet->getCell('C2')->getDataType());
            $this->assertSame('=HYPERLINK("https://example.invalid","ی/ک")', $sheet->getCell('C2')->getValue());
            $this->assertSame(800000, $sheet->getCell('F2')->getValue());
            $this->assertSame('n', $sheet->getCell('F2')->getDataType());
            $this->assertSame($appointment->reference, $sheet->getCell('A2')->getValue());
            $book->disconnectWorksheets();
        } finally {
            unlink($file);
        }
        $pdf = $exporter->pdf($report);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(5000, strlen($pdf));
        $query = http_build_query(array_intersect_key($this->reportFilters('appointments', 3), array_flip(['kind', 'from', 'to', 'sort', 'direction'])));
        $this->actingAs($this->secretary)->get('/management/reports?'.$query.'&format=xlsx')->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
    public function test_staff_names_are_admin_audited_and_cannot_modify_a_patient_account(): void
    {
        $data = ['user_id' => $this->secretary->id, 'display_name' => 'منشی نمونه', 'reason' => 'تست'];
        $this->actingAs($this->secretary)->post('/management/reports/staff-name', $data)->assertForbidden();
        $this->actingAs($this->admin)->post('/management/reports/staff-name', $data)->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $this->secretary->id, 'display_name' => 'منشی نمونه']);
        $this->post('/management/reports/staff-name', [...$data, 'user_id' => $this->patient->id])->assertUnprocessable();
        $this->assertDatabaseHas('configuration_audit_events', ['action' => 'report.staff_name']);
    }
    public function test_financial_database_errors_hide_bindings_and_private_tables_are_not_read(): void
    {
        $this->collect($this->discounted());
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        app(ReportService::class)->build($this->admin, $this->reportFilters());
        $this->assertStringNotContainsString('form_submissions', implode(' ', $queries));
        \Illuminate\Support\Facades\Log::spy();
        DB::listen(function ($query): void {
            if (str_contains($query->sql, 'payments') && str_starts_with(strtolower($query->sql), 'select')) {
                throw new \Illuminate\Database\QueryException('mysql', 'SELECT ? FROM payments', ['PRIVATE-FINANCE-BINDING'], new \PDOException('test failure'));
            }
        });
        $this->actingAs($this->admin)->getJson('/management/reports?kind=finance')->assertStatus(503)->assertDontSee('PRIVATE-FINANCE-BINDING');
        \Illuminate\Support\Facades\Log::shouldHaveReceived('error')->once()->with('booking.database_error', \Mockery::type('array'));
    }
    public function test_export_rate_limit_and_inactive_account_apply_before_generation(): void
    {
        $this->actingAs($this->admin);
        foreach (range(1, 5) as $unused) {
            $this->get('/management/reports?format=invalid')->assertSessionHasErrors('format');
        }
        $this->get('/management/reports?format=xlsx')->assertStatus(429);
        $this->admin->forceFill(['is_active' => false])->save();
        $this->get('/management/reports')->assertForbidden();
    }
}
