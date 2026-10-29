@extends('website.layout')
@section('title', 'خدمات قابل رزرو')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/information-pages.css') }}">
<div class="info-pages">
<section class="info-hero info-split"><div><p class="info-eyebrow">حوزه‌های تخصصی {{ $websiteTheme['brand_name'] }}</p><h1>{{ $websiteTheme['services_title'] }}</h1><p class="info-copy">{{ $websiteTheme['services_intro'] }}</p><div class="info-actions"><a class="info-button" href="{{ route('calendar') }}">انتخاب خدمت و دریافت نوبت</a></div></div><div class="info-visual"><img src="{{ asset('assets/clinic-art.webp') }}" alt="" width="1254" height="1254"></div></section>
<section class="info-section"><header class="info-section-heading"><h2>آشنایی با خدمات قابل رزرو</h2></header><div class="info-grid">
@forelse($services as $service)
<article class="info-card info-service">
@if($service->websiteContent && $service->media)<img src="{{ route('website.media.show', $service->media) }}" alt="{{ $service->media->alt_text }}" width="400" height="180" loading="lazy">@else<span class="info-icon"><x-ui-icon name="heart"/></span>@endif
<h2>{{ $service->name }}</h2>@if($service->websiteContent?->summary)<p class="info-copy">{{ $service->websiteContent->summary }}</p>@endif<p>مدت خدمت: {{ $service->duration_minutes }} دقیقه</p><p>تعرفه ثبت‌شده: {{ number_format($service->price_irr) }} ریال</p><div class="info-actions"><a class="info-button info-button--outline" href="{{ route('website.services.show', $service->id) }}" aria-label="جزئیات {{ $service->name }}">مشاهده جزئیات ←</a><a class="info-button" href="{{ route('calendar', ['service_id' => $service->id]) }}">دریافت نوبت</a></div>
</article>
@empty<p class="empty-services info-notice">در حال حاضر خدمتی برای رزرو آنلاین موجود نیست.</p>@endforelse
</div><div class="info-section">{{ $services->links() }}</div></section>
@include('website.partials.information-cta')
@include('website.partials.information-links')
</div>
@endsection
