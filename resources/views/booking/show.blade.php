@extends('layouts.app')
@push('assets')<script src="{{ asset('assets/booking.js') }}" defer></script>@endpush
@section('page-heading','پیگیری نوبت')
@section('content')
<div class="section-heading"><div><h1 class="h3 mb-1">جزئیات نوبت</h1><p class="text-secondary mb-0">وضعیت نوبت و قدم بعدی را اینجا ببینید.</p></div><a class="btn btn-outline-secondary" href="{{ route('appointments.index') }}">نوبت‌های من</a></div>
<div class="appointment-overview">
<section class="ux-panel"><h2 class="h5">{{ $appointment->service_title }}</h2><p class="text-secondary">{{ $appointment->patient->name }}</p>
<span class="status-pill status-{{ $appointment->effectiveStatus() }}">{{ ['pending_payment'=>'رزرو موقت، در انتظار پرداخت','confirmed'=>'نوبت قطعی','expired'=>'منقضی','cancelled'=>'لغوشده','completed'=>'انجام‌شده','no_show'=>'عدم مراجعه'][$appointment->effectiveStatus()] ?? 'نیازمند بررسی' }}</span>
<dl class="appointment-facts"><div><dt>تاریخ شمسی</dt><dd><bdi>{{ $jalali->format($appointment->start_at) }}</bdi></dd></div><div><dt>ساعت مراجعه · تهران</dt><dd>{{ $appointment->start_at->setTimezone('Asia/Tehran')->format('H:i') }}</dd></div><div><dt>مبلغ کامل</dt><dd>{{ number_format($appointment->price_irr) }} ریال</dd></div><div><dt>وضعیت مالی</dt><dd>{{ ['unpaid'=>'پرداخت‌نشده','paid'=>'تسویه‌شده','credited'=>'برگشته به اعتبار'][$appointment->payment_status] ?? 'نیازمند بررسی' }}</dd></div><div><dt>وضعیت حضور</dt><dd>{{ ['scheduled'=>'در انتظار مراجعه','arrived'=>'مراجعه کرده','completed'=>'انجام‌شده','no_show'=>'عدم مراجعه'][$appointment->attendance_status] }}</dd></div></dl>
@if($appointment->discount_irr > 0)<p class="form-text">تعرفه پایه {{ number_format($appointment->base_price_irr) }} ریال؛ تخفیف {{ number_format($appointment->discount_irr) }} ریال.</p>@endif
@if($appointment->effectiveStatus()==='pending_payment')<p class="alert alert-info" data-hold-expires="{{ $appointment->hold_expires_at->toIso8601String() }}" data-server-now="{{ now()->toIso8601String() }}" role="status" aria-live="polite">رزرو موقت است؛ پیش از پایان مهلت، تسویه کامل را انجام دهید.</p>@endif
@if($appointment->is_override)<p class="alert alert-warning">این نوبت خارج از ظرفیت عادی ثبت شده است.</p>@endif
<div class="action-group"><a class="btn btn-outline-primary" href="{{ route('tickets.print',$appointment->reference) }}">برگه چاپ نوبت</a><a class="btn btn-outline-primary" href="{{ route('tickets.pdf',$appointment->reference) }}">PDF نوبت</a></div>
@if(!$actor->staff() || $actor->can('forms.answers.view'))<a class="d-block mt-3" href="{{ route('private-forms.index',$appointment->reference) }}">فرم‌های خصوصی این نوبت</a>@endif
</section><section class="ux-panel">@include('payments.appointment')</section>
</div>
@if($appointment->active() && $appointment->attendance_status === 'scheduled')
<details class="ux-secondary mt-3"><summary>تغییر زمان یا لغو این نوبت</summary>
<a class="btn btn-outline-primary mb-3" href="{{ route('appointments.edit',$appointment->reference) }}" data-hold-action>تغییر زمان</a>
<form method="post" action="{{ route('appointments.cancel',$appointment->reference) }}" data-confirm="این نوبت لغو و ظرفیت آن آزاد می‌شود. ادامه می‌دهید؟">@csrf
<input type="hidden" name="expected_version" value="{{ $appointment->version }}">
<input type="hidden" name="idempotency_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
<label>دلیل لغو<input class="form-control" name="reason" maxlength="500" required></label>
<p class="form-text">برای نوبت پرداخت‌نشده وجهی برگشت داده نمی‌شود. مبلغ تسویه‌شده مجاز پس از لغو به اعتبار شماره رزروکننده برمی‌گردد؛ استرداد بانکی نیازمند درخواست و بررسی است.</p>
<button class="btn btn-outline-danger mt-2" type="submit" data-hold-action>لغو نوبت</button></form>
</details>
@endif
@if($actor->userId && !$actor->staff() && $appointment->booker_user_id === null)
<form method="post" action="{{ route('appointments.claim',$appointment->reference) }}" class="mt-3">@csrf<input type="hidden" name="expected_version" value="{{ $appointment->version }}"><input type="hidden" name="idempotency_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><button class="btn btn-outline-primary">اتصال نوبت مهمان به حساب تأییدشده من</button></form>
@endif
<a class="d-block mt-3" href="{{ route('appointments.index') }}">فهرست نوبت‌ها</a>

@can('attendance.manage')@include('desk.attendance')@endcan
@endsection
