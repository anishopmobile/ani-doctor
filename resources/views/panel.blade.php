@extends('layouts.app')
@section('content')
<h1 class="h3">{{ ['patient' => 'حساب بیمار', 'secretary' => 'پنل منشی', 'admin' => 'پنل مدیر'][$panel] }}</h1>
<p>دسترسی این بخش تأیید شده است. امکانات عملیاتی مطابق فازهای بعدی اضافه می‌شوند.</p>
<a href="{{ route('dashboard') }}" class="btn btn-outline-primary">بازگشت</a>
@endsection
