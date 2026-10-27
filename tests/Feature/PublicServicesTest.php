<?php

namespace Tests\Feature;

use App\Modules\Catalog\Infrastructure\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicServicesTest extends TestCase
{
    use RefreshDatabase;

    private function service(string $name, bool $active = true, bool $online = true): Service
    {
        return Service::create([
            'name' => $name,
            'price_irr' => 900000,
            'duration_minutes' => 30,
            'buffer_minutes' => 0,
            'capacity' => 1,
            'is_active' => $active,
            'is_online' => $online,
        ]);
    }

    public function test_guests_see_only_active_online_services_and_can_open_details(): void
    {
        $published = $this->service('خدمت قابل رزرو');
        $this->service('خدمت غیرفعال', false);
        $this->service('خدمت حضوری', true, false);

        $this->get('/services')->assertOk()
            ->assertSee('خدمت قابل رزرو')
            ->assertSee(route('website.services.show', $published->id), false)
            ->assertDontSee('خدمت غیرفعال')
            ->assertDontSee('خدمت حضوری');

        $this->get(route('website.services.show', $published->id))->assertOk()
            ->assertSee('خدمت قابل رزرو')
            ->assertSee('900,000')
            ->assertSee(route('calendar'), false);

        $this->get('/')->assertOk()->assertSee(route('website.services.show', $published->id), false);
    }

    public function test_inactive_offline_deleted_and_unknown_services_are_not_publicly_addressable(): void
    {
        $inactive = $this->service('غیرفعال', false);
        $offline = $this->service('فقط حضوری', true, false);
        $deleted = $this->service('بایگانی');
        $deleted->delete();

        foreach ([$inactive->id, $offline->id, $deleted->id, 999999] as $id) {
            $this->get('/services/'.$id)->assertNotFound();
        }

        $this->get('/services/not-a-number')->assertNotFound();
    }

    public function test_service_names_are_escaped_in_public_views(): void
    {
        $item = $this->service('<script>alert(1)</script>');

        $this->get(route('website.services.show', $item->id))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
