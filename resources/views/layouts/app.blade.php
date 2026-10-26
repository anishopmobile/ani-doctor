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
</head>
<body>
<a class="visually-hidden-focusable" href="#main">رفتن به محتوای اصلی</a>
<header class="site-header"><div class="container"><a href="{{ route('dashboard') }}" class="brand">آنی دکتر</a><span>سامانه مطب</span></div></header>
<main id="main" class="container py-4">
    <div class="auth-card card shadow-sm mx-auto"><div class="card-body p-4">
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
<footer class="container text-center text-secondary pb-4">اطلاعات ورود خود را در اختیار دیگران نگذارید.</footer>
</body>
</html>
