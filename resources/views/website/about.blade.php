@extends('website.layout')
@section('title', 'درباره پزشک')
@section('content')
<nav class="ui-breadcrumb" aria-label="مسیر صفحه"><a href="{{ route('website.home') }}">خانه</a><span aria-hidden="true">/</span><span aria-current="page">درباره پزشک</span></nav>
<h1>درباره {{ $websiteTheme['brand_name'] }}</h1>
<section class="about-grid mt-4">
    @if($websitePortrait)<img class="doctor-portrait" src="{{ route('website.media.show', $websitePortrait) }}" alt="{{ $websitePortrait->alt_text }}">@else<img class="hero-illustration" src="{{ asset('assets/clinic-art.svg') }}" alt="" width="640" height="600">@endif
    <div><p class="eyebrow">{{ $websiteTheme['brand_subtitle'] }}</p><h2>{{ $websiteTheme['about_title'] }}</h2><p class="preserve-lines">{{ $websiteTheme['about_text'] }}</p><div class="hero-actions"><a class="btn-primary-site" href="{{ route('calendar') }}">رزرو نوبت آنلاین</a><a class="btn-outline-site" href="{{ route('website.contact') }}">اطلاعات مطب</a></div></div>
</section>
@endsection
