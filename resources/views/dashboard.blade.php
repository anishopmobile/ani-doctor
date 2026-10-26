@extends('layouts.app')
@section('page-heading','حساب من')
@section('content')
<div class="patient-welcome"><div><span class="eyebrow">خوش آمدید</span><h1 class="h3">{{ $isGuest ? 'شماره مهمان تأیید شد' : 'نوبت‌های شما، در یک نگاه' }}</h1><p>برای گرفتن نوبت تازه شروع کنید، یا نوبت قبلی را پیگیری کنید.</p></div><a class="btn btn-primary" href="{{ route('calendar') }}">دریافت نوبت جدید</a></div>
<nav class="patient-shortcuts" aria-label="کارهای اصلی"><a href="{{ route('calendar') }}"><span class="shortcut-icon" aria-hidden="true">▦</span><strong>گرفتن نوبت</strong><small>خدمت، روز و ساعت را انتخاب کنید</small></a><a href="{{ route('appointments.index') }}"><span class="shortcut-icon" aria-hidden="true">◷</span><strong>پیگیری نوبت‌ها</strong><small>مشاهده، پرداخت یا تغییر نوبت</small></a><a href="{{ route('wallet') }}"><span class="shortcut-icon" aria-hidden="true">◇</span><strong>اعتبار و استرداد</strong><small>مشاهده موجودی و درخواست برگشت</small></a></nav>
<div class="section-heading"><h2 class="h4 mb-0">نوبت‌ها و سابقه من</h2><a href="{{ route('appointments.index') }}">مشاهده همه نوبت‌ها</a></div>
<div class="patient-appointments">
@forelse($appointments as $appointment)<article class="appointment-tile">
<div class="appointment-date"><strong>{{ $appointment->start_at->setTimezone('Asia/Tehran')->format('H:i') }}</strong><bdi>{{ $jalali->format($appointment->start_at) }}</bdi></div>
<div><h3 class="h5 mb-1">{{ $appointment->service_title }}</h3><p class="text-secondary mb-2">{{ $appointment->patient->name }}</p><span class="status-pill status-{{ $appointment->effectiveStatus() }}">{{ ['pending_payment'=>'رزرو موقت؛ پرداخت لازم است','confirmed'=>'نوبت قطعی','expired'=>'منقضی','cancelled'=>'لغوشده','completed'=>'انجام‌شده','no_show'=>'عدم مراجعه'][$appointment->effectiveStatus()] }}</span></div>
<a class="btn {{ $appointment->effectiveStatus()==='pending_payment' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('appointments.show',$appointment->reference) }}">{{ $appointment->effectiveStatus()==='pending_payment' ? 'ادامه و پرداخت' : 'مشاهده نوبت' }}</a>
</article>@empty<div class="ux-empty"><h3 class="h5">هنوز نوبتی در این حساب ندارید</h3><p>برای شروع، خدمت و زمان مناسب خود را از تقویم انتخاب کنید.</p><a class="btn btn-primary" href="{{ route('calendar') }}">دریافت اولین نوبت</a></div>@endforelse
</div>{{ $appointments->links() }}
@if($isGuest)<p class="alert alert-info mt-3">تأیید شماره مهمان تا ده دقیقه معتبر است؛ حسابی ساخته نشده است.</p><form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-outline-secondary">خروج</button></form>@endif
@endsection
