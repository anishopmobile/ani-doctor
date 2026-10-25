@extends('layouts.app')
@section('content')
<h1 class="h3">دسترسی مجاز نیست</h1>
<p>برای ادامه با حساب دارای دسترسی وارد شوید.</p>
<a href="{{ route('login') }}" class="btn btn-primary">صفحه ورود</a>
@endsection
