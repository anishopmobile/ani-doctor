<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $websiteTheme['seo_description'] }}">
    @include('website.partials.seo')
    <title>{{ $websiteTheme['brand_name'] }} | {{ $websiteTheme['brand_subtitle'] }}</title>
    <link rel="sitemap" type="application/xml" href="{{ route('website.sitemap') }}">
    @unless(request()->hasAny(['category', 'tag', 'page']))
        <link rel="canonical" href="{{ url()->current() }}">
    @endunless
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap.rtl.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/design-system.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/website.css') }}">
    <script src="{{ asset('assets/ui.js') }}" defer></script>
    @include('website.partials.favicon')

    @include('website.partials.theme-css')
    <script defer src="{{ asset('assets/website-slider.js') }}"></script>
</head>
<body class="clinic-site">
<a class="visually-hidden-focusable" href="#main">رفتن به محتوای اصلی</a>
@include('website.partials.header')
<main id="main">
    <section class="hero">
        <div class="container hero-content">
            <div class="hero-copy">
                @if($slides->isNotEmpty())
                    <div class="website-slider" data-website-slider aria-roledescription="اسلایدشو" aria-label="معرفی خدمات مطب">
                        <div class="website-slides" aria-live="off">
                            @foreach($slides as $slide)
                                <article class="website-slide" data-website-slide @if(!$loop->first) hidden @endif>
                                    @if($slide->subtitle)<p class="eyebrow">{{ $slide->subtitle }}</p>@endif
                                    @if($loop->first)<h1>{{ $slide->title }}</h1>@else<h2 class="slide-heading">{{ $slide->title }}</h2>@endif
                                    @if($slide->body)<p class="lead slide-copy">{{ $slide->body }}</p>@endif
                                    @if($slide->media)<img class="cms-media" src="{{ route('website.media.show', $slide->media->id) }}" alt="{{ $slide->media->alt_text }}" loading="lazy">@endif
                                    <div class="hero-actions">
                                        @if($slide->button_url && $slide->button_text)
                                            <a class="btn-primary-site" href="{{ $slide->button_url }}">{{ $slide->button_text }}</a>
                                        @endif
                                        <a class="btn-outline-site" href="{{ route('calendar') }}">رزرو نوبت آنلاین</a>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                        @if($slides->count() > 1)
                            <div class="slide-controls" aria-label="کنترل اسلایدها">
                                <button type="button" class="slide-control" data-slide-prev aria-label="اسلاید قبلی">قبلی</button>
                                <span class="slide-counter" data-slide-counter role="status" aria-live="polite">۱ از {{ $slides->count() }}</span>
                                <button type="button" class="slide-control" data-slide-next aria-label="اسلاید بعدی">بعدی</button>
                            </div>
                        @endif
                    </div>
                @else
                    <p class="eyebrow">{{ $websiteTheme['brand_name'] }}</p>
                    <p class="hero-subtitle">{{ $websiteTheme['brand_subtitle'] }}</p>
                    <h1>{{ $websiteTheme['hero_title'] }}</h1>
                    <p class="lead">{{ $websiteTheme['hero_text'] }}</p>
                    <div class="hero-actions"><a class="btn-primary-site" href="{{ $websiteTheme['hero_button_url'] }}">{{ $websiteTheme['hero_button_label'] }}</a><a class="btn-outline-site" href="{{ $websiteTheme['hero_secondary_url'] }}">{{ $websiteTheme['hero_secondary_label'] }}</a></div>
                @endif
            </div>
            <div class="hero-art">@if($websitePortrait)<img class="doctor-portrait" src="{{ route('website.media.show', $websitePortrait) }}" alt="{{ $websitePortrait->alt_text }}" fetchpriority="high">@else<img class="hero-illustration" src="{{ asset('assets/clinic-art.svg') }}" width="640" height="600" alt="">@endif<div class="hero-art-caption"><x-ui-icon name="shield"/><span><strong>{{ $websiteTheme['hero_art_title'] }}</strong><small>{{ $websiteTheme['hero_art_subtitle'] }}</small></span></div></div>
        </div>
        @if($websiteTheme['highlight_items'])<div class="container hero-highlights">
            @foreach($websiteTheme['highlight_items'] as $highlight)<div><x-ui-icon :name="['calendar','file','shield'][$loop->index % 3]"/><strong>{{ $highlight['title'] }}</strong><span>{{ $highlight['text'] }}</span></div>@endforeach
        </div>@endif
    </section>
    @foreach($sections as $section)
    <section class="section cms-section cms-{{ $section->background }}" aria-label="{{ $section->title }}">
        <div class="container">
            @if($section->subtitle)<p class="eyebrow">{{ $section->subtitle }}</p>@endif
            <h2>{{ $section->title }}</h2>
            @if($section->body)<p class="cms-copy">{{ $section->body }}</p>@endif
            @if($section->media)<img class="cms-media" src="{{ route('website.media.show', $section->media->id) }}" alt="{{ $section->media->alt_text }}" loading="lazy">@endif
            @if($section->button_url && $section->button_text)<a class="btn-outline-site" href="{{ $section->button_url }}">{{ $section->button_text }}</a>@endif
        </div>
    </section>
    @endforeach
    @foreach($websiteTheme['section_order'] as $sectionKey)
        @if($websiteTheme['show_'.$sectionKey]) @include('website.home-sections.'.$sectionKey) @endif
    @endforeach
</main>
@include('website.partials.footer')
</body>
</html>
