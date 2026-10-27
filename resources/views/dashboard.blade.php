@extends('layouts.app')
@section('content')
<div class="workspace-actions">@can('desk.view')<a class="workspace-action" href="{{ route('desk') }}"><x-ui-icon name="calendar"/><span><strong>برنامه امروز مطب</strong><small>نوبت‌ها، حضور و پیگیری مراجعه</small></span></a>@else<a class="workspace-action" href="{{ route('calendar') }}"><x-ui-icon name="calendar"/><span><strong>یک نوبت تازه</strong><small>انتخاب خدمت و زمان مراجعه</small></span></a>@endcan<a class="workspace-action" href="{{ route('appointments.index') }}"><x-ui-icon name="file"/><span><strong>پیگیری نوبت‌ها</strong><small>جزئیات و وضعیت رزروهای شما</small></span></a>@can('website.manage')<a class="workspace-action" href="{{ route('website.pages.index') }}"><x-ui-icon name="grid"/><span><strong>وب‌سایت مطب</strong><small>طراحی و مدیریت محتوای صفحات</small></span></a>@else<a class="workspace-action" href="{{ route('wallet') }}"><x-ui-icon name="wallet"/><span><strong>اعتبار و پرداخت‌ها</strong><small>دسترسی به پیگیری مالی حساب</small></span></a>@endcan</div>
<h1 class="h3">{{ $isGuest ? 'شماره مهمان تأیید شد' : 'ورود انجام شد' }}</h1>
<p>از بخش نوبت‌ها، وضعیت رزرو و پرداخت خود را پیگیری کنید.</p>
<a class="btn btn-primary mb-3" href="{{ route('calendar') }}">رزرو از تقویم</a>
<h2 class="h4">نوبت‌ها و سابقه من</h2>
@forelse($appointments as $appointment)<article class="border rounded p-3 mb-2"><h3 class="h5">{{ $appointment->patient->name }} — {{ $appointment->service_title }}</h3><p><bdi>{{ $jalali->format($appointment->start_at) }} {{ $appointment->start_at->setTimezone('Asia/Tehran')->format('H:i') }}</bdi>؛ {{ ['pending_payment'=>'رزرو موقت','confirmed'=>'قطعی','expired'=>'منقضی','cancelled'=>'لغوشده','completed'=>'انجام‌شده','no_show'=>'عدم مراجعه'][$appointment->effectiveStatus()] }}</p><a href="{{ route('appointments.show', $appointment->reference) }}">جزئیات، پرداخت و پیگیری</a></article>@empty<p>نوبتی متعلق به این حساب یا شماره تأییدشده پیدا نشد.</p>@endforelse
{{ $appointments->links() }}
@if($isGuest)
    <p>تأیید شماره مهمان تا ده دقیقه معتبر است؛ حسابی ساخته نشده است.</p>
@else
    <nav class="d-grid gap-2" aria-label="پنل‌های مجاز">
        @can('panel.patient')<a class="btn btn-outline-primary" href="{{ route('panel.patient') }}">حساب بیمار</a>@endcan
        @can('panel.secretary')<a class="btn btn-outline-primary" href="{{ route('panel.secretary') }}">پنل منشی</a>@endcan
        @can('panel.admin')<a class="btn btn-outline-primary" href="{{ route('panel.admin') }}">پنل مدیر</a>@endcan
    </nav>
@endif
@guest<form class="mt-3" method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-secondary w-100">خروج</button></form>@endguest
@endsection
