@extends('layouts.app')
@section('content')
<h1 class="h3 mb-3">ورود با شماره موبایل</h1>
@if($localOtpPreview)
<div class="alert alert-warning" role="status">حالت آزمایشی لوکال فعال است؛ پیامک ارسال نمی‌شود. فقط از داده ساختگی استفاده کنید.</div>
@if(session('local_otp_code'))
<p class="alert alert-info" role="status">کد آزمایشی این درخواست: <strong dir="ltr">{{ session('local_otp_code') }}</strong> — اعتبار دو دقیقه؛ پس از بازخوانی صفحه دوباره نمایش داده نمی‌شود.</p>
@endif
@else
<p class="text-secondary">کد تأیید برای شماره شما پیامک می‌شود.</p>
@endif
<details class="ux-disclosure" @if(!session('otp') || $errors->has('mobile')) open @endif><summary>۱. شماره موبایل و دریافت کد</summary><form method="post" action="{{ route('otp.request') }}" data-submit-message="در حال ارسال کد…">
    @csrf
    <div class="mb-3">
        <label class="form-label" for="mobile">شماره موبایل <span aria-hidden="true">*</span></label>
        <input class="form-control" id="mobile" name="mobile" inputmode="tel" autocomplete="tel-national" dir="ltr" maxlength="11" required aria-describedby="mobile-help mobile-error">
        <div id="mobile-help" class="form-text">شماره ۱۱ رقمی با ۰۹</div>
        <div id="mobile-error" class="text-danger">@error('mobile'){{ $message }}@enderror</div>
    </div>
    <fieldset class="mb-3">
        <legend class="form-label">روش ادامه</legend>
        <div class="form-check"><input class="form-check-input" type="radio" id="login-purpose" name="purpose" value="login" checked><label class="form-check-label" for="login-purpose">ورود یا ساخت حساب بیمار</label></div>
        <div class="form-check"><input class="form-check-input" type="radio" id="guest-purpose" name="purpose" value="guest"><label class="form-check-label" for="guest-purpose">تأیید شماره مهمان، بدون ساخت حساب</label></div>
    </fieldset>
    <button class="btn btn-primary w-100" type="submit">ارسال کد / ارسال مجدد</button>
    <p class="form-text">بین درخواست‌های ارسال کد، دست‌کم یک دقیقه فاصله لازم است.</p>
    <p class="mb-0" role="status" aria-live="polite" data-form-status></p>
</form>
</details>
@if(session('otp'))
<h2 class="h5 mt-4">۲. کد پیامک‌شده را وارد کنید</h2>
<form method="post" action="{{ route('otp.verify') }}" data-submit-message="در حال بررسی کد…">
    @csrf
    <label class="form-label" for="code">کد تأیید شش رقمی</label>
    <input class="form-control mb-2" id="code" name="code" autofocus inputmode="numeric" autocomplete="one-time-code" dir="ltr" maxlength="6" required aria-describedby="code-help code-error">
    <p id="code-help" class="form-text">اعتبار کد دو دقیقه است. پس از پایان مهلت، کد جدید درخواست کنید.</p>
    <p id="code-error" class="text-danger">@error('code'){{ $message }}@enderror</p>
    <button class="btn btn-primary w-100" type="submit">تأیید شماره و ادامه</button>
    <p class="mb-0" role="status" aria-live="polite" data-form-status></p>
</form>
@endif
@endsection
