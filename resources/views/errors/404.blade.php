@extends('layouts.app')
@section('content')
<h1 class="h3">موردی با این دسترسی یافت نشد</h1>
<p>نشانی را بررسی کنید یا با هویت مجاز وارد شوید.</p>
<a class="btn btn-primary" href="{{ route('calendar') }}">بازگشت به تقویم</a>
@endsection
