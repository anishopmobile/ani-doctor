<?php

namespace App\Modules\Website\Application;

use App\Modules\Website\Infrastructure\WebsiteSection;
use Illuminate\Database\Eloquent\Collection;

final class SectionService
{
    public function published(): Collection
    {
        return WebsiteSection::query()->where('is_published', true)->orderBy('sort_order')->orderBy('id')->get();
    }

    public function save(array $data, ?WebsiteSection $section = null): WebsiteSection
    {
        $section ??= new WebsiteSection();
        $section->fill($data);
        $section->save();

        return $section;
    }

    public function remove(WebsiteSection $section): void
    {
        $section->delete();
    }
}
