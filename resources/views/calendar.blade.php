@extends('layouts.app')
@push('assets')
<link rel="stylesheet" href="{{ asset('assets/calendar.css') }}">
<script type="module" src="{{ asset('assets/calendar.js') }}?v=20261027-ux2"></script>
<script type="module" src="{{ asset('assets/booking-flow.js') }}?v=20261027-ux2"></script>
@if($deskBooking ?? false)<script src="{{ asset('assets/desk.js') }}" defer></script>@endif
@endpush
@section('page-heading', ($deskBooking ?? false) ? 'ثبت نوبت در مطب' : 'دریافت نوبت')
@section('content')
<div class="section-heading"><div><h1 class="h3 mb-1">{{ ($deskBooking ?? false) ? 'نوبت تلفنی یا فوری' : ($editing ? 'انتخاب زمان جدید' : 'دریافت نوبت مطب') }}</h1><p class="text-secondary mb-0">خدمت و زمان مناسب را انتخاب کنید؛ سپس مشخصات را ثبت کنید.</p></div><a class="btn btn-outline-secondary" href="{{ route('appointments.index') }}">نوبت‌های من</a></div>
@if(!($deskBooking ?? false))<ol class="booking-steps" aria-label="مراحل رزرو"><li data-flow-step aria-current="step"><span>۱</span> انتخاب زمان</li><li data-flow-step><span>۲</span> مشخصات بیمار</li><li data-flow-step><span>۳</span> پرداخت و تأیید</li></ol>@endif
<div id="booking-calendar" @if(!($deskBooking ?? false) && !($actor?->staff() ?? false)) data-public-flow @endif data-month="{{ $initialMonth }}" data-month-url="{{ $editing ? route('appointments.calendar.month',$editing->reference) : route('calendar.month') }}" data-day-url="{{ $editing ? route('appointments.calendar.day',$editing->reference) : route('calendar.day') }}">
<div class="booking-workspace">
<section class="ux-panel booking-time-panel" aria-labelledby="time-choice-title"><h2 id="time-choice-title" class="h5">۱. خدمت و زمان مراجعه</h2>
<label class="form-label" for="service">چه خدمتی نیاز دارید؟</label>
<select class="form-select mb-3" id="service" @disabled($services->isEmpty())>
@forelse($services as $service)<option value="{{ $service->id }}" data-price="{{ $service->price_irr }}">{{ $service->name }} — {{ number_format($service->price_irr) }} ریال — {{ $service->duration_minutes }} دقیقه</option>@empty<option>خدمتی برای نمایش فعال نیست.</option>@endforelse</select>
<div class="calendar-layout"><section aria-label="تقویم شمسی"><div id="inline-calendar"></div><p class="calendar-legend">آزاد · تکمیل ظرفیت · تعطیل · گذشته · خارج افق</p><p id="calendar-status" role="status" aria-live="polite"></p><button id="calendar-retry" class="btn btn-outline-primary" type="button" hidden>تلاش دوباره</button></section>
<section aria-labelledby="slots-title"><h3 id="slots-title" class="h5">ساعت‌های آزاد</h3><p id="slots-status" role="status" aria-live="polite">ابتدا روز دلخواه را در تقویم انتخاب کنید.</p><div id="slots" class="slot-grid"></div><p id="slot-selection" role="status" aria-live="polite"></p></section></div>
</section>
<aside class="ux-panel booking-patient-panel" aria-label="ثبت مشخصات">
@if($deskBooking ?? false)@include('desk.booking-form')@else
<div data-selection-help class="booking-guidance" @if($actor?->staff()) hidden @endif><span class="shortcut-icon" aria-hidden="true">۲</span><h2 class="h5">بعد از انتخاب زمان</h2><p>مشخصات بیمار را وارد می‌کنید و سپس برای قطعی‌شدن نوبت، پرداخت را انجام می‌دهید.</p><small>انتخاب روز و ساعت به‌تنهایی نوبت ثبت نمی‌کند.</small></div>
<div data-booking-summary class="selection-summary" hidden><strong data-summary-service></strong><p class="mb-2" data-summary-time></p><button class="btn btn-outline-primary btn-sm" type="button" data-go-patient>ادامه و مشخصات بیمار</button></div>
@include('booking.form')@endif
</aside>
</div>
</div>
<noscript><p class="alert alert-warning">برای انتخاب زمان و دریافت ساعت‌ها، جاوااسکریپت مرورگر را فعال کنید.</p></noscript>
@endsection
