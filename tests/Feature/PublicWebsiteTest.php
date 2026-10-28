<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicWebsiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_see_public_homepage_without_login_redirect(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('دکتر زهرا باقری')
            ->assertSee('رزرو نوبت آنلاین')
            ->assertSee(route('calendar'), false)
            ->assertDontSee('name="patient_id"', false);
    }

    public function test_existing_calendar_route_is_still_available(): void
    {
        $this->get('/calendar')->assertOk()->assertSee('رزرو نوبت')->assertSee('booking-calendar', false);
    }
}
