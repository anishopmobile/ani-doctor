<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title>@yield('title', 'آنی دکتر | مدیریت نوبت')</title>
<link rel="stylesheet" href="{{ asset('assets/vendor/tabler.rtl.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/hcg-select.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/pardis.css') }}">
<link rel="stylesheet" href="{{ asset('assets/app.css') }}?v=20261027-ux2">
<link rel="stylesheet" href="{{ asset('assets/controls.css') }}">
<script src="{{ asset('assets/vendor/hcg-select.js') }}" defer></script>
<script src="{{ asset('assets/auth.js') }}" defer></script>
<script type="module" src="{{ asset('assets/ui-controls.js') }}?v=20261027-ux2"></script>
@stack('assets')
</head>
<body class="app-body {{ request()->routeIs('login') ? 'is-login' : '' }}">
<a class="visually-hidden-focusable skip-link" href="#main">رفتن به محتوای اصلی</a>
<div class="app-shell page">
<aside class="app-sidebar" aria-label="نوار کناری">
<a class="app-brand" href="{{ route('dashboard') }}"><span class="brand-symbol" aria-hidden="true">+</span><span>آنی دکتر<small>سامانه نوبت‌دهی مطب</small></span></a>
@include('components.app-navigation')
<div class="sidebar-note"><span class="status-dot" aria-hidden="true"></span> یک مسیر ساده برای مدیریت مطب</div>
</aside>
<div class="app-workspace page-wrapper">
<header class="app-topbar navbar">
<a class="mobile-brand" href="{{ route('calendar') }}">آنی دکتر</a>
<div class="topbar-context"><span class="status-dot" aria-hidden="true"></span> فضای {{ auth()->user()?->hasPermission('panel.admin') ? 'مدیریت' : (auth()->user()?->hasPermission('panel.secretary') ? 'منشی' : 'کاربری') }} مطب</div>
<div class="topbar-actions"><a class="btn btn-primary" href="{{ route('calendar') }}">رزرو نوبت</a>
@auth<form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-outline-secondary" type="submit">خروج</button></form>@else<a class="btn btn-outline-primary" href="{{ route('login') }}">ورود</a>@endauth
</div>
</header>
<details class="mobile-navigation"><summary>منوی سامانه <span aria-hidden="true">☰</span></summary>@include('components.app-navigation')</details>
<main id="main" class="app-main container-xl">
<div class="page-intro"><div><p class="page-pretitle">آنی دکتر / فضای {{ auth()->user()?->hasPermission('desk.view') ? 'کاری مطب' : 'شخصی شما' }}</p><p class="page-title">@yield('page-heading', 'نوبت‌دهی، ساده و منظم')</p></div><span class="workspace-badge">زمان مطب: تهران</span></div>
@if(app(\App\Modules\Payments\Application\LocalPaymentMode::class)->enabled())<p class="alert alert-warning" role="status">درگاه آزمایشی لوکال فعال است؛ پرداخت‌ها و اعتبار حاصل از آن ساختگی‌اند و هیچ وجهی دریافت نمی‌شود. این داده‌ها را به هاست واقعی منتقل نکنید.</p>@endif
@if($clinicEmergencyActive)<p class="alert alert-warning" role="status">حالت اضطراری پرداخت در مطب فعال است؛ نوبت‌های این حالت بدهکار ثبت می‌شوند.</p>@endif
<div class="content-surface card {{ request()->routeIs('login') ? 'auth-card' : 'work-card' }}"><div class="card-body">
@if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert" tabindex="-1" data-errors><p class="mb-1">درخواست تکمیل نشد:</p><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
</div></div>
<footer class="app-footer">آنی دکتر · اطلاعات ورود و پرونده‌های خصوصی را در اختیار دیگران نگذارید.</footer>
</main>
</div>
</div>
<x-confirm-dialog/>
</body>
</html>
