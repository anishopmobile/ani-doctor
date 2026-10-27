@extends('website.layout')
@section('title', 'مقالات و آموزش‌ها')
@section('content')
<nav aria-label="مسیر صفحه"><a href="{{ route('website.home') }}">خانه</a> / مقالات</nav>
<h1 class="mt-3">مقالات و آموزش‌های سلامت زنان</h1>
<p class="text-secondary">این مطالب برای آگاهی عمومی هستند و جایگزین مشاوره یا معاینه پزشکی نیستند.</p>
<div class="service-grid mt-4">
    @forelse($articles as $article)
        <article class="service-card">
            <h2 class="h5">{{ $article->title }}</h2>
            @foreach($article->taxonomies as $taxonomy)
                <a href="{{ route('website.articles.index', [$taxonomy->type === 'tag' ? 'tag' : 'category' => $taxonomy->slug]) }}" class="small">{{ $taxonomy->name }}</a>
            @endforeach
            @if($article->summary)<p>{{ $article->summary }}</p>@endif
            <a href="{{ route('website.articles.show', $article->slug) }}">مطالعه مقاله ←</a>
        </article>
    @empty
        <p class="empty-services">مقاله منتشرشده‌ای وجود ندارد.</p>
    @endforelse
</div>
<div class="mt-4">{{ $articles->links() }}</div>
@endsection
