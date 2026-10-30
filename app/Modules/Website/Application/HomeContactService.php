<?php

namespace App\Modules\Website\Application;

final class HomeContactService
{
    public function data(array $theme): array
    {
        $cards = [];
        foreach ([
            ['contact_address', 'آدرس مطب', 'pin', null, false],
            ['contact_phone', 'شماره تماس', 'phone', 'tel:', true],
            ['contact_secondary_phone', 'شماره تماس دوم', 'phone', 'tel:', true],
            ['contact_email', 'ایمیل', 'mail', 'mailto:', true],
            ['contact_hours', 'ساعات پاسخ‌گویی', 'clock', null, false],
            ['contact_website', 'وب‌سایت رسمی', 'link', '', true],
        ] as [$key, $title, $icon, $prefix, $ltr]) {
            if ($theme[$key] !== '') {
                $cards[] = ['title' => $title, 'icon' => $icon, 'text' => $theme[$key], 'url' => $prefix === null ? null : $prefix.$theme[$key], 'ltr' => $ltr, 'external' => $key === 'contact_website'];
            }
        }
        $whatsapp = app(LinkService::class)->published('social')->first(fn ($link) => LinkService::validUrl($link->url, true) && in_array(strtolower(parse_url($link->url, PHP_URL_HOST) ?? ''), ['wa.me', 'api.whatsapp.com', 'www.whatsapp.com', 'whatsapp.com'], true));
        return ['cards' => $cards, 'whatsapp' => $whatsapp?->url];
    }
}
