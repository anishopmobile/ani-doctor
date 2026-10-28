<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $websiteTheme['brand_name'] }} | فضای کار</title>
    <link rel="stylesheet" href="{{ asset('assets/vendor/tabler.rtl.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/hcg-select.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/pardis.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/design-system.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/workspace.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/workflow.css') }}">
    @include('website.partials.theme-css')
    @include('website.partials.favicon')
    <script src="{{ asset('assets/ui.js') }}" defer></script>
    <script src="{{ asset('assets/booking-ux.js') }}" defer></script>
    <script src="{{ asset('assets/workspace-ux.js') }}" defer></script>
    <script src="{{ asset('assets/auth.js') }}" defer></script>
    <script type="module" src="{{ asset('assets/form-controls.js') }}"></script>
    @stack('assets')
</head>
<body class="panel-app page">
<a class="visually-hidden-focusable" href="#main">رفتن به محتوای اصلی</a>
<header class="navbar app-topbar"><div class="container-fluid">
    <a href="{{ route('dashboard') }}" class="navbar-brand app-brand">@if($websiteLogo)<img class="brand-logo" src="{{ route('website.media.show', $websiteLogo) }}" alt="{{ $websiteLogo->alt_text }}">@else<span class="app-brand-mark"><x-ui-icon name="heart"/></span>@endif<span>{{ $websiteTheme['brand_name'] }}<small>{{ $websiteTheme['brand_subtitle'] }}</small></span></a>
    <div class="panel-context"><x-ui-icon name="calendar"/><span>امروز</span><bdi>{{ app(App\Modules\Scheduling\Application\JalaliDate::class)->format(now()) }}</bdi></div><div class="d-flex gap-2 align-items-center header-tools"><details class="account-menu"><summary><x-ui-icon name="users"/>حساب کاربری</summary><div>@auth<a class="d-block mb-3" href="{{ route('dashboard') }}">نمای کلی حساب</a><form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-outline-secondary w-100">خروج از حساب</button></form>@else<a href="{{ route('login') }}">ورود به حساب</a>@endauth</div></details><a class="btn btn-primary" href="{{ auth()->user()?->hasPermission('desk.book') ? route('desk.create') : route('calendar') }}">رزرو نوبت</a><button type="button" class="btn btn-outline-primary app-menu-toggle" data-menu-toggle aria-controls="panel-navigation" aria-expanded="false" hidden>فهرست بخش‌ها</button></div>
</div></header>
<div class="app-frame">
    <aside class="app-sidebar" id="panel-navigation" aria-label="منوی پنل">
        <div class="app-workspace"><div class="workspace-title"><x-ui-icon name="grid"/><span>فضای کار شما</span></div><p>دسترسی سریع و امن به خدمات</p></div>
        @include('layouts.panel-navigation')
        <div class="app-sidebar-footer">@auth<form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-outline-secondary w-100">خروج از حساب</button></form>@else<a class="btn btn-outline-primary w-100" href="{{ route('login') }}">ورود به حساب</a>@endauth<p>اطلاعات ورود خود را در اختیار دیگران نگذارید.</p></div>
    </aside>
    <div class="page-wrapper app-content">
        <div class="app-welcome"><div><p class="page-pretitle"><x-ui-icon name="spark"/>{{ $websiteTheme['brand_name'] }} / فضای همراه سلامت</p><p class="app-welcome-title">@can('desk.view')یک روز منظم، یک تجربه بهتر@elseبا آرامش، برای سلامتی برنامه‌ریزی کنید@endcan</p><p class="welcome-description">@can('desk.view')از برنامه امروز تا مدیریت خدمات؛ ابزارهای مطب در دسترس شماست.@elseانتخاب زمان مراجعه، پیگیری نوبت و خدمات مطب، در یک فضای امن و ساده.@endcan</p></div><div class="welcome-badge"><x-ui-icon name="calendar"/></div></div>
        <main id="main" class="page-body">
        <x-route-context/>
    @if(app(\App\Modules\Payments\Application\LocalPaymentMode::class)->enabled())<p class="alert alert-warning" role="status">درگاه آزمایشی لوکال فعال است؛ پرداخت‌ها و اعتبار حاصل از آن ساختگی‌اند و هیچ وجهی دریافت نمی‌شود. این داده‌ها را به هاست واقعی منتقل نکنید.</p>@endif

            @if($clinicEmergencyActive)<p class="alert alert-warning" role="status">حالت اضطراری پرداخت در مطب فعال است؛ نوبت‌های این حالت بدهکار ثبت می‌شوند.</p>@endif
    <div class="{{ request()->is('login') ? 'auth-card' : 'work-card' }} card shadow-sm mx-auto"><div class="card-body p-3 p-md-4">
        @if(session('status'))<p class="alert alert-success" role="status">{{ session('status') }}</p>@endif
        @if($errors->any())
            <div class="alert alert-danger" role="alert" tabindex="-1" data-errors data-error-context="{{ is_string(old('_ux_context')) && preg_match('/\A[a-z0-9-]{1,100}\z/', old('_ux_context')) ? old('_ux_context') : '' }}">
                <p class="mb-1">درخواست تکمیل نشد:</p>
                <ul class="mb-0">@foreach($errors->getMessages() as $errorField => $messages)@foreach($messages as $error)<li data-field-error="{{ $errorField }}">{{ $error }}</li>@endforeach @endforeach</ul>
            </div>
        @endif
        @yield('content')
    </div></div>

        </main>
        <footer class="app-footer">{{ $websiteTheme['brand_name'] }} · مدیریت ساده‌تر، پیگیری آسان‌تر</footer>
    </div>
</div>
<x-confirm-dialog/>
</body>
</html>
