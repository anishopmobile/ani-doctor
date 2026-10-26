@extends('layouts.app')
@push('assets')<script src="{{ asset('assets/booking.js') }}" defer></script>@endpush
@section('content')
@if(!$actor->staff() || $actor->can('forms.answers.view'))<p><a href="{{ route('private-forms.index', $appointment->reference) }}">فرم‌های خصوصی این نوبت</a></p>@endif
<h1 class="h3">جزئیات نوبت</h1>
<p>{{ $appointment->patient->name }} — {{ $appointment->service_title }}</p>
<p>تاریخ: <bdi dir="ltr">{{ $jalali->format($appointment->start_at) }}</bdi>، ساعت {{ $appointment->start_at->setTimezone('Asia/Tehran')->format('H:i') }} به وقت تهران</p>
<p>وضعیت: {{ ['pending_payment'=>'رزرو موقت، در انتظار پرداخت','confirmed'=>'قطعی','expired'=>'منقضی','cancelled'=>'لغوشده','completed'=>'انجام‌شده','no_show'=>'عدم مراجعه'][$appointment->effectiveStatus()] ?? 'نیازمند بررسی' }}</p>
<p>مبلغ کامل: {{ number_format($appointment->price_irr) }} ریال. وضعیت مالی: {{ ['unpaid'=>'پرداخت‌نشده','paid'=>'تسویه‌شده','credited'=>'برگشته به اعتبار'][$appointment->payment_status] ?? 'نیازمند بررسی' }}</p>
<p>حضور: {{ ['scheduled'=>'در انتظار مراجعه','arrived'=>'مراجعه کرده','completed'=>'انجام‌شده','no_show'=>'عدم مراجعه'][$appointment->attendance_status] }}؛ روش ثبت: {{ ['phone'=>'تلفنی','urgent'=>'فوری','secretary'=>'منشی','user'=>'حساب کاربر','guest'=>'مهمان'][$appointment->channel] ?? 'ثبت قبلی' }}</p>
@if($actor->staff())<p>شناسه ثبت‌کننده: {{ $appointment->created_by ?? 'مهمان' }} @if($appointment->override_reason)؛ توضیح ثبت/استثنا: {{ $appointment->override_reason }}@endif</p>@endif
@if($appointment->is_override)<p class="alert alert-warning">این نوبت خارج از ظرفیت عادی ثبت شده است.</p>@endif
@if($appointment->effectiveStatus()==='pending_payment')
<p class="alert alert-info" data-hold-expires="{{ $appointment->hold_expires_at->toIso8601String() }}" data-server-now="{{ now()->toIso8601String() }}" role="status" aria-live="polite">رزرو موقت است؛ پیش از پایان مهلت، تسویه کامل را انجام دهید.</p>
@endif
@if($appointment->active() && $appointment->attendance_status === 'scheduled')
<a class="btn btn-outline-primary mb-3" href="{{ route('appointments.edit',$appointment->reference) }}" data-hold-action>تغییر زمان</a>
<form method="post" action="{{ route('appointments.cancel',$appointment->reference) }}" data-confirm="این نوبت لغو و ظرفیت آن آزاد می‌شود. ادامه می‌دهید؟">@csrf
<input type="hidden" name="expected_version" value="{{ $appointment->version }}">
<input type="hidden" name="idempotency_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
<label>دلیل لغو<input class="form-control" name="reason" maxlength="500" required></label>
<p class="form-text">برای نوبت پرداخت‌نشده وجهی برگشت داده نمی‌شود. مبلغ تسویه‌شده مجاز پس از لغو به اعتبار شماره رزروکننده برمی‌گردد؛ استرداد بانکی نیازمند درخواست و بررسی است.</p>
<button class="btn btn-outline-danger mt-2" type="submit" data-hold-action>لغو نوبت</button></form>
@endif
@if($actor->userId && !$actor->staff() && $appointment->booker_user_id === null)
<form method="post" action="{{ route('appointments.claim',$appointment->reference) }}" class="mt-3">@csrf<input type="hidden" name="expected_version" value="{{ $appointment->version }}"><input type="hidden" name="idempotency_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><button class="btn btn-outline-primary">اتصال نوبت مهمان به حساب تأییدشده من</button></form>
@endif
<a class="d-block mt-3" href="{{ route('appointments.index') }}">فهرست نوبت‌ها</a>
@include('payments.appointment')
@can('attendance.manage')@include('desk.attendance')@endcan
@endsection
