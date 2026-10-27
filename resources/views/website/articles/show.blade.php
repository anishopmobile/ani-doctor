@extends('website.layout')
@section('title', e($article->title))
@section('meta_description', e($article->meta_description ?: ($article->summary ?: 'آموزش سلامت زنان در وب‌سایت دکتر زهرا باقری')))
@section('content')
<nav aria-label="مسیر صفحه"><a href="{{ route('website.home') }}">خانه</a> / <a href="{{ route('website.articles.index') }}">مقالات</a> / {{ $article->title }}</nav>
<article class="service-detail mt-4">
    <h1>{{ $article->title }}</h1>
    @if($article->media)<img class="cms-media cms-media-large" src="{{ route('website.media.show', $article->media->id) }}" alt="{{ $article->media->alt_text }}">@endif
    <p>@foreach($article->taxonomies as $taxonomy)
        <a class="me-2" href="{{ route('website.articles.index', [$taxonomy->type === 'tag' ? 'tag' : 'category' => $taxonomy->slug]) }}">{{ $taxonomy->name }}</a>
    @endforeach</p>
    @if($article->summary)<p class="lead">{{ $article->summary }}</p>@endif
    <div class="cms-copy">{{ $article->body }}</div>
    <p class="text-secondary mt-4">مطالب آموزشی جایگزین تشخیص و توصیه شخصی پزشک نیستند.</p>
    <a class="btn-outline-site" href="{{ route('website.articles.index') }}">بازگشت به فهرست مقالات</a>
</article>
@endsection
