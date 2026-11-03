<?php

namespace Tests\Feature;

use App\Modules\Bale\Application\InitDataVerifier;
use App\Modules\Medical\Application\MedicalInbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BookingFixtures;
use Tests\TestCase;

final class BaleIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use BookingFixtures;

    private function signed(string $id = '123', ?int $date = null): string
    {
        $data = ['auth_date' => (string) ($date ?? now()->timestamp), 'query_id' => 'test', 'user' => json_encode(['id' => (int) $id])];
        $check = implode("\n", array_map(fn ($key) => $key.'='.$data[$key], array_keys($data)));
        $data['hash'] = hash_hmac('sha256', $check, hash_hmac('sha256', 'synthetic-token', 'WebAppData', true));
        return http_build_query($data);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
        config(['bale.enabled' => true, 'bale.bot_token' => 'synthetic-token', 'bale.bot_id' => 1, 'bale.safir_key' => 'synthetic-key']);
        Http::preventStrayRequests();
    }

    public function test_signature_and_freshness_reject_forgery_duplicate_fields_and_old_data(): void
    {
        $this->assertSame('123', app(InitDataVerifier::class)->verify($this->signed()));
        foreach ([$this->signed().'&user=forged', str_replace('test', 'forged', $this->signed()), $this->signed('123', now()->subMinutes(11)->timestamp)] as $raw) {
            $this->withHeader('X-Bale-Init-Data', $raw)->postJson('/bale/me')->assertUnauthorized();
        }
    }

    public function test_safir_otp_links_only_patient_and_subsequent_login_needs_no_otp(): void
    {
        $code = null;
        Http::fake(['safir.bale.ai/*' => function ($request) use (&$code) {
            $code = $request['message_data']['otp_message']['otp'];
            $this->assertSame('989123456789', $request['phone_number']);
            $this->assertTrue($request->hasHeader('api-access-key', 'synthetic-key'));
            return Http::response(['message_id' => 'test-message']);
        }]);
        $this->withHeader('X-Bale-Init-Data', $this->signed())->postJson('/bale/otp', ['mobile' => '09123456789'])->assertOk()->assertJsonMissingPath('data.preview_code');
        $this->postJson('/bale/verify', ['code' => 'wrong'])->assertUnprocessable();
        $this->postJson('/bale/verify', ['code' => $code])->assertOk();
        $this->postJson('/bale/me')->assertOk();
        $this->assertDatabaseCount('bale_links', 1);
        Http::assertSentCount(1);
    }

    public function test_medical_files_encrypted_ownership_enforced_and_staff_review_audited(): void
    {
        $owner = $this->booker();
        $service = $this->bookingServiceFixture($owner);
        $appointment = app(\App\Modules\Booking\Application\BookingService::class)->create($this->bookingActor($owner), $this->bookingData($service));
        $actor = $this->bookingActor($owner);
        $reference = app(MedicalInbox::class)->submit($actor, $appointment->patient_id, UploadedFile::fake()->image('private.png'));
        $doc = DB::table('medical_documents')->sole();
        $this->assertStringNotContainsString('PNG', Storage::disk('local')->get($doc->path));
        $this->actingAs($owner)->get('/management/medical-inbox/'.$reference.'/file')->assertForbidden();
        $secretary = $this->booker('09120000002', 'secretary');
        $this->actingAs($secretary)->get('/management/medical-inbox/'.$reference.'/file')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->post('/management/medical-inbox/'.$reference.'/review', ['decision' => 'accepted', 'response' => 'پاسخ خصوصی آزمایشی'])->assertRedirect();
        $this->assertStringNotContainsString('پاسخ خصوصی', DB::table('medical_documents')->value('response'));
        $this->assertSame('پاسخ خصوصی آزمایشی', app(MedicalInbox::class)->responses($actor, $appointment->patient_id)[0]['response']);
        $this->assertDatabaseHas('medical_documents', ['reviewed_by' => $secretary->id, 'state' => 'accepted']);
        $this->assertDatabaseHas('emr_documents', ['patient_id' => $appointment->patient_id, 'status' => 'reviewed', 'reviewed_by' => $secretary->id]);
        @unlink(storage_path('app/private/emr/'.DB::table('emr_documents')->value('path')));
        $this->assertDatabaseHas('financial_events', ['action' => 'medical.viewed']);
    }

    public function test_bale_family_booking_cannot_use_another_patients_id_and_has_only_card_payment(): void
    {
        $owner = $this->booker();
        $service = $this->bookingServiceFixture($owner);
        $appointment = app(\App\Modules\Booking\Application\BookingService::class)->create($this->bookingActor($owner), $this->bookingData($service));
        $other = $this->booker('09120000004');
        DB::table('bale_links')->insert(['bale_id' => '123', 'user_id' => $other->id, 'active' => true]);
        $data = $this->bookingData($service);
        unset($data['patient_name']);
        $data['patient_id'] = $appointment->patient_id;
        $this->withHeader('X-Bale-Init-Data', $this->signed())->postJson('/bale/book', $data)->assertNotFound();
        $this->postJson('/bale/document', ['patient_id' => $appointment->patient_id, 'consent' => 1, 'document' => UploadedFile::fake()->image('x.png')])->assertNotFound();
        $this->get('/bale')->assertOk()->assertDontSee('زرین');
        $this->assertDatabaseCount('medical_documents', 0);
    }

    public function test_polling_ignores_groups_and_file_download_rejects_untrusted_paths(): void
    {
        Http::fake(['tapi.bale.ai/*' => Http::response(['ok' => true, 'result' => [
            ['update_id' => 1, 'message' => ['chat' => ['id' => -123, 'type' => 'group'], 'from' => ['id' => 123], 'text' => '/start']],
        ]])]);
        app(\App\Modules\Bale\Application\BalePoller::class)->once();
        Http::assertSentCount(1);
        $this->assertSame(2, (int) DB::table('bale_poll_state')->value('offset'));
        Http::fake(['tapi.bale.ai/*' => Http::response(['ok' => true, 'result' => ['file_path' => '../private/file.png', 'file_size' => 100]])]);
        try {
            app(\App\Modules\Bale\Infrastructure\BaleClient::class)->download('synthetic-file');
            $this->fail('Untrusted file path accepted');
        } catch (\App\Modules\Booking\Domain\BookingFailure $error) {
            $this->assertSame('INVALID_FILE', $error->errorCode);
        }
    }

    public function test_outbox_unknown_delivery_is_not_automatically_resent(): void
    {
        $owner = $this->booker();
        DB::table('bale_links')->insert(['bale_id' => '123', 'user_id' => $owner->id, 'active' => true]);
        app(\App\Modules\Bale\Application\BaleOutbox::class)->enqueue('synthetic-event', '123', 'اعلان عمومی ساختگی');
        Http::fake(['tapi.bale.ai/*' => Http::response(['ok' => false], 503)]);
        app(\App\Modules\Bale\Application\BaleOutbox::class)->dispatch();
        app(\App\Modules\Bale\Application\BaleOutbox::class)->dispatch();
        Http::assertSentCount(1);
        $this->assertSame('uncertain', DB::table('bale_outbox')->value('state'));
        $this->assertStringNotContainsString('اعلان عمومی', DB::table('bale_outbox')->value('message'));
    }
}
