<?php

namespace Tests\Feature;

use App\Modules\Booking\Application\BookingService;
use App\Modules\Booking\Application\FinancialBookingService;
use App\Modules\Notifications\Application\MessageDispatcher;
use App\Modules\Notifications\Application\NotificationOutbox;
use App\Modules\Notifications\Domain\EventSmsProviderInterface;
use App\Modules\Notifications\Domain\SmsFailure;
use App\Modules\Notifications\Infrastructure\SmsIrEventProvider;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\BookingFixtures;
use Tests\TestCase;

final class P6NotificationsTest extends TestCase
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
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-26 00:01', 'Asia/Tehran'));
        $this->seed();
        $this->admin = $this->booker('09120000001', 'admin');
        $this->secretary = $this->booker('09120000002', 'secretary');
        $this->patient = $this->booker();
        $this->service = $this->bookingServiceFixture($this->admin);
        config(['notifications.enabled' => true]);
        foreach (NotificationOutbox::EVENTS as $event) {
            DB::table('sms_templates')->insert(['event' => $event, 'provider_template_id' => 123, 'enabled' => true, 'secretary_id' => $event === 'secretary' ? $this->secretary->id : null, 'reminder_minutes' => 1440, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->appointment = app(BookingService::class)->create($this->bookingActor($this->patient), $this->bookingData($this->service));
        Http::preventStrayRequests();
    }
    private function confirm(): void
    {
        DB::transaction(fn () => app(FinancialBookingService::class)->confirm($this->appointment, 'unpaid', $this->appointment->price_irr, $this->admin->id, 'تثبیت fixture تست؛ بدون پرداخت واقعی'));
        $this->appointment = $this->appointment->fresh();
    }
    private function dispatcher(string $result = 'ok'): MessageDispatcher
    {
        return new MessageDispatcher(new class ($result) implements EventSmsProviderInterface {
            public function __construct(private string $result)
            {
            }
            public function sendTemplate(string $recipient, int $templateId, array $parameters): string
            {
                if (DB::transactionLevel() !== 0) {
                    throw new \LogicException('NETWORK_UNDER_LOCK');
                }
                if ($this->result !== 'ok') {
                    throw new SmsFailure($this->result === 'reject');
                }
                return '123456';
            }
            public function queryDelivery(string $messageId): ?string
            {
                return '1';
            }
        });
    }
    public function test_hold_does_not_notify_and_confirmation_is_durable_private_and_idempotent(): void
    {
        $this->assertDatabaseCount('message_outbox', 0);
        $this->confirm();
        $this->assertDatabaseCount('message_outbox', 3);
        DB::transaction(fn () => app(NotificationOutbox::class)->record($this->appointment, 'confirmed'));
        $this->assertDatabaseCount('message_outbox', 3);
        $message = DB::table('message_outbox')->where('event', 'confirmed')->sole();
        $this->assertSame($this->patient->mobile, Crypt::decryptString($message->recipient));
        $this->assertStringNotContainsString($this->patient->mobile, $message->recipient);
        $parameters = json_decode(Crypt::decryptString($message->parameters), true);
        $this->assertSame(['DATE', 'TIME', 'REFERENCE'], array_keys($parameters));
        $this->assertStringNotContainsString($this->service->name, json_encode($parameters));
        $this->assertStringStartsWith('14', $parameters['DATE']);
        Http::assertNothingSent();
    }
    public function test_rollback_removes_notification_with_booking_state(): void
    {
        try {
            DB::transaction(function (): void {
                $this->confirm();
                throw new \RuntimeException('TEST_ROLLBACK');
            });
        } catch (\RuntimeException) {
        }
        $this->assertDatabaseCount('message_outbox', 0);
        $this->assertSame('pending_payment', $this->appointment->fresh()->status);
        Http::assertNothingSent();
    }
    public function test_dispatch_twice_accepts_only_once_and_reminder_is_not_early(): void
    {
        $this->confirm();
        $id = DB::table('message_outbox')->where('event', 'confirmed')->value('id');
        $dispatcher = $this->dispatcher();
        $dispatcher->dispatch($id);
        $dispatcher->dispatch($id);
        $this->assertDatabaseHas('message_outbox', ['id' => $id, 'state' => 'accepted', 'attempts' => 1, 'provider_message_id' => '123456']);
        $dispatcher->poll();
        $reminder = DB::table('message_outbox')->where('event', 'reminder')->sole();
        $dispatcher->dispatch($reminder->id);
        $this->assertDatabaseHas('message_outbox', ['id' => $reminder->id, 'state' => 'queued', 'attempts' => 0]);
        $this->travelTo(\Carbon\CarbonImmutable::parse($reminder->available_at, 'UTC'));
        $dispatcher->dispatch($reminder->id);
        $this->assertDatabaseHas('message_outbox', ['id' => $reminder->id, 'state' => 'accepted']);
        $dispatcher->poll();
        $this->assertDatabaseHas('message_outbox', ['id' => $id, 'delivery_code' => '1']);
        $this->assertDatabaseHas('message_outbox', ['id' => $reminder->id, 'delivery_code' => '1']);
        $this->assertDatabaseHas('sms_delivery_logs', ['message_id' => $id, 'state' => 'delivery_reported']);
    }
    public function test_explicit_rejection_retries_with_delay_but_stops_at_three(): void
    {
        $this->confirm();
        $id = DB::table('message_outbox')->where('event', 'confirmed')->value('id');
        $dispatcher = $this->dispatcher('reject');
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $dispatcher->dispatch($id);
            $row = DB::table('message_outbox')->find($id);
            $this->assertSame($attempt, $row->attempts);
            $this->assertSame($attempt === 3 ? 'failed' : 'retry', $row->state);
            $dispatcher->dispatch($id);
            $this->assertSame($attempt, DB::table('message_outbox')->find($id)->attempts);
            $this->travel(10)->minutes();
        }
    }
    public function test_ambiguous_delivery_and_crashed_claim_are_never_automatically_retried(): void
    {
        $this->confirm();
        $id = DB::table('message_outbox')->where('event', 'confirmed')->value('id');
        $dispatcher = $this->dispatcher('timeout');
        $dispatcher->dispatch($id);
        $dispatcher->dispatch($id);
        $this->assertDatabaseHas('message_outbox', ['id' => $id, 'state' => 'uncertain', 'attempts' => 1]);
        $reminder = DB::table('message_outbox')->where('event', 'reminder')->sole();
        DB::table('message_outbox')->where('id', $reminder->id)->update(['state' => 'sending', 'claimed_at' => now()->subMinutes(6), 'attempts' => 1]);
        $dispatcher->run();
        $this->assertDatabaseHas('message_outbox', ['id' => $reminder->id, 'state' => 'uncertain', 'attempts' => 1]);
        $this->assertDatabaseHas('sms_delivery_logs', ['message_id' => $reminder->id, 'state' => 'uncertain']);
    }
    public function test_reschedule_cancels_stale_reminder_and_cancel_suppresses_all_pending_notifications(): void
    {
        $this->confirm();
        $oldReminder = DB::table('message_outbox')->where('event', 'reminder')->value('id');
        $data = ['expected_version' => $this->appointment->version, 'idempotency_key' => (string) Str::uuid(), 'start_at' => $this->appointment->start_at->addHour()->toIso8601String()];
        $this->appointment = app(BookingService::class)->change($this->bookingActor($this->patient), $this->appointment, $data, 'reschedule');
        $this->assertDatabaseHas('message_outbox', ['id' => $oldReminder, 'state' => 'superseded']);
        $this->assertDatabaseHas('message_outbox', ['event' => 'rescheduled', 'state' => 'queued']);
        $this->appointment = app(BookingService::class)->change($this->bookingActor($this->patient), $this->appointment, ['expected_version' => $this->appointment->version, 'idempotency_key' => (string) Str::uuid(), 'reason' => 'لغو تست'], 'cancel');
        $this->dispatcher()->run();
        $this->assertSame(1, DB::table('message_outbox')->where('state', 'accepted')->count());
        $this->assertDatabaseHas('message_outbox', ['event' => 'cancelled', 'state' => 'accepted']);
    }
    public function test_reminder_does_not_send_after_attendance_or_to_inactive_secretary(): void
    {
        $this->confirm();
        $this->secretary->forceFill(['is_active' => false])->save();
        $id = DB::table('message_outbox')->where('event', 'secretary')->value('id');
        $this->dispatcher()->dispatch($id);
        $this->assertDatabaseHas('message_outbox', ['id' => $id, 'state' => 'superseded']);
        $id = DB::table('message_outbox')->where('event', 'reminder')->value('id');
        $this->appointment->forceFill(['status' => 'completed', 'attendance_status' => 'completed'])->save();
        $this->travelTo($this->appointment->start_at->subHour());
        $this->dispatcher()->dispatch($id);
        $this->assertDatabaseHas('message_outbox', ['id' => $id, 'state' => 'superseded']);
    }
    public function test_no_inferred_recipient_for_historic_hash_and_disabled_switch_sends_nothing(): void
    {
        config(['notifications.enabled' => false]);
        $this->confirm();
        $this->assertDatabaseCount('message_outbox', 0);
        config(['notifications.enabled' => true]);
        $this->appointment->forceFill(['notification_mobile' => null])->save();
        DB::transaction(fn () => app(NotificationOutbox::class)->record($this->appointment, 'confirmed'));
        $this->assertDatabaseCount('message_outbox', 1); // configured secretary only
        config(['notifications.enabled' => false]);
        $this->dispatcher()->run();
        $this->assertSame(0, DB::table('message_outbox')->where('state', 'accepted')->count());
    }
    public function test_notification_settings_require_admin_neutral_template_review_and_audit(): void
    {
        $data = ['event' => 'confirmed', 'provider_template_id' => 456, 'enabled' => 1, 'reminder_minutes' => 60, 'reviewed' => 1, 'reason' => 'قالب عمومی آزمایشی'];
        $this->actingAs($this->secretary)->get('/admin/notifications')->assertForbidden();
        $this->post('/admin/notifications', $data)->assertForbidden();
        $this->actingAs($this->admin)->post('/admin/notifications', $data)->assertRedirect();
        $this->assertDatabaseHas('configuration_audit_events', ['actor_id' => $this->admin->id, 'action' => 'sms.template.confirmed']);
        $this->postJson('/admin/notifications', [...$data, 'reviewed' => 0])->assertUnprocessable();
        $this->postJson('/admin/notifications', [...$data, 'event' => 'secretary', 'secretary_id' => $this->patient->id])->assertUnprocessable();
        $this->get('/admin/notifications')->assertOk()->assertSee('به معنی تحویل به گوشی نیست');
        Http::assertNothingSent();
    }
    public function test_smsir_adapter_acceptance_query_and_safe_parameters_without_raw_body_logging(): void
    {
        config(['identity.sms_ir.api_key' => 'test-only-key']);
        Http::fake(['https://api.sms.ir/v1/send/verify' => Http::response(['status' => 1, 'data' => ['messageId' => 777]]), 'https://api.sms.ir/v1/send/777' => Http::response(['status' => 1, 'data' => ['deliveryState' => 1]])]);
        $provider = app(SmsIrEventProvider::class);
        $this->assertSame('777', $provider->sendTemplate('09123456789', 123, ['DATE' => '1405-08-03', 'TIME' => '17:00', 'REFERENCE' => 'safe-reference']));
        $this->assertSame('1', $provider->queryDelivery('777'));
        Http::assertSent(fn ($request) => $request->url() === 'https://api.sms.ir/v1/send/verify' && $request['templateId'] === 123 && array_column($request['parameters'], 'name') === ['DATE', 'TIME', 'REFERENCE']);
        $this->assertNull($provider->queryDelivery('../private'));
        Http::assertSentCount(2);
    }
    public function test_smsir_timeout_or_malformed_success_is_uncertain_and_explicit_rejection_retryable(): void
    {
        config(['identity.sms_ir.api_key' => 'test-only-key']);
        Http::fake(['*' => Http::sequence()->push(['status' => 1, 'data' => []])->push(['status' => 0, 'data' => []])]);
        foreach ([[1, [], false], [0, [], true]] as [$status, $data, $retryable]) {
            try {
                app(SmsIrEventProvider::class)->sendTemplate('09123456789', 123, []);
                $this->fail('Failure accepted');
            } catch (SmsFailure $error) {
                $this->assertSame($retryable, $error->retryable);
                $this->assertStringNotContainsString('test-only-key', $error->getMessage());
            }
        }
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(fn () => throw new \RuntimeException('sensitive response'));
        try {
            app(SmsIrEventProvider::class)->sendTemplate('09123456789', 123, []);
            $this->fail('Timeout accepted');
        } catch (SmsFailure $error) {
            $this->assertFalse($error->retryable);
            $this->assertStringNotContainsString('sensitive', $error->getMessage());
        }
    }
    public function test_delivery_poll_does_not_starve_messages_beyond_the_first_batch(): void
    {
        foreach (range(1, 101) as $number) {
            DB::table('message_outbox')->insert(['event_key' => 'poll-fixture-'.$number, 'appointment_id' => $this->appointment->id, 'event' => 'confirmed', 'recipient' => Crypt::encryptString('09123456789'), 'parameters' => Crypt::encryptString('{}'), 'provider_template_id' => 123, 'appointment_start' => $this->appointment->start_at, 'state' => 'accepted', 'provider_message_id' => (string) $number, 'available_at' => now(), 'claimed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->dispatcher()->poll();
        $this->assertSame(100, DB::table('message_outbox')->whereNotNull('last_polled_at')->count());
        $this->travel(15)->minutes();
        $this->dispatcher()->poll();
        $this->assertNotNull(DB::table('message_outbox')->where('event_key', 'poll-fixture-101')->value('last_polled_at'));
    }
    public function test_failed_delivery_report_cannot_supply_a_success_code(): void
    {
        config(['identity.sms_ir.api_key' => 'test-only-key']);
        Http::fake(['*' => Http::response(['status' => 0, 'data' => ['deliveryState' => 1]])]);
        $this->assertNull(app(SmsIrEventProvider::class)->queryDelivery('777'));
    }
}
