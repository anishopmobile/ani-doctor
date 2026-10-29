@extends('website.layout')
@section('title', 'حریم خصوصی')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/information-pages.css') }}">
<div class="info-pages">
    <header class="info-hero"><p class="info-eyebrow">اطلاعات و حریم خصوصی مراجعان</p><h1>{{ $snapshot['title'] ?? 'حریم خصوصی' }}</h1><h2>{{ $websiteTheme['brand_name'] }}</h2><p>برای اطلاع از سیاست حریم خصوصی مطب و پیگیری درخواست‌های مرتبط، اطلاعات منتشرشده این صفحه و راه‌های ارتباطی رسمی را بررسی کنید.</p></header>
    @include('website.partials.information-published')
    @unless(isset($snapshot))
        <section class="info-section info-grid" aria-label="راهنمای اطلاعات سامانه">@foreach(array_slice($privacyGuide, 0, 4) as $item)<article class="info-card"><span class="info-icon"><x-ui-icon :name="$item['icon']"/></span><h2>{{ $item['title'] }}</h2><p>{{ $item['summary'] }}</p></article>@endforeach</section>
        <div class="info-layout info-section"><aside class="info-card info-sidebar"><h2>موضوعات</h2><nav class="info-toc" aria-label="فهرست حریم خصوصی">@foreach($privacyGuide as $item)<a href="#privacy-{{ $item['id'] }}">{{ $item['title'] }}</a>@endforeach</nav></aside><div class="info-main">@foreach($privacyGuide as $item)<article class="info-card info-article" id="privacy-{{ $item['id'] }}"><h2>{{ $loop->iteration }}. {{ $item['title'] }}</h2><p>{{ $item['text'] }}</p>@if($loop->last)<a class="info-button" href="{{ route('website.contact') }}">ارتباط با مطب</a>@endif</article>@endforeach</div></div>
    @endunless
    @include('website.partials.information-links')
</div>
@endsection
