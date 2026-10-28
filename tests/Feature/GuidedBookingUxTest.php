<?php

namespace Tests\Feature;

use App\Modules\Booking\Infrastructure\Appointment;
use App\Modules\Patients\Infrastructure\Patient;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BookingFixtures;
use Tests\TestCase;

final class GuidedBookingUxTest extends TestCase
{
    use RefreshDatabase;
    use BookingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->travelTo(CarbonImmutable::parse('2026-10-28 08:00', 'Asia/Tehran'));
    }

    public function test_patient_picker_only_discloses_linked_people_and_places_discount_after_identity(): void
    {
        $owner = $this->booker();
        $patient = Patient::create(['name' => '<img src=x onerror=alert(1)>', 'contact_mobile' => '09120000111', 'file_number' => 'SYNTHETIC-01']);
        DB::table('patient_user_links')->insert(['user_id' => $owner->id, 'patient_id' => $patient->id, 'created_at' => now()]);
        Patient::create(['name' => 'UNLINKED_SECRET_PERSON', 'contact_mobile' => '09120000222']);
        $response = $this->actingAs($owner)->get('/calendar')->assertOk()
            ->assertSee('09120000111')->assertSee('SYNTHETIC-01')->assertDontSee('UNLINKED_SECRET_PERSON')->assertDontSee('09120000222')
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)->assertDontSee('<img src=x onerror=alert(1)>', false)
            ->assertSeeInOrder(['data-patient-picker', 'data-new-patient', 'data-discount-quote', 'data-booking-submit'], false);
        $this->assertStringContainsString('data-native-select', $response->getContent());
        $this->post('/management/patients/search', ['query' => 'UNLINKED'])->assertForbidden();
    }

    public function test_appointment_guidance_changes_with_state_and_keeps_finance_and_cancellation_contracts(): void
    {
        $owner = $this->booker();
        $service = $this->bookingServiceFixture($owner);
        $created = $this->actingAs($owner)->postJson('/api/v1/appointments', $this->bookingData($service))->assertCreated();
        $appointment = Appointment::where('reference', $created->json('data.reference'))->sole();
        $path = '/appointments/'.$appointment->reference;
        $this->get($path)->assertOk()->assertSee('گام بعدی: پرداخت')->assertSee('data-hold-expires', false)
            ->assertSeeInOrder(['اطلاعات مراجعه', 'id="appointment-payment"', 'برگه نوبت و فرم‌ها', 'می‌خواهم این نوبت را لغو کنم'], false)
            ->assertSee('name="expected_version"', false)->assertSee('name="idempotency_key"', false)->assertSee('data-confirm', false);
        $appointment->update(['status' => 'confirmed', 'payment_status' => 'paid', 'balance_due_irr' => 0]);
        $this->get($path)->assertOk()->assertSee('نوبت شما قطعی است')->assertDontSee('data-hold-expires', false)->assertDontSee('پرداخت و تسویه کامل');
        $appointment->update(['status' => 'cancelled']);
        $this->get($path)->assertOk()->assertSee('این نوبت لغو شده است')->assertSee('رزرو نوبت جدید')->assertDontSee('می‌خواهم این نوبت را لغو کنم');
        $this->actingAs($this->booker('09120000333'))->get($path)->assertNotFound();
    }

    public function test_secretary_operational_pages_render_and_keep_privileged_controls_scoped(): void
    {
        $secretary = $this->booker('09120000444', 'secretary');
        $this->actingAs($secretary)->get('/management/desk')->assertOk();
        $this->get('/management/bookings/new')->assertOk()->assertSee('data-patient-dialog', false)
            ->assertSee('data-patient-find', false)->assertSeeInOrder(['data-new-patient', 'data-discount-quote', 'data-desk-settlement'], false);
        $this->get('/management/patients')->assertOk()->assertSee('افزودن بیمار جدید');
        $this->get('/admin/booking-settings')->assertForbidden();
        $this->get('/wallet')->assertOk()->assertSee('ux-two-columns', false);
    }
}
