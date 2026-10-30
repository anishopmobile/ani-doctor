<?php

namespace App\Modules\Website\Application;

final class FooterService
{
    public function data(array $theme): array
    {
        $links = app(LinkService::class);
        $visible = fn ($link) => LinkService::validUrl($link->url)
            && (!empty($theme['show_weekly_schedule']) || url($link->url) !== route('weekly-announcements.public'));
        $social = $links->published('social')->filter(fn ($link) => LinkService::validUrl($link->url, true))->map(function ($link): array {
            $host = preg_replace('/\Awww\./', '', strtolower(parse_url($link->url, PHP_URL_HOST) ?? ''));
            $icon = match ($host) {
                'instagram.com' => 'instagram', 't.me', 'telegram.me' => 'telegram',
                'linkedin.com' => 'linkedin', 'aparat.com', 'youtube.com', 'youtu.be' => 'play', default => 'link',
            };
            return ['label' => $link->label, 'url' => $link->url, 'icon' => $icon, 'new_tab' => $link->new_tab];
        });
        return [
            'services' => app(ServiceContentService::class)->catalogQuery()->without(['media', 'websiteContent'])->select(['services.id', 'services.name'])->limit(8)->get(),
            'links' => $links->published('footer')->filter($visible),
            'legal' => $links->published('footer_legal')->filter($visible),
            'social' => $social,
        ];
    }
}
