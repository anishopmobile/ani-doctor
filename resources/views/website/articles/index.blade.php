@extends('website.layout')
@section('title', 'مقالات و آموزش‌ها')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/articles.css') }}">
<script src="{{ asset('assets/articles.js') }}" defer></script>
<div class="articles-page">
<header class="articles-hero"><div><nav class="articles-breadcrumb" aria-label="مسیر صفحه"><a href="{{ route('website.home') }}">صفحه اصلی</a><span aria-hidden="true"> / </span><span aria-current="page">مقالات</span></nav><h1>مقالات</h1><h2>دانش، آگاهی و آرامش برای سلامتی شما</h2><p>مطالب آموزشی و منتشرشده {{ $websiteTheme['brand_name'] }} را مطالعه کنید. این مطالب برای آگاهی عمومی هستند و جایگزین مشاوره یا معاینه پزشکی نیستند.</p></div><img src="{{ asset('assets/images/article-default.svg') }}" width="800" height="450" alt="" class="articles-hero-art"></header>
@if($errors->any())<div class="article-validation" role="alert"><h2>فیلترها را بررسی کنید</h2><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form action="{{ route('website.articles.index') }}" method="get" class="articles-filters" aria-label="جستجو و فیلتر مقالات">
<label>دسته‌بندی<select name="category"><option value="">همه دسته‌ها</option>@foreach($categories as $category)<option value="{{ $category->slug }}" @selected(($filters['category'] ?? '') === $category->slug)>{{ $category->name }} ({{ $category->articles_count }})</option>@endforeach</select></label>
<label>بازه انتشار<select name="period"><option value="all">همه زمان‌ها</option><option value="month" @selected(($filters['period'] ?? '') === 'month')>۳۰ روز اخیر</option><option value="year" @selected(($filters['period'] ?? '') === 'year')>۳۶۵ روز اخیر</option></select></label>
<label>مرتب‌سازی<select name="sort"><option value="newest">جدیدترین‌ها</option><option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>قدیمی‌ترین‌ها</option><option value="title" @selected(($filters['sort'] ?? '') === 'title')>عنوان مقاله</option></select></label>
<label class="articles-search">جستجو<input type="search" name="q" maxlength="100" value="{{ $filters['q'] ?? '' }}" placeholder="عنوان، موضوع یا کلمه کلیدی…"></label>
@if(isset($filters['tag']))<input type="hidden" name="tag" value="{{ $filters['tag'] }}">@endif
<button class="article-button" type="submit">جستجو</button></form>
<div class="articles-results"><p role="status">{{ $articles->total() }} مقاله منتشرشده</p>@if(array_filter($filters))<a class="article-text-link" href="{{ route('website.articles.index') }}">پاک‌کردن فیلترها</a>@endif</div>
<section class="articles-grid" aria-label="فهرست مقالات">@forelse($articles as $article)@include('website.articles.card')@empty<div class="article-empty"><h2>مقاله‌ای پیدا نشد</h2><p>مقاله منتشرشده‌ای مطابق این جستجو وجود ندارد. می‌توانید فیلترها را تغییر دهید یا همه مقالات را ببینید.</p><a class="article-button" href="{{ route('website.articles.index') }}">همه مقالات</a></div>@endforelse</section>
@include('website.articles.pagination')
</div>
@endsection
