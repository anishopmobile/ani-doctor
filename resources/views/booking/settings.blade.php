@extends('layouts.app')
@section('content')
<h1 class="h3">سیاست رزرو</h1>
<section class="ux-card"><p>این تنظیمات روی رزرو اثر می‌گذارند. پیش از ذخیره، مقدارها را بررسی کنید؛ برای تغییر متن و ظاهر سایت از مدیریت سایت استفاده کنید.</p><form class="field-grid" method="post" action="{{ route('booking.settings.update') }}">@csrf @method('PUT')
@foreach(['hold_minutes'=>'مهلت رزرو موقت (دقیقه)','cutoff_hours'=>'محدودیت تغییر و لغو (ساعت پیش از نوبت)','max_active_patient'=>'سقف نوبت فعال هر بیمار','max_active_booker'=>'سقف نوبت فعال هر شماره رزروکننده'] as $field=>$label)
<label class="d-block mb-3">{{ $label }}<input class="form-control" type="number" min="{{ $field==='cutoff_hours' ? 0 : 1 }}" name="{{ $field }}" value="{{ $settings->$field }}" required></label>
@endforeach
<p>مهلت جدید فقط برای رزروهای تازه اعمال می‌شود. تغییرات با نام ثبت‌کننده نگهداری می‌شوند.</p><button class="btn btn-primary">ذخیره</button></form></section>
@endsection
