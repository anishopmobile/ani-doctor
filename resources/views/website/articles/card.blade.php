<article class="article-card">
    <a class="article-card-image" href="{{ route('website.articles.show', $article->slug) }}" tabindex="-1" aria-hidden="true"><x-article-image :article="$article" alt=""/></a>
    <div class="article-card-content">
        <div class="article-categories">@foreach($article->taxonomies->where('type', 'category') as $category)<a href="{{ route('website.articles.index', ['category' => $category->slug]) }}">{{ $category->name }}</a>@endforeach</div>
        <h2><a href="{{ route('website.articles.show', $article->slug) }}">{{ $article->title }}</a></h2>
        @if($article->summary)<p class="article-excerpt">{{ $article->summary }}</p>@endif
        <div class="article-card-footer"><span><x-ui-icon name="calendar"/>@include('website.articles.date')</span><a href="{{ route('website.articles.show', $article->slug) }}" aria-label="ادامه مطلب: {{ $article->title }}">ادامه مطلب <x-ui-icon name="arrow"/></a></div>
    </div>
</article>
