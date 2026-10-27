@extends('layouts.app')
@section('content')
<h1 class="h3">تنظیمات برند، تماس و رنگ‌بندی سایت عمومی</h1>
@if(session('status')) <p role="status" class="alert alert-success">{{ session('status') }}</p> @endif
<p>رنگ‌های اصلی وب‌سایت را بدون تغییر ظاهر تقویم رزرو و پنل مدیریت تنظیم کنید. مقادیر فقط به‌شکل کد رنگ شش‌رقمی پذیرفته می‌شوند.</p>
<form method="post" action="{{ route('website.theme.update') }}">
    @csrf
    @method('PUT')
    @foreach(['brand_name' => 'نام برند یا پزشک', 'brand_subtitle' => 'عنوان حرفه‌ای', 'contact_phone' => 'شماره تماس (ارقام لاتین)', 'contact_address' => 'نشانی مطب', 'seo_description' => 'توضیح پیش‌فرض موتور جستجو'] as $field => $label)
        <label class="d-block mb-3">{{ $label }}
            <input class="form-control" type="text" name="{{ $field }}" value="{{ old($field, $theme[$field]) }}" required maxlength="{{ $field === 'contact_address' ? 500 : ($field === 'seo_description' ? 300 : ($field === 'brand_name' ? 120 : ($field === 'contact_phone' ? 16 : 180))) }}">
        </label>
        @error($field) <p role="alert" class="text-danger">{{ $message }}</p> @enderror
    @endforeach
    @foreach(['primary_color' => 'رنگ اصلی و دکمه‌ها', 'text_color' => 'رنگ متن و تیترها', 'surface_color' => 'زمینه کارت‌ها و خدمات'] as $field => $label)
        <label class="d-block mb-3">{{ $label }}
            <input class="form-control" type="color" name="{{ $field }}" value="{{ old($field, $theme[$field]) }}" required>
        </label>
    @endforeach
    <button class="btn btn-primary">ذخیره تنظیمات سایت</button>
    <label class="form-check mt-3"><input type="hidden" name="logo_is_published" value="0"><input class="form-check-input" type="checkbox" name="logo_is_published" value="1" @checked(old('logo_is_published', $theme['logo_is_published']))> انتشار لوگوی برند در تمام صفحات عمومی</label>
</form>
@if($themeRecord)
    <h2 class="h4 mt-4">لوگوی مستقل برند</h2>
    <p>لوگو از تصاویر خدمت و مقاله مستقل است. پیش از انتشار می‌توانید تصویر را در همین پنل بررسی کنید.</p>
    @include('admin.partials.website-media-editor', ['owner' => $themeRecord, 'ownerType' => 'brand'])
@else
    <p class="mt-3">برای فعال‌شدن فرم بارگذاری لوگو، تنظیمات برند را یک بار ذخیره کنید.</p>
@endif
@endsection
