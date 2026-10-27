<?php

namespace App\Modules\Website\Application;

use App\Modules\Website\Infrastructure\WebsiteArticle;
use Illuminate\Database\Eloquent\Builder;

final class ArticleService
{
    public function publicQuery(): Builder
    {
        return WebsiteArticle::query()
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function save(array $data, ?WebsiteArticle $article = null): WebsiteArticle
    {
        $taxonomyIds = $data['taxonomy_ids'] ?? [];
        unset($data['taxonomy_ids']);
        \Illuminate\Support\Facades\DB::transaction(function () use (&$article, $data, $taxonomyIds): void {
            $article ??= new WebsiteArticle();
            $article->fill($data);
            $article->save();
            $article->taxonomies()->sync($taxonomyIds);
        });

        return $article;
    }

    public function remove(WebsiteArticle $article): void
    {
        $article->media?->deleteWithFile();
        $article->delete();
    }
}
