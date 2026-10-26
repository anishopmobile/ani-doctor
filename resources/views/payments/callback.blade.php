@extends('layouts.app')
@section('content')
<h1 class="h3">پیگیری پرداخت</h1>
<p role="status">پاسخ درگاه بررسی شد. برای مشاهده نتیجه مالی و وضعیت قطعی نوبت، با شماره تأییدشده وارد بخش نوبت‌ها شوید. بازگشت از درگاه به‌تنهایی به معنی موفقیت پرداخت نیست.</p>
<a class="btn btn-primary" href="{{ route('appointments.index') }}">مشاهده نوبت‌های من</a>
@endsection
