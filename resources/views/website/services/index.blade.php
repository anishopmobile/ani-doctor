@extends('website.layout')
@section('title', 'خدمات قابل رزرو')
@section('content')
<nav aria-label="مسیر صفحه"><a href="{{ route('website.home') }}">خانه</a> / خدمات</nav>
<h1 class="mt-3">خدمات قابل رزرو مطب</h1>
<p class="text-secondary">خدمات این صفحه از فهرست فعال نوبت‌دهی دریافت می‌شوند. زمان‌های آزاد در تقویم اصلی قابل مشاهده‌اند.</p>
<div class="service-grid mt-4">
    @forelse($services as $service)
        <article class="service-card">
            <span class="service-icon" aria-hidden="true">✿</span>
            <h2 class="h5">{{ $service->name }}</h2>
            <p>مدت خدمت: {{ $service->duration_minutes }} دقیقه</p>
            <a href="{{ route('website.services.show', $service->id) }}" aria-label="جزئیات {{ $service->name }}">مشاهده جزئیات ←</a>
        </article>
    @empty
        <p class="empty-services">در حال حاضر خدمتی برای رزرو آنلاین موجود نیست.</p>
    @endforelse
</div>
<div class="mt-4">{{ $services->links() }}</div>
@endsection
