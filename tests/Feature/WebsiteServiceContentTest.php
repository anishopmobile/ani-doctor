<?php

namespace Tests\Feature;

use App\Modules\Catalog\Infrastructure\Service;
use App\Modules\Identity\Infrastructure\Role;
use App\Modules\Identity\Infrastructure\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WebsiteServiceContentTest extends TestCase
{
    use RefreshDatabase;

    private function service(): Service
    {
        return Service::create([
            'name' => 'خدمت نمونه',
            'price_irr' => 900000,
            'duration_minutes' => 30,
            'buffer_minutes' => 0,
            'capacity' => 1,
            'is_active' => true,
            'is_online' => true,
        ]);
    }

    public function test_draft_copy_is_private_and_published_copy_is_escaped(): void
    {
        $service = $this->service();
        $this->seed(RoleSeeder::class);
        $admin = User::create(['mobile' => '09120000992']);
        $admin->forceFill(['mobile_verified_at' => now()])->save();
        $admin->roles()->attach(Role::query()->where('name', 'admin')->firstOrFail());

        $url = route('website.service-content.update', $service->id);
        $this->put($url, ['summary' => 'محرمانه', 'description' => 'پیش نویس', 'is_published' => 1])
            ->assertRedirect('/login');

        $this->actingAs($admin)->put($url, [
            'summary' => 'محرمانه',
            'description' => 'پیش نویس',
            'is_published' => 0,
        ])->assertSessionHasNoErrors();

        $this->get(route('website.services.show', $service->id))->assertDontSee('محرمانه');
        $this->put($url, [
            'summary' => '<script>alert(1)</script>',
            'description' => '<img src=x onerror=alert(1)>',
            'is_published' => 1,
        ])->assertSessionHasNoErrors();

        $this->get(route('website.services.show', $service->id))
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);

        $this->assertDatabaseHas('services', ['id' => $service->id, 'price_irr' => 900000]);
    }

    public function test_invalid_summary_cannot_be_saved(): void
    {
        $service = $this->service();
        $this->seed(RoleSeeder::class);
        $admin = User::create(['mobile' => '09120000993']);
        $admin->forceFill(['mobile_verified_at' => now()])->save();
        $admin->roles()->attach(Role::query()->where('name', 'admin')->firstOrFail());
        $this->actingAs($admin)->put(route('website.service-content.update', $service->id), [
            'summary' => str_repeat('a', 351),
            'is_published' => 1,
        ])->assertSessionHasErrors('summary');
        $this->assertDatabaseCount('website_service_contents', 0);
    }
}
