<?php

namespace Tests\Feature;

use App\Modules\Booking\Application\BookingService;
use App\Modules\DynamicForms\Application\FormSchema;
use App\Modules\DynamicForms\Application\PrivateFormService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\BookingFixtures;
use Tests\TestCase;

final class P6PrivateFormsTest extends TestCase
{
    use DatabaseMigrations;
    use BookingFixtures;
    private $admin;
    private $patient;
    private $secretary;
    private $service;
    private $appointment;
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = $this->booker('09120000001', 'admin');
        $this->patient = $this->booker();
        $this->secretary = $this->booker('09120000002', 'secretary');
        $this->service = $this->bookingServiceFixture($this->admin);
        $this->appointment = app(BookingService::class)->create($this->bookingActor($this->patient), $this->bookingData($this->service));
    }
    private function definition(array $changes = []): array
    {
        return ['title' => 'اطلاعات ضروری آزمایشی', 'service_id' => $this->service->id, 'is_active' => true, 'expected_version' => 0, 'reason' => 'انتشار آزمایشی', 'schema' => [
            ['key' => 'choice', 'label' => 'نیاز به هماهنگی', 'type' => 'select', 'required' => true, 'options' => ['بله', 'خیر']],
            ['key' => 'detail', 'label' => 'توضیح ضروری', 'type' => 'text', 'required' => true, 'condition' => ['field' => 'choice', 'equals' => 'بله']],
        ], ...$changes];
    }
    private function publish(array $changes = [], ?int $id = null): int
    {
        return app(PrivateFormService::class)->publish($this->admin, $id, $this->definition($changes));
    }
    private function payload(int $id, array $changes = []): array
    {
        return ['version_id' => DB::table('form_versions')->where('form_id', $id)->orderByDesc('number')->value('id'), 'expected_submission_id' => 0, 'idempotency_key' => (string) Str::uuid(), 'consent' => 1, 'answers' => ['choice' => 'بله', 'detail' => 'پاسخ محرمانه آزمایشی'], ...$changes];
    }
    private function url(int $id): string
    {
        return '/private-forms/'.$this->appointment->reference.'/'.$id;
    }
    public function test_admin_publishes_versioned_form_through_validated_endpoint_and_no_secretary_access(): void
    {
        $this->actingAs($this->secretary)->get('/admin/forms')->assertForbidden();
        $this->post('/admin/forms', $this->definition())->assertForbidden();
        $this->actingAs($this->admin)->post('/admin/forms', $this->definition())->assertRedirect();
        $this->assertDatabaseCount('form_versions', 1);
        $this->get('/admin/forms')->assertOk()->assertSee('اطلاعات ضروری آزمایشی');
        $this->get('/admin/forms/1')->assertOk()->assertSee('انتشار نسخه جدید');
    }
    public function test_encrypted_answers_and_recipient_are_hidden_from_serialization_and_audited(): void
    {
        $id = $this->publish();
        $data = $this->payload($id);
        $this->actingAs($this->patient)->post($this->url($id), $data)->assertRedirect($this->url($id));
        $row = DB::table('form_submissions')->sole();
        $this->assertStringNotContainsString('پاسخ محرمانه', $row->answers);
        $this->assertSame($data['answers'], json_decode(Crypt::decryptString($row->answers), true));
        $this->assertNotNull($row->consented_at);
        $this->get($this->url($id))->assertOk()->assertSee('پاسخ محرمانه آزمایشی')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('form_access_logs', 2);
        $this->assertStringNotContainsString('پاسخ محرمانه', json_encode(DB::table('form_access_logs')->get()));
        $raw = DB::table('appointments')->where('id', $this->appointment->id)->value('notification_mobile');
        $this->assertNotSame($this->patient->mobile, $raw);
        $this->assertArrayNotHasKey('notification_mobile', $this->appointment->toArray());
        $this->get('/appointments/'.$this->appointment->reference)->assertDontSee('پاسخ محرمانه');
    }
    public function test_appointment_permission_is_not_medical_permission_and_idor_is_blocked(): void
    {
        $id = $this->publish();
        $this->actingAs($this->secretary)->get($this->url($id))->assertForbidden();
        $this->post($this->url($id), $this->payload($id))->assertForbidden();
        $other = $this->booker('09120000003');
        $this->actingAs($other)->get($this->url($id))->assertNotFound();
        $this->post($this->url($id), $this->payload($id))->assertNotFound();
        $this->assertDatabaseCount('form_access_logs', 0);
        $this->assertDatabaseCount('form_submissions', 0);
    }
    public function test_delegated_reader_cannot_write_and_permission_revocation_is_immediate(): void
    {
        $id = $this->publish();
        $role = $this->secretary->roles()->first();
        $permission = \App\Modules\Identity\Infrastructure\Permission::where('name', 'forms.answers.view')->sole();
        $role->permissions()->attach($permission);
        $this->actingAs($this->secretary)->get($this->url($id))->assertOk()->assertSee('فقط برای مشاهده');
        $this->post($this->url($id), $this->payload($id))->assertForbidden();
        $role->permissions()->detach($permission);
        $this->get($this->url($id))->assertForbidden();
    }
    public function test_guest_must_have_fresh_verified_ownership_and_matching_contact_is_not_ownership(): void
    {
        $id = $this->publish();
        $this->appointment->forceFill(['booker_user_id' => null])->save();
        $this->withSession(['guest_identity' => ['mobile' => $this->patient->mobile, 'expires_at' => now()->addMinutes(10)->timestamp]])->get($this->url($id))->assertOk();
        $this->withSession(['guest_identity' => ['mobile' => '09120000003', 'expires_at' => now()->addMinutes(10)->timestamp]])->get($this->url($id))->assertNotFound();
        $this->withSession(['guest_identity' => ['mobile' => $this->patient->mobile, 'expires_at' => now()->subSecond()->timestamp]])->get($this->url($id))->assertRedirect('/login');
    }
    public function test_required_conditional_fields_are_enforced_and_hidden_values_discarded(): void
    {
        $id = $this->publish();
        $this->actingAs($this->patient)->postJson($this->url($id), $this->payload($id, ['answers' => ['choice' => 'بله']]))->assertUnprocessable();
        $this->post($this->url($id), $this->payload($id, ['answers' => ['choice' => 'خیر', 'detail' => 'مقدار پنهان غیرمجاز']]))->assertRedirect();
        $this->assertSame(['choice' => 'خیر'], json_decode(Crypt::decryptString(DB::table('form_submissions')->sole()->answers), true));
    }
    public function test_invalid_answer_is_not_flashed_into_session_and_xss_is_escaped(): void
    {
        $id = $this->publish();
        $this->actingAs($this->patient)->from($this->url($id))->post($this->url($id), $this->payload($id, ['answers' => ['choice' => 'INVALID-PRIVATE', 'detail' => 'PRIVATE-ANSWER']]))->assertSessionHasErrors('choice')->assertSessionMissing('_old_input.answers');
        $this->post($this->url($id), $this->payload($id, ['answers' => ['choice' => 'بله', 'detail' => '<script>alert(1)</script>']]))->assertRedirect();
        $this->get($this->url($id))->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }
    public function test_submission_replay_and_stale_revision_do_not_overwrite_history(): void
    {
        $id = $this->publish();
        $data = $this->payload($id);
        $this->actingAs($this->patient)->post($this->url($id), $data)->assertRedirect();
        $this->post($this->url($id), $data)->assertRedirect();
        $this->assertDatabaseCount('form_submissions', 1);
        $this->post($this->url($id), [...$data, 'answers' => ['choice' => 'خیر']])->assertConflict();
        $this->post($this->url($id), $this->payload($id))->assertConflict();
        $latest = DB::table('form_submissions')->sole();
        $this->post($this->url($id), $this->payload($id, ['expected_submission_id' => $latest->id, 'answers' => ['choice' => 'خیر']]))->assertRedirect();
        $this->assertDatabaseCount('form_submissions', 2);
        $this->assertSame($data['answers'], json_decode(Crypt::decryptString(DB::table('form_submissions')->where('id', $latest->id)->value('answers')), true));
    }
    public function test_published_versions_preserve_old_structure_and_reject_unseen_version(): void
    {
        $id = $this->publish();
        $oldData = $this->payload($id);
        $this->publish(['expected_version' => 1, 'schema' => [['key' => 'new', 'label' => 'ساختار جدید', 'type' => 'text', 'required' => true]]], $id);
        $this->actingAs($this->patient)->post($this->url($id), $oldData)->assertConflict();
        $this->assertDatabaseCount('form_submissions', 0);
        $this->assertDatabaseCount('form_versions', 2);
        $this->assertSame('choice', json_decode(DB::table('form_versions')->where('number', 1)->value('schema'), true)[0]['key']);
    }
    public function test_answered_appointment_stays_pinned_to_original_form_version(): void
    {
        $id = $this->publish();
        $this->actingAs($this->patient)->post($this->url($id), $this->payload($id))->assertRedirect();
        $this->publish(['expected_version' => 1, 'schema' => [['key' => 'new', 'label' => 'ساختار جدید', 'type' => 'text', 'required' => true]]], $id);
        $this->get($this->url($id))->assertOk()->assertSee('نسخه 1')->assertSee('پاسخ محرمانه')->assertDontSee('ساختار جدید');
    }
    public function test_wrong_service_disabled_form_and_closed_appointment_are_guarded(): void
    {
        $id = $this->publish();
        DB::table('forms')->where('id', $id)->update(['is_active' => false]);
        $this->actingAs($this->patient)->get($this->url($id))->assertNotFound();
        DB::table('forms')->where('id', $id)->update(['is_active' => true]);
        $this->appointment->forceFill(['status' => 'cancelled'])->save();
        $this->post($this->url($id), $this->payload($id))->assertConflict();
        $this->get($this->url($id))->assertOk()->assertSee('فقط برای مشاهده');
        $this->assertDatabaseCount('form_submissions', 0);
    }
    public function test_consent_unknown_fields_and_malformed_answers_are_rejected(): void
    {
        $id = $this->publish();
        $this->actingAs($this->patient)->postJson($this->url($id), $this->payload($id, ['consent' => 0]))->assertUnprocessable();
        $this->postJson($this->url($id), $this->payload($id, ['answers' => ['unknown' => 'PRIVATE']]))->assertUnprocessable();
        $this->postJson($this->url($id), $this->payload($id, ['answers' => 'PRIVATE']))->assertUnprocessable();
        $this->assertDatabaseCount('form_submissions', 0);
    }
    public function test_number_date_multiselect_checkbox_and_condition_on_false_are_validated(): void
    {
        $schema = [['key' => 'flag', 'label' => 'گزینه', 'type' => 'checkbox', 'required' => true], ['key' => 'count', 'label' => 'عدد', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 5, 'condition' => ['field' => 'flag', 'equals' => false]], ['key' => 'day', 'label' => 'تاریخ میلادی', 'type' => 'date', 'required' => true], ['key' => 'choices', 'label' => 'انتخاب‌ها', 'type' => 'multiselect', 'required' => true, 'options' => ['الف', 'ب']]];
        $service = app(FormSchema::class);
        $service->definition($schema);
        $valid = ['flag' => '0', 'count' => '۳', 'day' => '2026-10-25', 'choices' => ['الف', 'ب']];
        $this->assertSame('3', $service->answers($schema, $valid)['count']);
        foreach ([['count' => 6], ['day' => '2026-02-30'], ['choices' => ['الف', 'ناشناخته']], ['choices' => ['الف', 'الف']], ['flag' => []]] as $bad) {
            try {
                $service->answers($schema, [...$valid, ...$bad]);
                $this->fail('Invalid answers accepted');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }
    public function test_jalali_date_is_normalized_to_iso_and_invalid_jalali_date_is_rejected(): void
    {
        $schema = [['key' => 'day', 'label' => 'روز هماهنگی', 'type' => 'date', 'required' => true]];
        $this->assertSame(['day' => '2026-10-25'], app(FormSchema::class)->answers($schema, ['day' => '۱۴۰۵-۰۸-۰۳']));
        $id = $this->publish(['schema' => $schema]);
        $this->actingAs($this->patient)->postJson($this->url($id), $this->payload($id, ['answers' => ['day' => '۱۴۰۵-۱۳-۰۱']]))->assertUnprocessable();
        $this->post($this->url($id), $this->payload($id, ['answers' => ['day' => '۱۴۰۵-۰۸-۰۳']]))->assertRedirect();
        $this->get($this->url($id))->assertOk()->assertSee('value="1405-08-03"', false);
    }
    public function test_invalid_schema_cycles_file_type_and_duplicate_keys_are_rejected(): void
    {
        foreach ([['type' => 'file'], ['key' => 'Invalid Key'], ['condition' => ['field' => 'detail', 'equals' => 'x']]] as $change) {
            $definition = $this->definition();
            $definition['schema'][0] = [...$definition['schema'][0], ...$change];
            $this->actingAs($this->admin)->postJson('/admin/forms', $definition)->assertUnprocessable();
        }
        $definition = $this->definition();
        $definition['schema'][1]['key'] = 'choice';
        $this->postJson('/admin/forms', $definition)->assertUnprocessable();
        $this->assertDatabaseCount('forms', 0);
    }
    public function test_separate_select_fields_may_share_options_but_one_field_cannot_repeat_them(): void
    {
        $first = $this->definition()['schema'][0];
        $fields = [$first, [...$first, 'key' => 'second']];
        $id = $this->publish(['schema' => $fields]);
        $this->assertDatabaseHas('forms', ['id' => $id]);
        $fields[0]['options'] = ['بله', 'بله'];
        $this->actingAs($this->admin)->postJson('/admin/forms', $this->definition(['schema' => $fields]))->assertUnprocessable();
        $this->assertDatabaseCount('forms', 1);
    }
    public function test_page_assignment_and_builder_options_conversion_and_stale_publish(): void
    {
        $data = $this->definition(['service_id' => null, 'page_key' => 'appointment']);
        $data['schema'][0]['options_text'] = "بله\nخیر";
        $data['schema'][1]['options_text'] = '';
        $data['schema'][1]['min'] = '';
        $data['schema'][1]['max'] = '';
        unset($data['schema'][0]['options']);
        $this->actingAs($this->admin)->post('/admin/forms', $data)->assertRedirect();
        $id = DB::table('forms')->sole()->id;
        $this->post('/admin/forms/'.$id, $data)->assertConflict();
        $this->actingAs($this->patient)->get('/private-forms/'.$this->appointment->reference)->assertOk()->assertSee('اطلاعات ضروری');
        $this->get($this->url($id))->assertOk();
    }
    public function test_inactive_account_and_csrf_are_guarded(): void
    {
        $id = $this->publish();
        $this->patient->forceFill(['is_active' => false])->save();
        $this->actingAs($this->patient)->get($this->url($id))->assertForbidden();
        $this->patient->forceFill(['is_active' => true])->save();
        $this->app['env'] = 'production';
        try {
            $this->post($this->url($id), $this->payload($id))->assertStatus(419);
        } finally {
            $this->app['env'] = 'testing';
        }
    }
    public function test_archived_answer_remains_readable_but_no_longer_writable(): void
    {
        $id = $this->publish();
        $this->actingAs($this->patient)->post($this->url($id), $this->payload($id))->assertRedirect();
        DB::table('forms')->where('id', $id)->update(['is_active' => false]);
        $this->get('/private-forms/'.$this->appointment->reference)->assertOk()->assertSee('اطلاعات ضروری');
        $this->get($this->url($id))->assertOk()->assertSee('پاسخ محرمانه')->assertSee('فقط برای مشاهده');
        $this->post($this->url($id), $this->payload($id, ['expected_submission_id' => DB::table('form_submissions')->sole()->id]))->assertConflict();
        $this->assertDatabaseCount('form_submissions', 1);
    }
    public function test_database_failure_rolls_back_answer_and_never_exposes_or_logs_sensitive_binding(): void
    {
        $id = $this->publish();
        \Illuminate\Support\Facades\Log::spy();
        DB::listen(function ($query): void {
            if (str_starts_with(strtolower($query->sql), 'insert') && str_contains($query->sql, 'form_submissions')) {
                throw new \Illuminate\Database\QueryException('mysql', 'INSERT INTO form_submissions VALUES (?)', ['PRIVATE_FORM_BINDING'], new \PDOException('synthetic failure'));
            }
        });
        $this->actingAs($this->patient)->postJson($this->url($id), $this->payload($id))->assertStatus(503)->assertDontSee('PRIVATE_FORM_BINDING')->assertDontSee('پاسخ محرمانه');
        $this->assertDatabaseCount('form_submissions', 0);
        $this->assertDatabaseCount('form_access_logs', 0);
        \Illuminate\Support\Facades\Log::shouldHaveReceived('error')->once()->with('booking.database_error', \Mockery::type('array'));
    }
}
