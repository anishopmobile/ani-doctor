<?php

namespace Tests\Feature;

use App\Modules\Website\Infrastructure\WebsiteLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WebsiteLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_published_links_appear_in_the_correct_public_navigation(): void
    {
        WebsiteLink::create([
            'placement' => 'header', 'label' => 'لینک نمایشی', 'url' => '/calendar',
            'sort_order' => 2, 'is_published' => true, 'new_tab' => false,
        ]);
        WebsiteLink::create([
            'placement' => 'footer', 'label' => 'لینک فوتر', 'url' => 'https://example.ir',
            'sort_order' => 1, 'is_published' => true, 'new_tab' => true,
        ]);
        WebsiteLink::create([
            'placement' => 'header', 'label' => 'پیش‌نویس پنهان', 'url' => '/calendar',
            'sort_order' => 1, 'is_published' => false, 'new_tab' => false,
        ]);

        $this->get('/')->assertOk()->assertSee('لینک نمایشی')
            ->assertSee('لینک فوتر')->assertDontSee('پیش‌نویس پنهان')
            ->assertSee('rel="noopener noreferrer"', false);
    }

    public function test_guest_cannot_manage_navigation(): void
    {
        $this->get('/admin/website-links')->assertRedirect('/login');
        $this->post('/admin/website-links', [])->assertRedirect('/login');
    }

    public function test_disallowed_schemes_and_protocol_relative_urls_fail_validation(): void
    {
        $request = new \App\Http\Requests\WebsiteLinkRequest();
        foreach (['javascript:alert(1)', '//malicious.example', 'http://insecure.example', 'data:text/html,foo'] as $url) {
            $this->assertFalse(validator([
                'placement' => 'header', 'label' => 'نمونه', 'url' => $url,
                'sort_order' => 0, 'is_published' => 1, 'new_tab' => 0,
            ], $request->rules())->passes(), $url);
        }
    }
}
