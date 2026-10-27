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

    public function test_published_slides_render_in_order_without_leaking_drafts_or_regular_sections(): void
    {
        WebsiteSection::create([
            'type' => 'slide', 'title' => 'اسلاید دوم', 'background' => 'blush',
            'sort_order' => 20, 'is_published' => true,
        ]);
        WebsiteSection::create([
            'type' => 'slide', 'title' => 'اسلاید اول', 'background' => 'plain',
            'sort_order' => 10, 'is_published' => true,
        ]);
        WebsiteSection::create([
            'type' => 'slide', 'title' => 'اسلاید پیش‌نویس', 'background' => 'lilac',
            'sort_order' => 0, 'is_published' => false,
        ]);
        WebsiteSection::create([
            'type' => 'about', 'title' => 'متن درباره', 'background' => 'plain',
            'sort_order' => 5, 'is_published' => true,
        ]);

        $response = $this->get('/')->assertOk()
            ->assertSee('اسلاید اول')
            ->assertSee('اسلاید دوم')
            ->assertDontSee('اسلاید پیش‌نویس')
            ->assertSee('data-slide-next', false)
            ->assertSee('متن درباره');

        $this->assertLessThan(
            strpos($response->getContent(), 'اسلاید دوم'),
            strpos($response->getContent(), 'اسلاید اول')
        );
        $this->assertSame(2, substr_count($response->getContent(), 'data-website-slide '));
    }

    public function test_no_published_slides_keeps_original_homepage_hero(): void
    {
        WebsiteSection::create([
            'type' => 'slide', 'title' => 'اسلاید مخفی', 'background' => 'plain',
            'sort_order' => 0, 'is_published' => false,
        ]);

        $this->get('/')->assertOk()
            ->assertSee('مراقبت تخصصی')
            ->assertDontSee('اسلاید مخفی')
            ->assertDontSee('data-slide-next', false);
    }

    public function test_editor_accepts_slide_but_rejects_unapproved_section_types(): void
    {
        $rules = (new \App\Http\Requests\WebsiteSectionRequest())->rules();
        $fields = [
            'type' => 'slide', 'title' => 'معرفی مطب', 'background' => 'blush',
            'sort_order' => 0, 'is_published' => 1,
        ];
        $this->assertTrue(validator($fields, $rules)->passes());
        $this->assertFalse(validator(array_replace($fields, ['type' => 'script']), $rules)->passes());
    }
}
