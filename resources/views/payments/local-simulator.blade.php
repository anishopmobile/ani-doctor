@extends('layouts.app')
@section('content')
<h1 class="h3">درگاه آزمایشی لوکال</h1>
<p class="alert alert-warning" role="status">این پرداخت شبیه‌سازی است؛ هیچ پولی جابه‌جا نمی‌شود. اطلاعات کارت وارد نکنید. فقط از داده ساختگی استفاده کنید.</p>
<p>مبلغ آزمایشی: <strong>{{ number_format($amountIrr) }} ریال</strong></p>
<p>یکی از نتیجه‌ها را انتخاب کنید و پس از بازگشت، وضعیت نوبت را در «نوبت‌ها» بررسی کنید. نخستین انتخاب ثبت می‌شود و قابل تغییر نیست.</p>
<form method="post" action="{{ route('payments.local.submit', $authority) }}" class="d-flex gap-3 flex-wrap">
    @csrf
    <button class="btn btn-success" type="submit" name="outcome" value="success">پرداخت موفق آزمایشی</button>
    <button class="btn btn-outline-danger" type="submit" name="outcome" value="failure">پرداخت ناموفق آزمایشی</button>
</form>
<p class="text-danger mt-2" role="alert">@error('outcome'){{ $message }}@enderror</p>
@endsection
