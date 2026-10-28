<?php

namespace Tests\Feature;

use App\Modules\Catalog\Infrastructure\Service;
use App\Modules\Website\Infrastructure\WebsiteLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BookingFixtures;
use Tests\TestCase;

final class AdminWorkspaceUxTest extends TestCase
{
    use RefreshDatabase;
    use BookingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function document(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
        return new \DOMXPath($document);
    }

    public function test_failed_service_edit_restores_only_its_record_and_does_not_change_data(): void
    {
        $admin = $this->booker('09120000001', 'admin');
        $first = Service::create(['name' => 'خدمت اول', 'price_irr' => 10000, 'duration_minutes' => 30, 'buffer_minutes' => 0, 'capacity' => 1, 'is_active' => true, 'is_online' => true]);
        $second = Service::create(['name' => 'خدمت دوم', 'price_irr' => 20000, 'duration_minutes' => 30, 'buffer_minutes' => 0, 'capacity' => 1, 'is_active' => true, 'is_online' => true]);
        $this->actingAs($admin)->from('/admin/services')->put(route('services.update', $second), [
            '_ux_context' => 'service-'.$second->id, 'name' => 'ویرایش نامعتبر دوم', 'price_irr' => 'invalid', 'duration_minutes' => 30, 'buffer_minutes' => 0, 'capacity' => 1, 'is_active' => 1, 'is_online' => 1,
        ])->assertRedirect('/admin/services')->assertSessionHasErrors('price_irr');
        $response = $this->get('/admin/services')->assertOk()->assertSee('data-record-tools', false);
        $xpath = $this->document($response->getContent());
        $this->assertSame('خدمت اول', $xpath->query('//*[@id="service-'.$first->id.'"]//input[@name="name"]')->item(0)->getAttribute('value'));
        $this->assertSame('ویرایش نامعتبر دوم', $xpath->query('//*[@id="service-'.$second->id.'"]//input[@name="name"]')->item(0)->getAttribute('value'));
        $this->assertSame(1, $xpath->query('//*[@id="service-'.$second->id.'" and @open]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="service-'.$first->id.'" and @open]')->length);
        $this->assertSame(20000, $second->fresh()->price_irr);
    }

    public function test_bad_old_input_shapes_and_context_cannot_break_or_inject_record_markup(): void
    {
        $admin = $this->booker('09120000001', 'admin');
        $this->actingAs($admin)->withSession(['_old_input' => ['_ux_context' => 'service-create', 'name' => ['bad'], 'price_irr' => ['bad']]])
            ->get('/admin/services')->assertOk();
        $this->withSession(['_old_input' => ['_ux_context' => '"><script>alert(1)</script>', 'name' => '<svg onload=alert(1)>']])
            ->get('/admin/services')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<svg onload=alert(1)>', false);
    }

    public function test_website_link_error_stays_in_the_matching_editor_and_keeps_url_validation(): void
    {
        $admin = $this->booker('09120000001', 'admin');
        $first = WebsiteLink::create(['label' => 'پیوند اول', 'url' => '/calendar', 'placement' => 'header', 'sort_order' => 1, 'is_published' => true, 'new_tab' => false]);
        $second = WebsiteLink::create(['label' => 'پیوند دوم', 'url' => '/contact', 'placement' => 'footer', 'sort_order' => 2, 'is_published' => false, 'new_tab' => false]);
        $this->actingAs($admin)->from('/admin/website-links')->put(route('website.links.update', $second), [
            '_ux_context' => 'link-'.$second->id, 'label' => 'ویرایش دوم', 'url' => 'javascript:alert(1)', 'placement' => 'footer', 'sort_order' => 2, 'is_published' => 0, 'new_tab' => 0,
        ])->assertRedirect('/admin/website-links')->assertSessionHasErrors('url');
        $response = $this->get('/admin/website-links')->assertOk();
        $xpath = $this->document($response->getContent());
        $this->assertSame('/calendar', $xpath->query('//*[@id="link-'.$first->id.'"]//input[@name="url"]')->item(0)->getAttribute('value'));
        $this->assertSame('javascript:alert(1)', $xpath->query('//*[@id="link-'.$second->id.'"]//input[@name="url"]')->item(0)->getAttribute('value'));
        $this->assertSame('/contact', $second->fresh()->url);
    }

    public function test_secretary_keeps_daily_tools_but_cannot_open_admin_cms_or_finance(): void
    {
        $this->actingAs($this->booker('09120000002', 'secretary'));
        $this->get('/management/desk')->assertOk()->assertSee('پیداکردن مراجعه‌کننده');
        $this->get('/management/schedule')->assertOk()->assertSee('data-schedule-form', false);
        $this->get('/management/reports')->assertOk()->assertSee('data-report-form', false)->assertDontSee('مالی کل مطب');
        $this->get('/admin/website-links')->assertForbidden();
        $this->get('/admin/finance')->assertForbidden();
    }

    public function test_rejected_schedule_keeps_exception_type_and_ignores_unexpected_old_shift_keys(): void
    {
        $secretary = $this->booker('09120000002', 'secretary');
        $this->actingAs($secretary)->withSession(['_old_input' => [
            '_ux_context' => 'exception-create', 'kind' => 'override', 'reason' => 'ساعات ویژه ساختگی',
            'shifts' => [['start' => '25:00', 'end' => '22:00', 'start_minute' => ['unexpected'], 'step_minutes' => ['bad'], 'capacity' => 1]],
        ]])->get('/management/schedule')->assertOk()->assertSee('value="override" selected', false)->assertSee('value="25:00"', false);
    }

    public function test_notification_tracking_renders_tehran_dates_without_private_recipient_or_parameters(): void
    {
        $admin = $this->booker('09120000001', 'admin');
        $patient = $this->booker();
        $service = $this->bookingServiceFixture($admin);
        $appointment = app(\App\Modules\Booking\Application\BookingService::class)->create($this->bookingActor($patient), $this->bookingData($service));
        \Illuminate\Support\Facades\DB::table('message_outbox')->insert([
            'event_key' => 'ux-synthetic-tracking', 'appointment_id' => $appointment->id, 'event' => 'confirmed',
            'recipient' => \Illuminate\Support\Facades\Crypt::encryptString('09121112222'),
            'parameters' => \Illuminate\Support\Facades\Crypt::encryptString('{"private":"DO_NOT_RENDER_PARAMETERS"}'),
            'provider_template_id' => 1, 'appointment_start' => $appointment->start_at, 'state' => 'accepted',
            'available_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($admin)->get('/admin/notifications')->assertOk()->assertSee('به وقت تهران')
            ->assertSee('پذیرفته‌شده توسط ارائه‌دهنده')->assertDontSee('09121112222')->assertDontSee('DO_NOT_RENDER_PARAMETERS');
    }
}
