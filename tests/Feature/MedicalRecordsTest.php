<?php

namespace Tests\Feature;

use App\Modules\MedicalRecords\Application\ClinicalService;
use App\Modules\MedicalRecords\Application\ProfileService;
use App\Modules\MedicalRecords\Infrastructure\ClinicalRecord;
use App\Modules\MedicalRecords\Infrastructure\MedicalDocument;
use App\Modules\MedicalRecords\Infrastructure\MedicalProfile;
use App\Modules\Patients\Infrastructure\Patient;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Support\ClinicFixtures;
use Tests\TestCase;

final class MedicalRecordsTest extends TestCase
{
    use RefreshDatabase;
    use ClinicFixtures;

    private $admin;
    private $secretary;
    private $service;
    private Patient $patient;
    private string $scratch;
    private string $originalStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->travelTo(CarbonImmutable::parse('2026-10-30 08:00', 'Asia/Tehran'));
        $this->admin = $this->booker('09120000011', 'admin');
        $this->secretary = $this->booker('09120000012', 'secretary');
        $this->patient = Patient::create(['name' => 'بیمار کاملاً ساختگی الف', 'contact_mobile' => '09120000013']);
        $this->scratch = sys_get_temp_dir().'/ani-emr-'.bin2hex(random_bytes(12));
        mkdir($this->scratch.'/app/private', 0700, true);
        $this->originalStorage = storage_path();
        // Keep compiled views in the application runtime; only files are isolated.
        config(['view.compiled' => storage_path('framework/views')]);
        $this->app->useStoragePath($this->scratch);
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        $this->app->useStoragePath($this->originalStorage);
        File::deleteDirectory($this->scratch);
        parent::tearDown();
    }

    private function url(string $suffix = ''): string
    {
        return '/management/medical-records/'.$this->patient->id.$suffix;
    }

    private function data(array $payload = ['complaint' => 'شرح ساختگی حساس'], array $changes = []): array
    {
        return ['payload' => $payload, 'request_key' => (string) Str::uuid(), 'occurred_jalali' => '1405-08-08', 'occurred_time' => '09:30', 'expected_version' => 0, ...$changes];
    }

    private function visit(array $changes = []): ClinicalRecord
    {
        $this->post($this->url('/records/visit'), $this->data(changes: $changes))->assertRedirect();
        return ClinicalRecord::latest('id')->firstOrFail();
    }

    public function test_roles_are_unchanged_and_secretary_cannot_read_or_mutate_any_clinical_endpoint(): void
    {
        $record = $this->visit();
        $this->assertSame(['admin', 'patient', 'secretary'], DB::table('roles')->orderBy('name')->pluck('name')->all());
        $this->actingAs($this->secretary)->get($this->url())->assertOk()->assertDontSee('شرح ساختگی حساس')->assertDontSee('تاریخچه بالینی')->assertDontSee('حساسیت:');
        foreach (['/new/visit', '/records/'.$record->id, '/audit', '/print', '/documents/999'] as $suffix) {
            $this->get($this->url($suffix))->assertForbidden();
        }
        $this->post($this->url('/records/visit'), $this->data())->assertForbidden();
        $this->put($this->url('/records/'.$record->id), $this->data())->assertForbidden();
        $this->post($this->url('/records/'.$record->id.'/sign'), ['expected_version' => 1])->assertForbidden();
        $this->post($this->url('/documents/999/review'), ['status' => 'reviewed'])->assertForbidden();
        $this->assertDatabaseHas('configuration_audit_events', ['action' => 'emr.access']);
    }

    public function test_public_patient_guest_inactive_and_unverified_users_are_denied(): void
    {
        $this->app['auth']->forgetGuards();
        $this->get($this->url())->assertRedirect('/login');
        $user = $this->booker('09120000014');
        $this->actingAs($user)->get($this->url())->assertForbidden();
        $this->post($this->url('/documents'), [])->assertForbidden();
        $this->admin->forceFill(['is_active' => false])->save();
        $this->actingAs($this->admin)->get($this->url())->assertForbidden();
        $this->admin->forceFill(['is_active' => true, 'mobile_verified_at' => null])->save();
        $this->get($this->url())->assertForbidden();
    }

    public function test_admin_can_render_all_real_editors_and_pages(): void
    {
        $this->get('/management/medical-records')->assertOk();
        $this->get('/management/medical-records/new')->assertOk();
        $this->get($this->url('/identity'))->assertOk();
        foreach (array_keys(\App\Modules\MedicalRecords\Application\ClinicalSchema::labels()) as $kind) {
            $this->get($this->url('/new/'.$kind))->assertOk()->assertSee('ذخیره پیش‌نویس واقعی');
        }
        $this->get($this->url())->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get($this->url('/audit'))->assertOk();
        $this->get($this->url('/print'))->assertOk();
    }

    public function test_visit_draft_final_amendment_and_locking_preserve_original(): void
    {
        $record = $this->visit();
        $this->put($this->url('/records/'.$record->id), $this->data(['complaint' => 'اصلاح پیش‌نویس'], ['expected_version' => 1]))->assertRedirect();
        $this->put($this->url('/records/'.$record->id), $this->data(['complaint' => 'تعارض'], ['expected_version' => 1]))->assertConflict();
        $this->post($this->url('/records/'.$record->id.'/sign'), ['expected_version' => 2])->assertRedirect();
        $this->post($this->url('/records/'.$record->id.'/sign'), ['expected_version' => 2])->assertRedirect();
        $this->put($this->url('/records/'.$record->id), $this->data(['complaint' => 'بازنویسی ممنوع'], ['expected_version' => 3]))->assertConflict();
        $this->post($this->url('/records/visit'), $this->data(['complaint' => 'الحاقیه ساختگی'], ['parent_id' => $record->id]))->assertSessionHasErrors('reason');
        $this->post($this->url('/records/visit'), $this->data(['complaint' => 'الحاقیه ساختگی'], ['parent_id' => $record->id, 'reason' => 'تصحیح مستند پزشک']))->assertRedirect();
        $amendment = ClinicalRecord::latest('id')->first();
        $this->assertSame($record->id, $amendment->parent_id);
        $this->assertSame('اصلاح پیش‌نویس', $record->fresh()->payload['complaint']);
        $this->assertSame('final', $record->fresh()->status);
        $this->post($this->url('/records/visit'), $this->data(changes: ['parent_id' => $record->id, 'reason' => 'تکراری']))->assertConflict();
    }

    public function test_idempotency_is_scoped_to_patient_and_payload(): void
    {
        $data = $this->data();
        $this->post($this->url('/records/visit'), $data)->assertRedirect();
        $this->post($this->url('/records/visit'), $data)->assertRedirect();
        $this->assertDatabaseCount('emr_records', 1);
        $this->post($this->url('/records/visit'), [...$data, 'payload' => ['complaint' => 'مختلف']])->assertConflict();
    }

    public function test_record_document_and_episode_cross_patient_links_are_denied(): void
    {
        $record = $this->visit();
        $other = Patient::create(['name' => 'بیمار ساختگی ب']);
        $this->get('/management/medical-records/'.$other->id.'/records/'.$record->id)->assertNotFound();
        $this->post('/management/medical-records/'.$other->id.'/records/note', $this->data(['body' => 'متن'], ['visit_id' => $record->id]))->assertNotFound();
        $this->post($this->url('/records/note'), $this->data(['body' => 'متن'], ['episode_id' => $record->id]))->assertNotFound();
    }

    public function test_payload_is_encrypted_xss_escaped_and_audit_contains_no_clinical_text(): void
    {
        $record = $this->visit();
        $this->assertStringNotContainsString('شرح ساختگی حساس', DB::table('emr_records')->value('payload'));
        $this->assertStringNotContainsString('شرح ساختگی حساس', json_encode(DB::table('configuration_audit_events')->get(), JSON_UNESCAPED_UNICODE));
        $this->assertArrayNotHasKey('payload', $record->toArray());
        $this->put($this->url('/records/'.$record->id), $this->data(['complaint' => '<script>alert(1)</script>'], ['expected_version' => 1]))->assertRedirect();
        $this->get($this->url('/records/'.$record->id))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->post($this->url('/records/visit'), $this->data(['complaint' => 'x', 'created_by' => 999]))->assertSessionHasErrors('payload');
    }

    public function test_unknown_negative_and_positive_allergies_remain_distinct(): void
    {
        foreach (['unknown', 'negative', 'positive'] as $status) {
            $this->post($this->url('/records/allergy'), $this->data(['observation' => $status, 'severity' => 'unknown', 'source' => 'patient']))->assertRedirect();
        }
        $this->assertSame(['unknown', 'negative', 'positive'], ClinicalRecord::orderBy('id')->get()->map(fn ($r) => $r->payload['observation'])->all());
    }

    public function test_profile_shared_contact_duplicate_acknowledgment_encrypted_identity_and_version(): void
    {
        $data = ['name' => 'بیمار ساختگی جدید', 'contact_mobile' => $this->patient->contact_mobile, 'identity_type' => 'passport', 'identity_number' => 'SYNTHETIC-PASS-1', 'marital_status' => 'unknown', 'status' => 'active', 'expected_version' => 0];
        $this->actingAs($this->secretary)->post('/management/medical-records', $data)->assertSessionHasErrors('duplicate_ack');
        $this->post('/management/medical-records', [...$data, 'duplicate_ack' => '1'])->assertRedirect();
        $profile = MedicalProfile::firstOrFail();
        $patient = Patient::findOrFail($profile->patient_id);
        $this->assertStringStartsWith('EMR-', $patient->file_number);
        $this->assertSame($this->patient->contact_mobile, $patient->contact_mobile);
        $this->assertStringNotContainsString('SYNTHETIC-PASS-1', DB::table('emr_profiles')->value('identity'));
        $this->assertSame($patient->id, app(ProfileService::class)->search(['query' => 'SYNTHETIC-PASS-1'])->sole()->id);
        $this->put('/management/medical-records/'.$patient->id.'/identity', [...$data, 'duplicate_ack' => '1'])->assertConflict();
        $this->assertDatabaseCount('patient_user_links', 0);
    }

    public function test_search_normalization_sql_metacharacters_and_filters_are_server_side(): void
    {
        $this->patient->update(['name' => 'كيميا ساختگی']);
        $this->assertSame($this->patient->id, app(ProfileService::class)->search(['query' => 'کیمیا'])->sole()->id);
        $this->assertSame(0, app(ProfileService::class)->search(['query' => "%' OR 1=1 --"])->count());
        $this->post('/management/medical-records/search', ['query' => $this->patient->name])->assertRedirect('/management/medical-records');
        $this->assertStringNotContainsString($this->patient->name, session()->previousUrl() ?? '');
    }

    public function test_secretary_upload_does_not_expose_title_original_name_content_or_clinical_links(): void
    {
        $this->actingAs($this->secretary)->post($this->url('/documents'), ['file' => UploadedFile::fake()->image('SECRET-DIAGNOSIS.png'), 'request_key' => (string) Str::uuid(), 'title' => 'SECRET-TITLE', 'visit_id' => 999])->assertRedirect();
        $document = MedicalDocument::firstOrFail();
        $this->assertSame([], $document->metadata);
        $this->assertNull($document->visit_id);
        $this->get($this->url())->assertOk()->assertDontSee('SECRET')->assertDontSee('دانلود خصوصی');
        $this->get($this->url('/documents/'.$document->id))->assertForbidden();
        $encrypted = file_get_contents(storage_path('app/private/emr/'.$document->path));
        $this->assertStringNotContainsString("\x89PNG", $encrypted);
        $this->actingAs($this->admin)->get($this->url('/documents/'.$document->id))->assertOk()->assertDownload('document-'.$document->id.'.png')->assertHeader('Content-Security-Policy', "sandbox; default-src 'none'; frame-ancestors 'none'");
        $other = Patient::create(['name' => 'ساختگی دیگر']);
        $this->get('/management/medical-records/'.$other->id.'/documents/'.$document->id)->assertNotFound();
        $this->post($this->url('/documents/'.$document->id.'/review'), ['status' => 'archived'])->assertRedirect();
        $this->assertSame('archived', $document->fresh()->status);
        $this->assertFileExists(storage_path('app/private/emr/'.$document->path));
    }

    public function test_forged_extensions_invalid_signature_and_oversize_uploads_are_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent('report.pdf', '<?php echo "bad";');
        $this->post($this->url('/documents'), ['file' => $file, 'request_key' => (string) Str::uuid()])->assertSessionHasErrors('file');
        $image = UploadedFile::fake()->image('image.php');
        $this->post($this->url('/documents'), ['file' => $image, 'request_key' => (string) Str::uuid()])->assertSessionHasErrors('file');
        $this->post($this->url('/documents'), ['file' => UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf'), 'request_key' => (string) Str::uuid()])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('emr_documents', 0);
    }

    public function test_pregnancy_unknown_multiple_episodes_and_dating_reference_boundaries(): void
    {
        $unknown = ['episode_status' => 'unknown', 'dating_source' => 'unknown'];
        $this->post($this->url('/records/pregnancy'), $this->data($unknown))->assertRedirect();
        $episode = ClinicalRecord::latest('id')->first();
        $this->assertNull(app(ClinicalService::class)->gestation($episode, now()));
        $payload = ['episode_status' => 'active', 'dating_source' => 'ultrasound', 'dating_date' => '1405-08-08', 'gestation_days' => 280, 'dating_decision' => 'تصمیم ساختگی پزشک', 'fetuses' => 2];
        foreach ([1, 2] as $unused) {
            $this->post($this->url('/records/pregnancy'), $this->data($payload))->assertRedirect();
        }
        $episode = ClinicalRecord::latest('id')->first();
        $estimate = app(ClinicalService::class)->gestation($episode, now());
        $this->assertSame(['weeks' => 40, 'days' => 0, 'reference' => '1405-08-08'], $estimate);
        $this->assertSame(1, app(ClinicalService::class)->gestation($episode, now()->addDay())['days']);
        $lmp = new ClinicalRecord(['payload' => ['dating_source' => 'lmp', 'lmp' => '2026-10-23']]);
        $this->assertSame(1, app(ClinicalService::class)->gestation($lmp, now())['weeks']);
        $this->assertNull(app(ClinicalService::class)->gestation($lmp, now()->subDays(8)));
        $this->assertNull(app(ClinicalService::class)->gestation($lmp, now()->addDays(316)));
        $this->post($this->url('/records/pregnancy'), $this->data([...$unknown, 'edd' => '1406-01-01']))->assertSessionHasErrors('payload.dating_source');
        $this->post($this->url('/records/pregnancy'), $this->data([...$payload, 'dating_date' => '1405-12-30']))->assertSessionHasErrors();
        $this->post($this->url('/records/followup'), $this->data(['findings' => 'ساختگی']))->assertSessionHasErrors('episode_id');
    }

    public function test_prescription_requires_visit_and_confidential_notes_are_excluded_from_print(): void
    {
        $this->post($this->url('/records/prescription'), $this->data(['name' => 'داروی ساختگی', 'source' => 'physician']))->assertSessionHasErrors('visit_id');
        $visit = $this->visit();
        $this->post($this->url('/records/prescription'), $this->data(['name' => 'داروی ساختگی', 'source' => 'physician'], ['visit_id' => $visit->id]))->assertRedirect();
        $this->post($this->url('/records/note'), $this->data(['body' => 'PRIVATE-NOTE-SYNTHETIC']))->assertRedirect();
        $note = ClinicalRecord::latest('id')->first();
        $this->post($this->url('/records/'.$note->id.'/sign'), ['expected_version' => 1])->assertRedirect();
        $this->get($this->url('/print'))->assertOk()->assertDontSee('PRIVATE-NOTE-SYNTHETIC');
    }

    public function test_appointment_visit_requires_actual_patient_attendance_and_is_unique(): void
    {
        $this->service = $this->bookingServiceFixture($this->secretary);
        $appointment = $this->clinicAppointment(['start_at' => now('Asia/Tehran')->setTime(9, 0)->utc()->toIso8601String()]);
        $this->patient = $appointment->patient;
        $data = $this->data(changes: ['appointment_id' => $appointment->id]);
        $this->post($this->url('/records/visit'), $data)->assertUnprocessable();
        $this->actingAs($this->secretary)->postJson('/management/appointments/'.$appointment->reference.'/attendance', $this->attendanceData($appointment, 'arrived'))->assertSuccessful();
        $this->actingAs($this->admin)->post($this->url('/records/visit'), $data)->assertRedirect();
        $this->post($this->url('/records/visit'), $this->data(changes: ['appointment_id' => $appointment->id]))->assertRedirect();
        $this->assertSame(1, ClinicalRecord::where('appointment_id', $appointment->id)->count());
        $this->assertSame('confirmed', $appointment->fresh()->status);
        $this->assertSame($appointment->booker_mobile_hash, $appointment->fresh()->booker_mobile_hash);
    }

    public function test_populated_migration_refuses_destructive_rollback(): void
    {
        $this->visit();
        $migration = require database_path('migrations/2026_10_30_180000_create_medical_records.php');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('EMR_DATA_PRESENT');
        $migration->down();
    }

    public function test_csrf_is_required_on_every_mutation_when_framework_test_bypass_is_disabled(): void
    {
        $record = $this->visit();
        $this->app->instance('env', 'local');
        $this->post('/management/medical-records', [])->assertStatus(419);
        $this->post('/management/medical-records/search', [])->assertStatus(419);
        $this->put($this->url('/identity'), [])->assertStatus(419);
        $this->post($this->url('/records/visit'), $this->data())->assertStatus(419);
        $this->put($this->url('/records/'.$record->id), [])->assertStatus(419);
        $this->post($this->url('/records/'.$record->id.'/sign'), [])->assertStatus(419);
        $this->post($this->url('/documents'), [])->assertStatus(419);
        $this->post($this->url('/documents/999/review'), [])->assertStatus(419);
    }

    public function test_patient_cannot_reach_any_emr_endpoint_even_with_guessed_ids_and_granted_patient_permission(): void
    {
        $user = $this->booker('09120000014');
        $role = \App\Modules\Identity\Infrastructure\Role::where('name', 'patient')->sole();
        $role->permissions()->attach(\App\Modules\Identity\Infrastructure\Permission::where('name', 'patients.manage')->sole());
        $this->actingAs($user);
        foreach (['', '/identity', '/new/note', '/records/999', '/documents/999', '/audit', '/print'] as $suffix) {
            $this->get($this->url($suffix))->assertForbidden();
        }
        foreach (['/search', '', '/'.$this->patient->id.'/documents', '/'.$this->patient->id.'/records/visit', '/'.$this->patient->id.'/records/999/sign', '/'.$this->patient->id.'/documents/999/review'] as $suffix) {
            $this->post('/management/medical-records'.$suffix, [])->assertForbidden();
        }
        $this->put($this->url('/identity'), [])->assertForbidden();
        $this->put($this->url('/records/999'), [])->assertForbidden();
    }

    public function test_document_retry_is_idempotent_and_accepted_bytes_match_download_audit(): void
    {
        $file = UploadedFile::fake()->image('synthetic.png');
        $originalHash = hash_file('sha256', $file->getRealPath());
        $data = ['file' => $file, 'request_key' => (string) Str::uuid(), 'title' => 'صرفاً مصنوعی'];
        $this->post($this->url('/documents'), $data)->assertRedirect();
        $this->post($this->url('/documents'), $data)->assertRedirect();
        $this->assertDatabaseCount('emr_documents', 1);
        $document = MedicalDocument::firstOrFail();
        $bytes = app(\App\Modules\MedicalRecords\Application\DocumentService::class)->bytes($this->admin, $this->patient, $document);
        $this->assertSame($originalHash, $document->sha256, 'Clinical evidence must not be recompressed or altered.');
        $this->assertSame($document->sha256, hash('sha256', $bytes));
        $this->assertDatabaseHas('configuration_audit_events', ['action' => 'emr.document.downloaded']);
    }

    public function test_all_clinical_sections_persist_structured_payloads_and_real_jalali_dates(): void
    {
        $visit = $this->visit();
        $cases = [
            'history' => ['category' => 'surgery', 'observation' => 'unknown', 'source' => 'document'],
            'medication' => ['name' => 'گزارش داروی ساختگی', 'source' => 'patient'],
            'result' => ['category' => 'laboratory', 'source' => 'document', 'value_unit' => 'SYNTHETIC 1 unit', 'sample_date' => '1405-08-08'],
            'menstrual' => ['regularity' => 'unknown', 'source' => 'patient'],
            'fertility' => ['trying_months' => 12, 'evaluation' => 'ساختگی'],
        ];
        foreach ($cases as $kind => $payload) {
            $this->post($this->url('/records/'.$kind), $this->data($payload, ['visit_id' => $visit->id]))->assertRedirect();
            $record = ClinicalRecord::latest('id')->first();
            $this->assertSame($kind, $record->kind);
            $this->assertSame('2026-10-30', $record->occurred_at->timezone('Asia/Tehran')->format('Y-m-d'));
            $this->get($this->url('/records/'.$record->id))->assertOk();
        }
        $result = ClinicalRecord::where('kind', 'result')->sole();
        $this->assertSame('2026-10-30', $result->payload['sample_date']);
        $this->post($this->url('/records/result'), $this->data(['category' => 'laboratory', 'source' => 'document', 'sample_date' => '1405-12-30']))->assertSessionHasErrors('payload.sample_date');
    }

    public function test_malformed_array_fields_return_validation_errors_instead_of_server_errors(): void
    {
        $this->postJson($this->url('/records/visit'), $this->data(changes: ['occurred_jalali' => ['bad']]))->assertUnprocessable();
        $this->putJson($this->url('/identity'), ['name' => ['bad'], 'identity_type' => 'national', 'identity_number' => ['bad'], 'birth_jalali' => ['bad'], 'status' => 'active', 'marital_status' => 'unknown', 'expected_version' => 0])->assertUnprocessable();
    }

    public function test_validation_preserves_original_jalali_dates_for_a_corrected_resubmission(): void
    {
        $payload = ['regularity' => 'invalid', 'source' => 'patient', 'lmp' => '1405-08-08'];
        $this->from($this->url('/new/menstrual'))->post($this->url('/records/menstrual'), $this->data($payload))->assertSessionHasErrors('payload.regularity')->assertSessionHasInput('payload.lmp', '1405-08-08');
        $this->get($this->url('/new/menstrual'))->assertOk()->assertSee('value="1405-08-08"', false);
        $this->post($this->url('/records/menstrual'), $this->data([...$payload, 'regularity' => 'unknown']))->assertRedirect();
        $this->assertSame('2026-10-30', ClinicalRecord::latest('id')->first()->payload['lmp']);
    }
}
