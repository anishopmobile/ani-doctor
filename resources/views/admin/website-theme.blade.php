@extends('layouts.app')
@section('content')
<h1 class="h3">تنظیمات رنگ‌بندی سایت عمومی</h1>
<p>رنگ‌های اصلی وب‌سایت را بدون تغییر ظاهر تقویم رزرو و پنل مدیریت تنظیم کنید. مقادیر فقط به‌شکل کد رنگ شش‌رقمی پذیرفته می‌شوند.</p>
<form method="post" action="{{ route('website.theme.update') }}">
    @csrf
    @method('PUT')
    @foreach(['primary_color' => 'رنگ اصلی و دکمه‌ها', 'text_color' => 'رنگ متن و تیترها', 'surface_color' => 'زمینه کارت‌ها و خدمات'] as $field => $label)
        <label class="d-block mb-3">{{ $label }}
            <input class="form-control" type="color" name="{{ $field }}" value="{{ old($field, $theme[$field]) }}" required>
        </label>
    @endforeach
    <button class="btn btn-primary">ذخیره رنگ‌بندی</button>
</form>
@endsection
