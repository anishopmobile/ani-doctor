@extends('layouts.app')
@push('assets')
<link rel="stylesheet" href="{{ asset('assets/vendor/pardis.css') }}">
<link rel="stylesheet" href="{{ asset('assets/calendar.css') }}">
<script type="module" src="{{ asset('assets/calendar.js') }}"></script>
@endpush
@section('content')
<h1 class="h3">تقویم خدمات مطب</h1>
<p>خدمت، روز و ساعت را انتخاب کنید. انتخاب ساعت به‌تنهایی نوبت نمی‌سازد؛ ثبت رزرو موقت نیازمند اقدام صریح و بررسی ظرفیت در سرور است.</p>
<div id="booking-calendar" data-month="{{ $initialMonth }}" data-month-url="{{ $editing ? route('appointments.calendar.month',$editing->reference) : route('calendar.month') }}" data-day-url="{{ $editing ? route('appointments.calendar.day',$editing->reference) : route('calendar.day') }}">
<label class="form-label" for="service">خدمت</label>
<select class="form-select mb-3" id="service" @disabled($services->isEmpty())>
@forelse($services as $service)<option value="{{ $service->id }}" data-price="{{ $service->price_irr }}">{{ $service->name }} — {{ number_format($service->price_irr) }} ریال — {{ $service->duration_minutes }} دقیقه</option>@empty<option>خدمتی برای نمایش فعال نیست.</option>@endforelse
</select>
<div class="calendar-layout">
    <section aria-label="تقویم شمسی"><div id="inline-calendar"></div><p class="calendar-legend">آزاد · تکمیل ظرفیت · تعطیل · گذشته · خارج افق</p><p id="calendar-status" role="status" aria-live="polite"></p><button id="calendar-retry" class="btn btn-outline-primary" type="button" hidden>تلاش دوباره</button></section>
    <section aria-labelledby="slots-title"><h2 id="slots-title" class="h5">ساعت‌های آزاد</h2><p id="slots-status" role="status" aria-live="polite">یک روز قابل رزرو انتخاب کنید.</p><div id="slots" class="slot-grid"></div><p id="slot-selection" role="status" aria-live="polite"></p></section>
</div>
@include('booking.form')
</div>
<noscript><p class="alert alert-warning">برای دریافت ساعت‌ها، جاوااسکریپت مرورگر را فعال کنید.</p></noscript>
@endsection
