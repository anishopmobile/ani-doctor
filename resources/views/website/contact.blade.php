@extends('website.layout')
@section('title', 'اطلاعات تماس و مطب')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/information-pages.css') }}">
<div class="info-pages">
<header class="info-hero"><p class="info-eyebrow">ارتباط با {{ $websiteTheme['brand_name'] }}</p><h1>{{ $websiteTheme['contact_title'] }}</h1><p>{{ $websiteTheme['brand_subtitle'] }}</p></header>
<section class="info-section info-layout">
<aside class="info-card info-contact-details"><h2>اطلاعات تماس</h2>@include('website.partials.contact-details')<div class="info-actions"><a class="info-button" href="{{ route('calendar') }}">دریافت نوبت آنلاین</a></div></aside>
<div class="info-contact-main"><article class="info-card"><span class="info-icon"><x-ui-icon name="users"/></span><h2>ارتباط مستقیم با مطب</h2><p>برای پرسش‌های عمومی و هماهنگی مراجعه، از راه‌های ارتباطی ثبت‌شده مطب استفاده کنید.</p><div class="info-actions"><a class="info-button" href="tel:{{ $websiteTheme['contact_phone'] }}">تماس با مطب</a>@if($websiteTheme['contact_email'])<a class="info-button info-button--outline" href="mailto:{{ $websiteTheme['contact_email'] }}">ارسال ایمیل</a>@endif</div></article>
<article class="info-card info-section"><h2>موقعیت مطب</h2><div class="info-map"><x-ui-icon name="pin"/><p class="info-copy">{{ $websiteTheme['contact_address'] }}</p>@if($websiteTheme['contact_map_url'])<a class="info-button info-button--outline" href="{{ $websiteTheme['contact_map_url'] }}" target="_blank" rel="noopener noreferrer">مشاهده نقشه و مسیریابی</a>@endif</div></article></div>
</section>
@include('website.partials.information-cta')
@include('website.partials.information-links')
</div>
@endsection
