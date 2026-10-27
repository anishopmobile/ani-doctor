<?php

namespace Tests\Feature;

use App\Modules\Identity\Infrastructure\Role;
use App\Modules\Identity\Infrastructure\User;
use App\Modules\Website\Application\ThemeService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WebsiteThemeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::create(['mobile' => '09120000991']);
        $user->forceFill(['mobile_verified_at' => now()])->save();
        $user->roles()->attach(Role::query()->where('name', 'admin')->firstOrFail());

        return $user;
    }

    public function test_public_pages_show_default_colors_before_any_admin_change(): void
    {
        $this->get('/')->assertOk()->assertSee('--pink: #ce4081;', false);
        $this->get('/services')->assertOk()->assertSee('--purple: #362147;', false);
        $this->get('/articles')->assertOk()->assertSee('--paper: #fffafc;', false);
    }

    public function test_only_verified_administrator_can_update_public_color_palette(): void
    {
        $values = [
            'primary_color' => '#112233',
            'text_color' => '#334455',
            'surface_color' => '#eef0ff',
        ];

        $this->get('/admin/website-theme')->assertRedirect('/login');
        $this->put('/admin/website-theme', $values)->assertRedirect('/login');

        $this->actingAs($this->admin())
            ->get(route('website.theme.edit'))->assertOk()
            ->assertSee('رنگ‌بندی سایت');

        $this->put(route('website.theme.update'), $values)->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('website_theme_settings', ['id' => 1] + $values);
        $this->get('/')->assertSee('--pink: #112233;', false);
        $this->get('/services')->assertSee('--purple: #334455;', false);

        $this->put(route('website.theme.update'), array_replace($values, [
            'primary_color' => '#aabbcc',
        ]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('website_theme_settings', 1);
        $this->assertSame('#aabbcc', app(ThemeService::class)->settings()['primary_color']);
    }

    public function test_css_payloads_and_non_hex_values_cannot_be_saved(): void
    {
        $this->actingAs($this->admin());
        $valid = [
            'primary_color' => '#112233',
            'text_color' => '#334455',
            'surface_color' => '#fafafa',
        ];

        foreach (['red', '#fff', '#111111; background:url(https://evil.example)', '<script>'] as $payload) {
            $this->put(route('website.theme.update'), array_replace($valid, [
                'primary_color' => $payload,
            ]))->assertSessionHasErrors('primary_color');
        }
        $this->assertDatabaseCount('website_theme_settings', 0);
    }
}
