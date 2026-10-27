<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('meta_description', e($websiteTheme['seo_description']))">
    @include('website.partials.seo')
    <title>@yield('title', 'خدمات مطب') | {{ $websiteTheme['brand_name'] }}</title>
    <link rel="sitemap" type="application/xml" href="{{ route('website.sitemap') }}">
    @unless(($isPreview ?? false) || request()->hasAny(['category', 'tag', 'page']))
        <link rel="canonical" href="{{ $canonicalUrl ?? url()->current() }}">
    @endunless
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap.rtl.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/website.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/studio.css') }}">
    @include('website.partials.theme-css')
</head>
<body class="clinic-site">
<a href="#main" class="visually-hidden-focusable">رفتن به محتوای اصلی</a>
<header class="website-header">
    <div class="container header-inner">
        <a class="brand" href="{{ route('website.home') }}">@include('website.partials.brand-logo')<span>{{ $websiteTheme['brand_name'] }}<small>{{ $websiteTheme['brand_subtitle'] }}</small></span></a>
        <nav class="main-nav" aria-label="ناوبری اصلی">
            @forelse($headerLinks as $link)
                <a href="{{ $link->url }}" @if($link->new_tab) target="_blank" rel="noopener noreferrer" @endif>{{ $link->label }}</a>
            @empty
            <a href="{{ route('website.home') }}">خانه</a>
            <a href="{{ route('website.services.index') }}">خدمات</a>
            <a href="{{ route('website.articles.index') }}">مقالات</a>
            <a href="{{ route('website.home') }}#contact">تماس با ما</a>
            @endforelse
        </nav>
        <a href="{{ route('calendar') }}" class="btn-primary-site">رزرو نوبت آنلاین</a>
    </div>
</header>
<main id="main" class="section">
    <div class="container">@yield('content')</div>
</main>
<footer class="website-footer"><div class="container footer-inner">
    <div><strong>{{ $websiteTheme['brand_name'] }}</strong><p>{{ $websiteTheme['brand_subtitle'] }}</p></div>
    <nav aria-label="دسترسی فوتر">@forelse($footerLinks as $link)<a href="{{ $link->url }}" @if($link->new_tab) target="_blank" rel="noopener noreferrer" @endif>{{ $link->label }}</a>@empty<a href="{{ route('website.home') }}">صفحه اصلی</a><a href="{{ route('website.services.index') }}">خدمات</a><a href="{{ route('website.articles.index') }}">مقالات</a><a href="{{ route('calendar') }}">نوبت‌دهی</a>@endforelse</nav>
</div></footer>
</body>
</html>
