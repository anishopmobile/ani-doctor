@extends('layouts.app')
@section('page-heading','میز کار مطب')
@section('content')
<div class="section-heading"><div><h1 class="h3 mb-1">برنامه روز مطب</h1><p class="text-secondary mb-0">نوبت‌های <bdi>{{ $jalaliDay }}</bdi> · به وقت تهران</p></div>
<div class="action-group">@can('desk.book')<a class="btn btn-primary" href="{{ route('desk.create') }}">+ نوبت تلفنی / فوری</a>@endcan @can('patients.manage')<a class="btn btn-outline-primary" href="{{ route('patients.index') }}">بیماران</a>@endcan</div></div>
<dl class="desk-summary desk-primary-stats">@foreach(['total'=>'کل نوبت‌های روز','scheduled'=>'منتظر مراجعه','arrived'=>'حاضر در مطب','completed'=>'انجام‌شده'] as $key=>$label)<div><dt>{{ $label }}</dt><dd>{{ $summary[$key] }}</dd></div>@endforeach</dl>
<section class="ux-panel" aria-labelledby="desk-filter-title"><div class="section-heading"><h2 id="desk-filter-title" class="h5 mb-0">پیداکردن نوبت</h2><nav class="day-navigation" aria-label="تغییر روز"><a href="{{ route('desk',['date'=>$previousDay]) }}">روز قبل</a><a href="{{ route('desk') }}">امروز</a><a href="{{ route('desk',['date'=>$nextDay]) }}">روز بعد</a></nav></div>
<form method="post" action="{{ route('desk.filter') }}" class="desk-filters" data-desk-filter>@csrf
<label>روز شمسی<input data-persian-date class="form-control" name="date" value="{{ $jalaliDay }}" dir="ltr" required></label>
<label>نام، شماره تماس کامل یا پرونده<input class="form-control" name="query" maxlength="100" value="{{ $filters['query'] ?? '' }}" autocomplete="off"></label>
<label>حضور<select class="form-select" name="attendance"><option value="">همه وضعیت‌ها</option>@foreach(['scheduled'=>'در انتظار مراجعه','arrived'=>'مراجعه کرده','completed'=>'انجام‌شده','no_show'=>'عدم مراجعه'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['attendance'] ?? '')===$value)>{{ $label }}</option>@endforeach</select></label>
<label>خدمت<select class="form-select" name="service_id"><option value="">همه خدمات</option>@foreach($services as $service)<option value="{{ $service->id }}" @selected(($filters['service_id'] ?? '')==$service->id)>{{ $service->name }}</option>@endforeach</select></label>
<div class="filter-actions"><button class="btn btn-primary" type="submit">نمایش</button><button class="btn btn-outline-secondary" name="clear" value="1">پاک‌کردن فیلتر</button></div>
</form></section>
<div class="section-heading mt-4"><h2 class="h4 mb-0">فهرست نوبت‌ها</h2><span class="text-secondary">{{ $appointments->total() }} نتیجه</span></div>
<div class="table-responsive desk-table-wrap" aria-live="polite"><table class="table table-hover desk-table">
<caption class="visually-hidden">نوبت‌های روز {{ $jalaliDay }} با فیلتر فعلی</caption>
<thead><tr><th scope="col">ساعت</th><th scope="col">بیمار و خدمت</th><th scope="col">تماس / پرونده</th><th scope="col">وضعیت</th><th scope="col">مالی</th><th scope="col">عملیات</th></tr></thead><tbody>
@forelse($appointments as $appointment)
<tr>
<td data-label="ساعت"><strong><bdi>{{ $appointment->start_at->setTimezone('Asia/Tehran')->format('H:i') }}</bdi></strong></td>
<td data-label="بیمار و خدمت"><strong>{{ $appointment->patient->name }}</strong><small class="d-block text-secondary">{{ $appointment->service_title }}</small><small>{{ ['phone'=>'تلفنی','urgent'=>'فوری','secretary'=>'ثبت منشی','user'=>'حساب کاربر','guest'=>'مهمان'][$appointment->channel] ?? 'ثبت قبلی' }}</small>@if($appointment->is_override)<span class="status-pill status-warning">خارج از ظرفیت عادی</span>@endif</td>
<td data-label="تماس / پرونده"><bdi>{{ $appointment->patient->contact_mobile ?? 'ثبت نشده' }}</bdi><small class="d-block">پرونده: {{ $appointment->patient->file_number ?? 'ندارد' }}</small></td>
<td data-label="وضعیت"><span class="status-pill status-{{ $appointment->effectiveStatus() }}">{{ ['pending_payment'=>'رزرو موقت','confirmed'=>'قطعی','expired'=>'منقضی','cancelled'=>'لغوشده','completed'=>'انجام‌شده','no_show'=>'عدم مراجعه'][$appointment->effectiveStatus()] ?? 'نیازمند بررسی' }}</span><small class="d-block">{{ ['scheduled'=>'در انتظار مراجعه','arrived'=>'مراجعه کرده','completed'=>'انجام‌شده','no_show'=>'عدم مراجعه'][$appointment->attendance_status] }}</small></td>
<td data-label="مالی">{{ ['paid'=>'تسویه‌شده','unpaid'=>'پرداخت‌نشده','credited'=>'برگشته به اعتبار'][$appointment->payment_status] ?? 'نیازمند بررسی' }}<small class="d-block text-secondary">بدهی: {{ number_format($appointment->balance_due_irr) }} ریال</small></td>
<td data-label="عملیات"><a class="btn btn-outline-primary" href="{{ route('appointments.show',$appointment->reference) }}">مدیریت نوبت</a></td>
</tr>
@empty<tr><td colspan="6"><div class="ux-empty"><h3 class="h5">نوبتی برای این انتخاب پیدا نشد</h3><p>روز دیگری انتخاب کنید یا فیلترها را پاک کنید.</p><a class="btn btn-outline-primary" href="{{ route('desk') }}">نمایش امروز بدون فیلتر</a></div></td></tr>@endforelse
</tbody></table></div>
{{ $appointments->appends(['date'=>$jalaliDay])->links() }}
<details class="ux-secondary mt-3"><summary>سایر آمار این روز</summary><dl class="compact-stats">@foreach(['holds'=>'رزرو موقت معتبر','cancelled'=>'لغوشده','expired'=>'منقضی','no_show'=>'عدم مراجعه'] as $key=>$label)<div><dt>{{ $label }}</dt><dd>{{ $summary[$key] }}</dd></div>@endforeach</dl><p class="form-text">آمار روز مستقل از فیلتر فهرست است.</p></details>
@endsection
