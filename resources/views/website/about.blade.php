@extends('website.layout')
@section('title', 'درباره پزشک')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/information-pages.css') }}">
<div class="info-pages">
<section class="info-hero info-split">
<div><p class="info-eyebrow">آشنایی با {{ $websiteTheme['brand_name'] }}</p><h1>درباره ما</h1><h2>{{ $websiteTheme['about_title'] }}</h2><p class="info-copy">{{ $websiteTheme['about_text'] }}</p><div class="info-actions"><a class="info-button" href="{{ route('website.services.index') }}">آشنایی با خدمات</a><a class="info-button info-button--outline" href="{{ route('website.contact') }}">راه‌های ارتباطی</a></div></div>
<div class="info-visual">@if($websitePortrait)<img class="info-portrait" src="{{ route('website.media.show', $websitePortrait) }}" alt="{{ $websitePortrait->alt_text }}" width="350" height="340">@else<img src="{{ asset('assets/clinic-art.webp') }}" alt="{{ $websiteTheme['brand_name'] }} — {{ $websiteTheme['brand_subtitle'] }}" width="1254" height="1254">@endif</div>
</section>
@if($websiteTheme['why_items'])<section class="info-section"><header class="info-section-heading"><h2>{{ $websiteTheme['why_title'] }}</h2></header><div class="info-grid">@foreach($websiteTheme['why_items'] as $item)<article class="info-card"><span class="info-icon"><x-ui-icon :name="['heart','shield','spark','file'][$loop->index % 4]"/></span><h3>{{ $item['title'] }}</h3><p class="info-copy">{{ $item['text'] }}</p></article>@endforeach</div></section>@endif
<section class="info-section info-split"><div><p class="info-eyebrow">پزشک همراه شما</p><h2>{{ $websiteTheme['brand_name'] }}</h2><p>{{ $websiteTheme['brand_subtitle'] }}</p><p class="info-copy">{{ $websiteTheme['hero_art_subtitle'] }}</p><a class="info-button info-button--outline" href="{{ route('website.contact') }}">ارتباط با مطب</a></div><aside class="info-cta"><x-ui-icon name="heart"/><h2>{{ $websiteTheme['hero_art_title'] }}</h2><p class="info-copy">{{ $websiteTheme['hero_text'] }}</p></aside></section>
@include('website.partials.information-cta')
@include('website.partials.information-links')
</div>
@endsection
