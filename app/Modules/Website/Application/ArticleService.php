<?php

namespace App\Modules\Website\Application;

use App\Modules\Website\Infrastructure\WebsiteArticle;
use App\Modules\Website\Infrastructure\WebsiteTaxonomy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ArticleService
{
    public function listing(array $filters): LengthAwarePaginator
    {
        $query = $this->publicQuery()->with(['taxonomies', 'media']);
        foreach (['category', 'tag'] as $type) {
            if (isset($filters[$type]) && $filters[$type] !== '') {
                $query->whereHas('taxonomies', fn ($taxonomies) => $taxonomies->where('type', $type)->where('slug', $filters[$type]));
            }
        }
        if (isset($filters['q']) && trim($filters['q']) !== '') {
            $needle = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($filters['q'])).'%';
            $query->where(function ($search) use ($needle): void {
                $search->whereRaw("title LIKE ? ESCAPE '!'", [$needle])
                    ->orWhereRaw("summary LIKE ? ESCAPE '!'", [$needle])
                    ->orWhereRaw("body LIKE ? ESCAPE '!'", [$needle]);
            });
        }
        if (in_array($filters['period'] ?? 'all', ['month', 'year'], true)) {
            $query->where('published_at', '>=', ($filters['period'] === 'month' ? now()->subDays(30) : now()->subDays(365)));
        }
        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->orderBy('published_at'),
            'title' => $query->orderBy('title'),
            default => $query->orderByDesc('published_at'),
        };
        return $query->orderByDesc('id')->paginate(12)->appends(array_filter($filters, fn ($value, $key) => $key !== 'page' && $value !== null && $value !== '', ARRAY_FILTER_USE_BOTH));
    }

    public function categories(): Collection
    {
        return WebsiteTaxonomy::query()->where('type', 'category')
            ->withCount(['articles' => fn ($query) => $query->where('is_published', true)->whereNotNull('published_at')->where('published_at', '<=', now())])
            ->orderBy('name')->get();
    }

    public function latest(?int $except = null): Collection
    {
        return $this->publicQuery()->when($except, fn ($query) => $query->whereKeyNot($except))
            ->with(['taxonomies', 'media'])->orderByDesc('published_at')->orderByDesc('id')->limit(4)->get();
    }

    public function related(WebsiteArticle $article): Collection
    {
        $categories = $article->taxonomies->where('type', 'category')->pluck('id');
        return $this->publicQuery()->whereKeyNot($article->id)
            ->whereHas('taxonomies', fn ($query) => $query->whereIn('website_taxonomies.id', $categories))
            ->with(['taxonomies', 'media'])->orderByDesc('published_at')->orderByDesc('id')->limit(4)->get();
    }

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
        unset($data['taxonomy_ids'], $data['published_jalali'], $data['published_time']);
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
