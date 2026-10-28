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
    <link rel="stylesheet" href="{{ asset('assets/design-system.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/website.css') }}">
    <script src="{{ asset('assets/ui.js') }}" defer></script>
    @include('website.partials.favicon')

    @include('website.partials.theme-css')
</head>
<body class="clinic-site">
<a href="#main" class="visually-hidden-focusable">رفتن به محتوای اصلی</a>
@include('website.partials.header')
<main id="main" class="section">
    <div class="container">@yield('content')</div>
</main>
@include('website.partials.footer')
</body>
</html>
