@extends('layouts.app')
@section('content')
<h1 class="h3">درخواست تکمیل نشد</h1>
<p role="alert">{{ $message }}</p>
<a href="{{ route('login') }}" class="btn btn-primary">بازگشت به ورود</a>
@endsection
