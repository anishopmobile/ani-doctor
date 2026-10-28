@extends('layouts.app')
@push('assets')<script src="{{ asset('assets/booking.js') }}" defer></script>@endpush
@section('content')
<div class="ux-page-heading"><div><h1 class="h3">جزئیات نوبت</h1><p>وضعیت نوبت و اقدام بعدی را از اینجا پیگیری کنید.</p></div><a class="btn btn-outline-primary" href="{{ route('appointments.index') }}">فهرست نوبت‌ها</a></div>
@php($effectiveStatus = $appointment->effectiveStatus())
<div class="ux-next-step mb-4" role="status"><strong>{{ ['pending_payment'=>'گام بعدی: پرداخت برای قطعی‌شدن نوبت','confirmed'=>'نوبت شما قطعی است','expired'=>'مهلت این رزرو تمام شده است','cancelled'=>'این نوبت لغو شده است','completed'=>'این مراجعه انجام شده است','no_show'=>'برای این نوبت عدم مراجعه ثبت شده است'][$effectiveStatus] ?? 'وضعیت نوبت را بررسی کنید' }}</strong>
<p class="mb-0">@if($effectiveStatus==='pending_payment')از بخش پرداخت همین صفحه، کل مبلغ را پیش از پایان مهلت تسویه کنید. @elseif($effectiveStatus==='confirmed')در تاریخ و ساعت زیر به مطب مراجعه کنید. @if($appointment->balance_due_irr > 0)این نوبت هنوز بدهی دارد؛ بخش پرداخت را بررسی کنید.@else مبلغ این نوبت تسویه شده است.@endif @elseif(in_array($effectiveStatus,['expired','cancelled']))برای مراجعه، زمان جدیدی انتخاب کنید. <a href="{{ route('calendar') }}">رزرو نوبت جدید</a>@else وضعیت ثبت‌شده و سوابق این نوبت در همین صفحه در دسترس است.@endif</p></div>
<div class="ux-two-columns appointment-workspace"><section class="ux-card" aria-labelledby="appointment-details-title"><h2 id="appointment-details-title" class="h4">اطلاعات مراجعه</h2>
<p class="h5">{{ $appointment->patient->name }} — {{ $appointment->service_title }}</p>
<p>تاریخ: <bdi dir="ltr">{{ $jalali->format($appointment->start_at) }}</bdi>، ساعت {{ $appointment->start_at->setTimezone('Asia/Tehran')->format('H:i') }} به وقت تهران</p>
<p>وضعیت: {{ ['pending_payment'=>'رزرو موقت، در انتظار پرداخت','confirmed'=>'قطعی','expired'=>'منقضی','cancelled'=>'لغوشده','completed'=>'انجام‌شده','no_show'=>'عدم مراجعه'][$appointment->effectiveStatus()] ?? 'نیازمند بررسی' }}</p>
<p>مبلغ کامل: {{ number_format($appointment->price_irr) }} ریال. وضعیت مالی: {{ ['unpaid'=>'پرداخت‌نشده','paid'=>'تسویه‌شده','credited'=>'برگشته به اعتبار'][$appointment->payment_status] ?? 'نیازمند بررسی' }}</p>
@if($appointment->discount_irr > 0)<p>تعرفه پایه {{ number_format($appointment->base_price_irr) }} ریال؛ تخفیف {{ number_format($appointment->discount_irr) }} ریال. مبلغ کامل قابل تسویه، مبلغ نهایی نمایش‌داده‌شده است.</p>@endif
<p>حضور: {{ ['scheduled'=>'در انتظار مراجعه','arrived'=>'مراجعه کرده','completed'=>'انجام‌شده','no_show'=>'عدم مراجعه'][$appointment->attendance_status] }}؛ روش ثبت: {{ ['phone'=>'تلفنی','urgent'=>'فوری','secretary'=>'منشی','user'=>'حساب کاربر','guest'=>'مهمان'][$appointment->channel] ?? 'ثبت قبلی' }}</p>
@if($actor->staff())<p>شناسه ثبت‌کننده: {{ $appointment->created_by ?? 'مهمان' }} @if($appointment->override_reason)؛ توضیح ثبت/استثنا: {{ $appointment->override_reason }}@endif</p>@endif
@if($appointment->is_override)<p class="alert alert-warning">این نوبت خارج از ظرفیت عادی ثبت شده است.</p>@endif
@if($appointment->effectiveStatus()==='pending_payment')
<p class="alert alert-info" data-hold-expires="{{ $appointment->hold_expires_at->toIso8601String() }}" data-server-now="{{ now()->toIso8601String() }}" role="status" aria-live="polite">رزرو موقت است؛ پیش از پایان مهلت، تسویه کامل را انجام دهید.</p>
@endif
</section>
@include('payments.appointment')
<section class="ux-card"><h2 class="h4">برگه نوبت و فرم‌ها</h2><div class="ux-quick-links"><p><a href="{{ route('tickets.print', $appointment->reference) }}">برگه چاپ نوبت</a> · <a href="{{ route('tickets.pdf', $appointment->reference) }}">PDF نوبت</a></p>

@if(!$actor->staff() || $actor->can('forms.answers.view'))<p><a href="{{ route('private-forms.index', $appointment->reference) }}">فرم‌های خصوصی این نوبت</a></p>@endif
</div></section>
@can('attendance.manage')@include('desk.attendance')@endcan
<section class="ux-card"><h2 class="h4">تغییر یا لغو نوبت</h2>@if($appointment->active() && $appointment->attendance_status === 'scheduled')
<a class="btn btn-outline-primary mb-3" href="{{ route('appointments.edit',$appointment->reference) }}" data-hold-action>تغییر زمان</a>
<details class="ux-disclosure ux-danger mt-3"><summary>می‌خواهم این نوبت را لغو کنم</summary><form method="post" action="{{ route('appointments.cancel',$appointment->reference) }}" data-confirm="این نوبت لغو و ظرفیت آن آزاد می‌شود. ادامه می‌دهید؟">@csrf
<input type="hidden" name="expected_version" value="{{ $appointment->version }}">
<input type="hidden" name="idempotency_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
<label>دلیل لغو<input class="form-control" name="reason" maxlength="500" required></label>
<p class="form-text">برای نوبت پرداخت‌نشده وجهی برگشت داده نمی‌شود. مبلغ تسویه‌شده مجاز پس از لغو به اعتبار شماره رزروکننده برمی‌گردد؛ استرداد بانکی نیازمند درخواست و بررسی است.</p>
<button class="btn btn-outline-danger mt-2" type="submit" data-hold-action>لغو نوبت</button></form></details>
@endif
@if(!$appointment->active() || $appointment->attendance_status !== 'scheduled')<p>در وضعیت فعلی تغییر یا لغو از این بخش ممکن نیست.</p>@endif
@if($actor->userId && !$actor->staff() && $appointment->booker_user_id === null)
<form method="post" action="{{ route('appointments.claim',$appointment->reference) }}" class="mt-3">@csrf<input type="hidden" name="expected_version" value="{{ $appointment->version }}"><input type="hidden" name="idempotency_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><button class="btn btn-outline-primary">اتصال نوبت مهمان به حساب تأییدشده من</button></form>
@endif
</section></div>
@endsection
