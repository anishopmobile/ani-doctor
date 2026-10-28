@extends('website.layout')
@section('title', e($service->name))
@section('meta_description', e($editorial?->summary ?: 'اطلاعات نوبت‌دهی خدمت '.$service->name))
@section('content')
<nav aria-label="مسیر صفحه"><a href="{{ route('website.home') }}">خانه</a> / <a href="{{ route('website.services.index') }}">خدمات</a> / {{ $service->name }}</nav>
<article class="service-detail mt-4">
    <span class="service-icon" aria-hidden="true"><x-ui-icon name="heart"/></span>
    <h1>{{ $service->name }}</h1>
    @if($editorial)
        @if($service->media)
            <img class="img-fluid rounded mb-3" src="{{ route('website.media.show', $service->media) }}" alt="{{ $service->media->alt_text }}">
        @endif
        @if($editorial->summary) <p class="lead">{{ $editorial->summary }}</p> @endif
        @if($editorial->description) <div class="mb-4 preserve-lines">{{ $editorial->description }}</div> @endif
    @endif
    <p>مدت زمان خدمت: {{ $service->duration_minutes }} دقیقه</p>
    <p>تعرفه ثبت‌شده: {{ number_format($service->price_irr) }} ریال</p>
    <p class="text-secondary">زمان‌های نوبت و مبلغ نهایی پیش از ثبت در سامانه اصلی بررسی می‌شوند. صرف مشاهده این صفحه به معنی رزرو نیست.</p>
    <a href="{{ route('calendar', ['service_id' => $service->id]) }}" class="btn-primary-site">مشاهده تقویم و دریافت نوبت</a>
</article>
@endsection
