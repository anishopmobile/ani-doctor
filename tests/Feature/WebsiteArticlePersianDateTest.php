<?php

namespace Tests\Feature;

use App\Modules\Identity\Infrastructure\Role;
use App\Modules\Identity\Infrastructure\User;
use App\Modules\Website\Infrastructure\WebsiteArticle;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WebsiteArticlePersianDateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $admin = User::create(['mobile' => '09000000553']);
        $admin->forceFill(['mobile_verified_at' => now()])->save();
        $admin->roles()->attach(Role::where('name', 'admin')->sole());
        $this->actingAs($admin);
    }

    private function payload(array $extra = []): array
    {
        return array_replace(['slug' => 'persian-date', 'title' => 'تاریخ آزمایشی', 'body' => 'متن', 'is_published' => 1], $extra);
    }

    public function test_persian_digits_and_tehran_time_are_stored_as_utc_and_render_back_in_persian(): void
    {
        $this->post('/admin/website-articles', $this->payload(['published_jalali' => '۱۴۰۵/۰۸/۱۲', 'published_time' => '00:15']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $article = WebsiteArticle::sole();
        $this->assertSame('2026-11-02 20:45:00', $article->published_at->utc()->format('Y-m-d H:i:s'));
        $this->get('/admin/website-articles')->assertOk()
            ->assertSee('1405-08-12')->assertSee('00:15');
    }

    public function test_invalid_calendar_day_and_time_do_not_write_an_article(): void
    {
        foreach ([['1405-07-31', '12:00', 'published_jalali'], ['1405-08-12', '24:01', 'published_time'], [['bad'], '12:00', 'published_jalali']] as [$date, $time, $error]) {
            $this->postJson('/admin/website-articles', $this->payload(['published_jalali' => $date, 'published_time' => $time]))
                ->assertUnprocessable()->assertJsonValidationErrors($error);
        }
        $this->assertDatabaseCount('website_articles', 0);
    }

    public function test_empty_date_does_not_implicitly_publish_and_legacy_utc_contract_still_works(): void
    {
        $this->post('/admin/website-articles', $this->payload(['published_jalali' => '', 'published_time' => '00:00']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull(WebsiteArticle::sole()->published_at);
        $this->get('/articles/persian-date')->assertNotFound();
        $this->post('/admin/website-articles', $this->payload(['slug' => 'legacy-date', 'published_at' => '2026-10-27 10:00:00']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('2026-10-27 10:00:00', WebsiteArticle::where('slug', 'legacy-date')->sole()->published_at->utc()->format('Y-m-d H:i:s'));
    }
}
