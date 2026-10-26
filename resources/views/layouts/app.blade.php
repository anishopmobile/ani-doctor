<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>آنی دکتر</title>
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap.rtl.min.css') }}">
    @auth
        <link rel="stylesheet" href="{{ asset('assets/vendor/tabler.rtl.min.css') }}">
    @endauth
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    <script src="{{ asset('assets/auth.js') }}" defer></script>
    @stack('assets')
</head>
<body>
<a class="visually-hidden-focusable" href="#main">رفتن به محتوای اصلی</a>
<header class="site-header"><div class="container"><a href="{{ route('calendar') }}" class="brand">آنی دکتر</a><nav class="d-flex gap-3 flex-wrap" aria-label="دسترسی اصلی"><a href="{{ route('calendar') }}">تقویم خدمات</a><a href="{{ route('dashboard') }}">حساب من</a><a href="{{ route('appointments.index') }}">نوبت‌ها</a>@can('booking.settings')<a href="{{ route('booking.settings') }}">سیاست رزرو</a>@endcan @can('catalog.manage')<a href="{{ route('services.index') }}">خدمات</a>@endcan @can('schedules.manage')<a href="{{ route('schedule.index') }}">برنامه کاری</a>@endcan</nav></div></header>
<main id="main" class="container py-4">
    @if(app(\App\Modules\Payments\Application\LocalPaymentMode::class)->enabled())<p class="alert alert-warning" role="status">درگاه آزمایشی لوکال فعال است؛ پرداخت‌ها و اعتبار حاصل از آن ساختگی‌اند و هیچ وجهی دریافت نمی‌شود. این داده‌ها را به هاست واقعی منتقل نکنید.</p>@endif
    @can('reports.appointments')<a class="d-inline-block mb-3 me-3" href="{{ route('reports.index') }}">گزارش‌ها و خروجی</a>@endcan
    @can('discounts.manage')<a class="d-inline-block mb-3" href="{{ route('discounts.index') }}">تخفیف خدمات</a>@endcan
    @can('forms.manage')<a class="d-inline-block mb-3 me-3" href="{{ route('forms.manage') }}">مدیریت فرم‌های خصوصی</a>@endcan
    @can('notifications.manage')<a class="d-inline-block mb-3" href="{{ route('notifications.manage') }}">اعلان‌های پیامکی</a>@endcan
    @auth<form method="post" action="{{ route('logout') }}" class="mb-3">@csrf<button class="btn btn-outline-secondary">خروج از حساب</button></form>@endauth
    @if($clinicEmergencyActive)<p class="alert alert-warning" role="status">حالت اضطراری پرداخت در مطب فعال است؛ نوبت‌های این حالت بدهکار ثبت می‌شوند.</p>@endif
    <nav class="d-flex gap-3 mb-3" aria-label="پیگیری مالی"><a href="{{ route('wallet') }}">اعتبار و استرداد</a>@can('payments.review')<a href="{{ route('finance') }}">مدیریت مالی</a>@endcan</nav>
    @can('desk.view')<nav class="d-flex gap-3 flex-wrap mb-3" aria-label="عملیات مطب"><a href="{{ route('desk') }}">میز کار امروز</a>@can('desk.book')<a href="{{ route('desk.create') }}">نوبت تلفنی / فوری</a>@endcan @can('patients.manage')<a href="{{ route('patients.index') }}">بیماران</a>@endcan</nav>@endcan
    <div class="{{ request()->is('login', 'dashboard', 'patient', 'secretary', 'admin') ? 'auth-card' : 'work-card' }} card shadow-sm mx-auto"><div class="card-body p-3 p-md-4">
        @if(session('status'))<p class="alert alert-success" role="status">{{ session('status') }}</p>@endif
        @if($errors->any())
            <div class="alert alert-danger" role="alert" tabindex="-1" data-errors>
                <p class="mb-1">درخواست تکمیل نشد:</p>
                <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @yield('content')
    </div></div>
</main>
<x-confirm-dialog/>
<footer class="container text-center text-secondary pb-4">اطلاعات ورود خود را در اختیار دیگران نگذارید.</footer>
</body>
</html>
