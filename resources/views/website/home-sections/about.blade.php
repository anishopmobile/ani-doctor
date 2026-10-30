<link rel="stylesheet" href="{{ asset('assets/home-about.css') }}">
<script src="{{ asset('assets/home-about.js') }}" defer></script>
<section class="section home-about home-about--{{ $websiteTheme['home_about_layout'] }}" id="about" aria-labelledby="home-about-title">
<div class="container ha-inner">
    <div class="ha-content"><p class="ha-label"><span aria-hidden="true"></span>درباره {{ $websiteTheme['brand_name'] }}</p><h2 id="home-about-title">{{ $websiteTheme['about_title'] }}</h2><p class="ha-subtitle">{{ $websiteTheme['brand_subtitle'] }}</p><p class="ha-description">{{ $websiteTheme['about_text'] }}</p>
        @if($websiteTheme['home_about_layout'] === 'dark')
            @if($websiteTheme['home_about_stats'])<div class="ha-stats" aria-label="اطلاعات تأییدشده مطب">@foreach($websiteTheme['home_about_stats'] as $item)<div class="ha-stat"><span class="ha-icon"><x-ui-icon :name="['file','heart','shield'][$loop->index % 3]"/></span><div><strong>{{ $item['title'] }}</strong><span>{{ $item['text'] }}</span></div></div>@endforeach</div>@endif
        @else
            @if($websiteTheme['home_about_features'])<div class="ha-features">@foreach($websiteTheme['home_about_features'] as $item)<article class="ha-feature"><span class="ha-icon"><x-ui-icon :name="['spark','users','shield','heart'][$loop->index % 4]"/></span><h3>{{ $item['title'] }}</h3><p>{{ $item['text'] }}</p></article>@endforeach</div>@endif
        @endif
        <a class="ha-button" href="{{ url($websiteTheme['home_about_button_url'] ?: '/about') }}">{{ $websiteTheme['home_about_button_label'] ?: 'بیشتر درباره ما' }}<x-ui-icon name="arrow"/></a>
    </div>
    <div class="ha-visual"><div @class(['ha-frame','ha-frame--fallback'=>!$homeAboutImage['custom']])><img src="{{ $homeAboutImage['url'] }}" alt="{{ $homeAboutImage['alt'] }}" width="800" height="600" loading="lazy" decoding="async" data-home-about-image data-fallback-src="{{ asset('assets/clinic-art.webp') }}"></div>
        @if($websiteTheme['home_about_layout'] !== 'classic' && ($websiteTheme['home_about_badge_title'] || $websiteTheme['home_about_badge_text']))<aside class="ha-badge"><img src="{{ asset('assets/icons/pregnancy-care.svg') }}" alt="" width="56" height="56">@if($websiteTheme['home_about_badge_title'])<strong>{{ $websiteTheme['home_about_badge_title'] }}</strong>@endif @if($websiteTheme['home_about_badge_text'])<span>{{ $websiteTheme['home_about_badge_text'] }}</span>@endif<x-ui-icon name="heart"/></aside>@endif
    </div>
</div>
<span class="ha-decoration" aria-hidden="true"><x-ui-icon name="spark"/></span>
</section>
