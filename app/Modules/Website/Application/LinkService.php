<?php

namespace App\Modules\Website\Application;

use App\Modules\Website\Infrastructure\WebsiteLink;
use Illuminate\Database\Eloquent\Collection;

final class LinkService
{
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
