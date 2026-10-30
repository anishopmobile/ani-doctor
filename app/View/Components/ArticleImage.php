<?php

namespace App\View\Components;

use App\Modules\Website\Infrastructure\WebsiteArticle;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Component;
use Illuminate\View\View;

final class ArticleImage extends Component
{
    public string $source;
    public string $fallback;
    public string $alt;

    public function __construct(public WebsiteArticle $article, public bool $eager = false)
    {
        $this->fallback = asset('assets/images/article-default.svg');
        $media = $article->media;
        $available = $media && preg_match('/\Awebsite-media\/[a-zA-Z0-9_-]+\.(?:png|jpe?g|webp)\z/D', $media->path)
            && in_array($media->mime_type, ['image/png', 'image/jpeg', 'image/webp'], true)
            && Storage::disk('local')->exists($media->path);
        $this->source = $available ? route('website.media.show', $media->id) : $this->fallback;
        $this->alt = $media?->alt_text ?: $article->title;
    }

    public function render(): View
    {
        return view('components.article-image');
    }
}
