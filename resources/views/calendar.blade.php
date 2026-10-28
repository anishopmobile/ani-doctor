@extends('layouts.app')
@push('assets')
<link rel="stylesheet" href="{{ asset('assets/vendor/pardis.css') }}">
<link rel="stylesheet" href="{{ asset('assets/calendar.css') }}">
<script type="module" src="{{ asset('assets/calendar.js') }}"></script>
@if($deskBooking ?? false)<script src="{{ asset('assets/desk.js') }}" defer></script>@endif
@endpush
@section('content')
<div class="ux-page-heading"><div><h1 class="h3">{{ $editing ? 'تغییر زمان نوبت' : (($deskBooking ?? false) ? 'ثبت نوبت برای مراجعه‌کننده' : 'رزرو نوبت') }}</h1><p>خدمت و زمان را انتخاب کنید، مشخصات بیمار را بررسی کنید و رزرو را ثبت کنید.</p></div><a class="btn btn-outline-primary" href="{{ route('appointments.index') }}">پیگیری نوبت‌های قبلی</a></div>
<ol class="ux-steps" aria-label="مراحل رزرو"><li><span>۱</span> خدمت و زمان</li><li><span>۲</span> مشخصات بیمار</li><li><span>۳</span> ثبت و پرداخت</li></ol>
<div id="booking-calendar" data-month="{{ $initialMonth }}" data-month-url="{{ $editing ? route('appointments.calendar.month',$editing->reference) : route('calendar.month') }}" data-day-url="{{ $editing ? route('appointments.calendar.day',$editing->reference) : route('calendar.day') }}">
<section class="ux-card"><h2 class="h4"><span class="ux-step-number">۱</span> خدمت و زمان مراجعه</h2><label class="form-label" for="service">برای چه خدمتی مراجعه می‌کنید؟</label>
<select class="form-select mb-3" id="service" @disabled($services->isEmpty())>
@forelse($services as $service)<option value="{{ $service->id }}" data-price="{{ $service->price_irr }}" @selected(!$editing && !($deskBooking ?? false) && is_scalar(request()->query('service_id', '')) && (string) request()->query('service_id', '') === (string) $service->id)>{{ $service->name }} — {{ number_format($service->price_irr) }} ریال — {{ $service->duration_minutes }} دقیقه</option>@empty<option>خدمتی برای نمایش فعال نیست.</option>@endforelse
</select>
<div class="calendar-layout">
    <section aria-label="تقویم شمسی"><div id="inline-calendar"></div><p class="calendar-legend">آزاد · تکمیل ظرفیت · تعطیل · گذشته · خارج افق</p><p id="calendar-status" role="status" aria-live="polite"></p><button id="calendar-retry" class="btn btn-outline-primary" type="button" hidden>تلاش دوباره</button></section>
    <section aria-labelledby="slots-title"><h2 id="slots-title" class="h5">ساعت‌های آزاد</h2><p id="slots-status" role="status" aria-live="polite">یک روز قابل رزرو انتخاب کنید.</p><div id="slots" class="slot-grid"></div><p id="slot-selection" role="status" aria-live="polite"></p></section>
</div>
</section>
<aside class="ux-next-step my-3" data-booking-summary aria-label="خلاصه انتخاب شما"><strong>خلاصه انتخاب شما</strong><p class="mb-1" data-summary-service></p><p class="mb-1" data-summary-time>روز و ساعت را انتخاب کنید.</p><p class="mb-0" data-summary-next></p><a class="btn btn-primary mt-3" href="#booking-patient-details" data-summary-continue hidden>ادامه: مشخصات بیمار</a></aside>
@if($deskBooking ?? false)@include('desk.booking-form')@else @include('booking.form')@endif
</div>
<noscript><p class="alert alert-warning">برای دریافت ساعت‌ها، جاوااسکریپت مرورگر را فعال کنید.</p></noscript>
@endsection
