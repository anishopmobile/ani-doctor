@extends('layouts.app')
@section('content')
<h1 class="h3">میز کار مطب</h1>
<p>نوبت‌های <bdi>{{ $jalaliDay }}</bdi> به وقت تهران؛ آمار این روز مستقل از فیلتر فهرست است.</p>
<nav class="d-flex gap-3 flex-wrap mb-3" aria-label="روز و عملیات مطب">
<a href="{{ route('desk', ['date'=>$previousDay]) }}">روز قبل</a><a href="{{ route('desk') }}">امروز</a><a href="{{ route('desk', ['date'=>$nextDay]) }}">روز بعد</a>
@can('desk.book')<a class="btn btn-primary" href="{{ route('desk.create') }}">نوبت تلفنی / فوری</a>@endcan
@can('patients.manage')<a class="btn btn-outline-primary" href="{{ route('patients.index') }}">بیماران و سابقه نوبت</a>@endcan
</nav>
<dl class="desk-summary">@foreach(['total'=>'کل نوبت‌ها','scheduled'=>'قطعی در انتظار مراجعه','arrived'=>'مراجعه کرده','completed'=>'انجام‌شده','no_show'=>'عدم مراجعه','holds'=>'رزرو موقت معتبر','cancelled'=>'لغوشده','expired'=>'منقضی'] as $key=>$label)<div class="border rounded p-2"><dt>{{ $label }}</dt><dd class="mb-0">{{ $summary[$key] }}</dd></div>@endforeach</dl>
<form method="post" action="{{ route('desk.filter') }}" class="field-grid border rounded p-3 mb-4">@csrf
<label>روز شمسی<input class="form-control" name="date" value="{{ $jalaliDay }}" placeholder="1405-08-03" dir="ltr" required></label>
<label>نام، شماره تماس کامل یا پرونده<input class="form-control" name="query" maxlength="100" value="{{ $filters['query'] ?? '' }}" autocomplete="off"></label>
<label>حضور<select class="form-select" name="attendance"><option value="">همه وضعیت‌ها</option>@foreach(['scheduled'=>'در انتظار مراجعه','arrived'=>'مراجعه کرده','completed'=>'انجام‌شده','no_show'=>'عدم مراجعه'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['attendance'] ?? '')===$value)>{{ $label }}</option>@endforeach</select></label>
<label>خدمت<select class="form-select" name="service_id"><option value="">همه خدمات</option>@foreach($services as $service)<option value="{{ $service->id }}" @selected(($filters['service_id'] ?? '')==$service->id)>{{ $service->name }}</option>@endforeach</select></label>
<div class="d-flex gap-2 align-items-end"><button class="btn btn-primary">نمایش</button><button class="btn btn-outline-secondary" name="clear" value="1">پاک‌کردن فیلتر</button></div>
</form>
<div aria-live="polite">
@forelse($appointments as $appointment)
<article class="border rounded p-3 mb-3">
<h2 class="h5"><bdi>{{ $appointment->start_at->setTimezone('Asia/Tehran')->format('H:i') }}</bdi> — {{ $appointment->patient->name }} — {{ $appointment->service_title }}</h2>
<p>تماس بیمار: <bdi>{{ $appointment->patient->contact_mobile ?? 'ثبت نشده' }}</bdi>؛ پرونده: <bdi>{{ $appointment->patient->file_number ?? 'ندارد' }}</bdi></p>
<p>{{ ['pending_payment'=>'رزرو موقت','confirmed'=>'قطعی','expired'=>'منقضی','cancelled'=>'لغوشده','completed'=>'انجام‌شده','no_show'=>'عدم مراجعه'][$appointment->effectiveStatus()] ?? 'نیازمند بررسی' }}؛ حضور: {{ ['scheduled'=>'در انتظار مراجعه','arrived'=>'مراجعه کرده','completed'=>'انجام‌شده','no_show'=>'عدم مراجعه'][$appointment->attendance_status] }}؛ {{ ['phone'=>'تلفنی','urgent'=>'فوری','secretary'=>'ثبت منشی','user'=>'حساب کاربر','guest'=>'مهمان'][$appointment->channel] ?? 'ثبت قبلی' }}</p>
<p>مالی: {{ ['paid'=>'تسویه‌شده','unpaid'=>'پرداخت‌نشده','credited'=>'برگشته به اعتبار'][$appointment->payment_status] ?? 'نیازمند بررسی' }}؛ بدهی: {{ number_format($appointment->balance_due_irr) }} ریال.</p>
@if($appointment->is_override)<p class="text-danger">خارج از ظرفیت عادی؛ ثبت‌کننده: {{ $appointment->created_by }}</p>@endif
<a class="btn btn-outline-primary" href="{{ route('appointments.show', $appointment->reference) }}">حضور، دریافت وجه و مدیریت</a>
</article>
@empty<p class="alert alert-info">نوبتی با این روز و فیلتر پیدا نشد. می‌توانید فیلتر را پاک کنید یا روز دیگری ببینید.</p>@endforelse
</div>
{{ $appointments->appends(['date'=>$jalaliDay])->links() }}
@endsection
