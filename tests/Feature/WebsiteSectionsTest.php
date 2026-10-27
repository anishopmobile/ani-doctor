<?php

namespace Tests\Feature;

use App\Modules\Website\Infrastructure\WebsiteSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WebsiteSectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_published_sections_are_visible_and_user_html_is_escaped(): void
    {
        WebsiteSection::create([
            'type' => 'about',
            'title' => '<script>alert(1)</script>',
            'subtitle' => 'معرفی',
            'body' => 'متن تست',
            'background' => 'blush',
            'sort_order' => 1,
            'is_published' => true,
        ]);

        WebsiteSection::create([
            'type' => 'cta',
            'title' => 'پیش‌نویس خصوصی',
            'background' => 'plain',
            'sort_order' => 0,
            'is_published' => false,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('پیش‌نویس خصوصی');
    }

    public function test_unauthenticated_visitors_cannot_access_section_admin(): void
    {
        $this->get('/admin/website-sections')->assertRedirect('/login');
        $this->post('/admin/website-sections', [])->assertRedirect('/login');
    }

    public function test_invalid_public_url_or_section_type_cannot_be_saved(): void
    {
        $this->assertFalse(
            validator(
                ['type' => 'raw_html', 'title' => 'x', 'background' => 'plain', 'sort_order' => 0, 'is_published' => 1],
                (new \App\Http\Requests\WebsiteSectionRequest())->rules()
            )->passes()
        );
        $this->assertFalse(
            validator(
                ['type' => 'cta', 'title' => 'x', 'button_text' => 'bad', 'button_url' => 'javascript:alert(1)', 'background' => 'plain', 'sort_order' => 0, 'is_published' => 1],
                (new \App\Http\Requests\WebsiteSectionRequest())->rules()
            )->passes()
        );
    }
}
