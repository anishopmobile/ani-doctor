@extends('layouts.app')
@push('assets')<script src="{{ asset('assets/booking.js') }}" defer></script>@endpush
@section('content')
<h1 class="h3">جزئیات نوبت</h1>
<p>{{ $appointment->patient->name }} — {{ $appointment->service_title }}</p>
<p>تاریخ: <bdi dir="ltr">{{ $jalali->format($appointment->start_at) }}</bdi>، ساعت {{ $appointment->start_at->setTimezone('Asia/Tehran')->format('H:i') }} به وقت تهران</p>
<p>وضعیت: {{ ['pending_payment'=>'رزرو موقت، در انتظار پرداخت','confirmed'=>'قطعی','expired'=>'منقضی','cancelled'=>'لغوشده','completed'=>'انجام‌شده','no_show'=>'عدم مراجعه'][$appointment->effectiveStatus()] ?? 'نیازمند بررسی' }}</p>
<p>مبلغ کامل: {{ number_format($appointment->price_irr) }} ریال. وضعیت مالی: {{ $appointment->payment_status === 'unpaid' ? 'پرداخت‌نشده' : 'نیازمند مسیر مالی' }}</p>
@if($appointment->is_override)<p class="alert alert-warning">این نوبت خارج از ظرفیت عادی ثبت شده است.</p>@endif
@if($appointment->effectiveStatus()==='pending_payment')
<p class="alert alert-info" data-hold-expires="{{ $appointment->hold_expires_at->toIso8601String() }}" data-server-now="{{ now()->toIso8601String() }}" role="status" aria-live="polite">رزرو موقت است؛ پرداخت آنلاین فعلاً در دسترس نیست.</p>
@endif
@if($appointment->active())
<a class="btn btn-outline-primary mb-3" href="{{ route('appointments.edit',$appointment->reference) }}" data-hold-action>تغییر زمان</a>
<form method="post" action="{{ route('appointments.cancel',$appointment->reference) }}" data-confirm="این نوبت لغو و ظرفیت آن آزاد می‌شود. ادامه می‌دهید؟">@csrf
<input type="hidden" name="expected_version" value="{{ $appointment->version }}">
<input type="hidden" name="idempotency_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
<label>دلیل لغو<input class="form-control" name="reason" maxlength="500" required></label>
<p class="form-text">برای نوبت پرداخت‌نشده وجهی برگشت داده نمی‌شود. اگر قبلاً پرداخت کرده‌اید، لغو نیازمند تسویه مالی و تماس با مطب است.</p>
<button class="btn btn-outline-danger mt-2" type="submit" data-hold-action>لغو نوبت</button></form>
@endif
@if($actor->userId && !$actor->staff() && $appointment->booker_user_id === null)
<form method="post" action="{{ route('appointments.claim',$appointment->reference) }}" class="mt-3">@csrf<input type="hidden" name="expected_version" value="{{ $appointment->version }}"><input type="hidden" name="idempotency_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><button class="btn btn-outline-primary">اتصال نوبت مهمان به حساب تأییدشده من</button></form>
@endif
<a class="d-block mt-3" href="{{ route('appointments.index') }}">فهرست نوبت‌ها</a>
@endsection
