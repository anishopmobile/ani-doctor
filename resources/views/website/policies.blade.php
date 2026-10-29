@extends('website.layout')
@section('title', 'سیاست‌های کاری')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/information-pages.css') }}">
<div class="info-pages">
    <header class="info-hero"><p class="info-eyebrow">راهنمای مراجعه‌کنندگان {{ $websiteTheme['brand_name'] }}</p><h1>{{ $snapshot['title'] ?? 'سیاست‌های کاری' }}</h1><p>پیش از دریافت نوبت، شرایط جاری ثبت، پرداخت، تغییر و لغو نوبت را بررسی کنید.</p></header>
    <section class="info-section info-grid" aria-label="خلاصه شرایط جاری سامانه">@foreach($rules as $rule)<article class="info-card"><span class="info-icon"><x-ui-icon :name="$rule['icon']"/></span><h2>{{ $rule['title'] }}</h2><p>{{ $rule['summary'] }}</p></article>@endforeach</section>
    <div class="info-layout info-section"><aside class="info-card info-sidebar"><h2>فهرست راهنما</h2><nav class="info-toc" aria-label="فهرست شرایط نوبت‌دهی">@foreach($rules as $rule)<a href="#policy-{{ $rule['id'] }}">{{ $rule['title'] }}</a>@endforeach</nav></aside><div class="info-main">@foreach($rules as $rule)<article class="info-card info-article" id="policy-{{ $rule['id'] }}"><h2>{{ $loop->iteration }}. {{ $rule['title'] }}</h2><p>{{ $rule['summary'] }}</p><p>{{ $rule['text'] }}</p></article>@endforeach</div></div>
    @include('website.partials.information-published')
    <aside class="info-notice"><p>مقادیر بالا از تنظیمات جاری سامانه خوانده می‌شوند. امکان انجام هر درخواست در زمان ثبت و با توجه به وضعیت نوبت دوباره بررسی می‌شود.</p></aside>
    @include('website.partials.information-cta')
    @include('website.partials.information-links')
</div>
@endsection
