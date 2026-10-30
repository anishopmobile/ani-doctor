<?php

namespace App\Modules\Website\Application;

use App\Modules\Website\Infrastructure\WebsiteLink;
use Illuminate\Database\Eloquent\Collection;

final class LinkService
{
    public static function validUrl(string $url, bool $httpsOnly = false): bool
    {
        if (str_starts_with($url, 'https://')) {
            return filter_var($url, FILTER_VALIDATE_URL) !== false
                && !preg_match('/[\s<>"\\\\]/', $url)
                && parse_url($url, PHP_URL_USER) === null && parse_url($url, PHP_URL_PASS) === null;
        }
        return !$httpsOnly && preg_match('~^(?:/(?!/)[a-zA-Z0-9/_#?=&.%-]*|\#[a-zA-Z0-9_-]+)$~D', $url) === 1;
    }

    public function published(string $placement): Collection
    {
        return WebsiteLink::query()->where('placement', $placement)->where('is_published', true)
            ->orderBy('sort_order')->orderBy('id')->get();
    }

    public function save(array $data, ?WebsiteLink $link = null): WebsiteLink
    {
        $link ??= new WebsiteLink();
        $link->fill($data);
        $link->save();

        return $link;
    }

    public function remove(WebsiteLink $link): void
    {
        $link->delete();
    }
}
