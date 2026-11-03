@extends('layouts.app')
@section('content')
<h1>تنظیمات کارت‌به‌کارت</h1>
<form method="post" action="{{ route('card.settings.update') }}" class="ux-card">@csrf @method('PUT')
<input type="hidden" name="expected_version" value="{{ $settings->version ?? 0 }}">
<label>وضعیت<select class="form-select" name="enabled"><option value="0">غیرفعال</option><option value="1" @selected($settings?->enabled)>فعال</option></select></label>
<label>شماره کارت<input class="form-control" name="card_number" inputmode="numeric" pattern="[0-9]{16}" maxlength="16" value="{{ $settings->card_number ?? '' }}" required></label>
<label>نام صاحب کارت<input class="form-control" name="holder" maxlength="150" value="{{ $settings->holder ?? '' }}" required></label>
<p>تغییر کارت فقط برای درخواست‌های جدید است. مهلت از ایجاد رزرو، حداکثر ۱۵ دقیقه است.</p>
<button class="btn btn-primary">ذخیره</button></form>
@endsection
