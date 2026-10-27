@extends('website.layout')
@section('title', e($service->name))
@section('meta_description', e('اطلاعات نوبت‌دهی خدمت '.$service->name.' در مطب دکتر زهرا باقری'))
@section('content')
<nav aria-label="مسیر صفحه"><a href="{{ route('website.home') }}">خانه</a> / <a href="{{ route('website.services.index') }}">خدمات</a> / {{ $service->name }}</nav>
<article class="service-detail mt-4">
    <span class="service-icon" aria-hidden="true">✿</span>
    <h1>{{ $service->name }}</h1>
    <p>مدت زمان خدمت: {{ $service->duration_minutes }} دقیقه</p>
    <p>تعرفه ثبت‌شده: {{ number_format($service->price_irr) }} ریال</p>
    <p class="text-secondary">زمان‌های نوبت و مبلغ نهایی پیش از ثبت در سامانه اصلی بررسی می‌شوند. صرف مشاهده این صفحه به معنی رزرو نیست.</p>
    <a href="{{ route('calendar') }}" class="btn-primary-site">مشاهده تقویم و دریافت نوبت</a>
</article>
@endsection
