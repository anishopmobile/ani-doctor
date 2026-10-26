@extends('layouts.app')
@section('content')
<h1 class="h3">{{ $isGuest ? 'شماره مهمان تأیید شد' : 'ورود انجام شد' }}</h1>
<p>در این مرحله، دسترسی حساب و تأیید شماره آماده است. قابلیت نوبت‌دهی در فازهای بعد اضافه می‌شود.</p>
@if($isGuest)
    <p>تأیید شماره مهمان تا ده دقیقه معتبر است؛ حسابی ساخته نشده است.</p>
@else
    <nav class="d-grid gap-2" aria-label="پنل‌های مجاز">
        @can('panel.patient')<a class="btn btn-outline-primary" href="{{ route('panel.patient') }}">حساب بیمار</a>@endcan
        @can('panel.secretary')<a class="btn btn-outline-primary" href="{{ route('panel.secretary') }}">پنل منشی</a>@endcan
        @can('panel.admin')<a class="btn btn-outline-primary" href="{{ route('panel.admin') }}">پنل مدیر</a>@endcan
    </nav>
@endif
<form class="mt-3" method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-secondary w-100">خروج</button></form>
@endsection
