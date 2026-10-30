@extends('website.layout')
@section('title', e($article->title))
@section('meta_description', e($article->meta_description ?: ($article->summary ?: 'آموزش سلامت زنان')))
@section('content')
<link rel="stylesheet" href="{{ asset('assets/articles.css') }}">
<script src="{{ asset('assets/articles.js') }}" defer></script>
<div class="articles-page">
<nav class="articles-breadcrumb" aria-label="مسیر صفحه"><a href="{{ route('website.home') }}">صفحه اصلی</a><span aria-hidden="true"> / </span><a href="{{ route('website.articles.index') }}">مقالات</a><span aria-hidden="true"> / </span><span aria-current="page">{{ $article->title }}</span></nav>
<div class="articles-layout"><article class="article-main article-panel">
<x-article-image :article="$article" :eager="true" class="article-featured"/>
<header><h1>{{ $article->title }}</h1>@if($article->summary)<p class="article-lead">{{ $article->summary }}</p>@endif
<div class="article-meta">@if($article->author_name)<span><x-ui-icon name="users"/>{{ $article->author_name }}</span>@endif<span><x-ui-icon name="calendar"/>@include('website.articles.date')</span><div class="article-categories">@foreach($article->taxonomies->where('type', 'category') as $category)<a href="{{ route('website.articles.index', ['category' => $category->slug]) }}">{{ $category->name }}</a>@endforeach</div></div></header>
<div class="article-body">{{ $article->body }}</div>
@if($article->taxonomies->where('type', 'tag')->isNotEmpty())<div class="article-tags" aria-label="برچسب‌های مقاله">@foreach($article->taxonomies->where('type', 'tag') as $tag)<a href="{{ route('website.articles.index', ['tag' => $tag->slug]) }}"># {{ $tag->name }}</a>@endforeach</div>@endif
<aside class="article-booking"><div><h2>برای مشاوره شخصی نوبت بگیرید</h2><p>مطالب آموزشی جایگزین تشخیص و توصیه شخصی پزشک نیستند.</p></div><a class="article-button" href="{{ route('calendar') }}">دریافت نوبت <x-ui-icon name="arrow"/></a></aside>
<footer class="article-share"><h2>اشتراک‌گذاری این مقاله</h2><div class="article-share-actions"><a href="https://t.me/share/url?{{ http_build_query(['url' => route('website.articles.show', $article->slug), 'text' => $article->title], '', '&', PHP_QUERY_RFC3986) }}" target="_blank" rel="noopener noreferrer">تلگرام</a><a href="https://wa.me/?{{ http_build_query(['text' => $article->title.' '.route('website.articles.show', $article->slug)], '', '&', PHP_QUERY_RFC3986) }}" target="_blank" rel="noopener noreferrer">واتساپ</a><a href="https://www.linkedin.com/sharing/share-offsite/?{{ http_build_query(['url' => route('website.articles.show', $article->slug)], '', '&', PHP_QUERY_RFC3986) }}" target="_blank" rel="noopener noreferrer">لینکدین</a><button type="button" data-copy-article="{{ route('website.articles.show', $article->slug) }}">کپی پیوند</button></div><label class="article-share-url">پیوند مقاله<input readonly dir="ltr" value="{{ route('website.articles.show', $article->slug) }}"></label><p data-share-status role="status"></p></footer>
</article>@include('website.articles.sidebar')</div>
</div>
@endsection
