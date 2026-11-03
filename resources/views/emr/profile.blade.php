@extends('layouts.app')
@section('content')
@php($identity = $profile?->identity ?? [])
@php($review = session('emr.duplicate_review'))
@php($review = is_array($review) && ($review['patient_id'] ?? null) === $patient?->id ? $review : null)
<h1 class="h3">{{ $patient ? 'اطلاعات اداری بیمار' : 'تشکیل پرونده بیمار' }}</h1>
<p>این فرم برای مشخصات اداری است؛ شرح حال و تشخیص را در آن ننویسید. تاریخ تولد نامعلوم را خالی بگذارید.</p>
<form method="post" action="{{ $patient ? route('emr.profile.update', $patient) : route('emr.store') }}" autocomplete="off" data-unsaved-guard>@csrf @if($patient)@method('PUT')@endif
<input type="hidden" name="expected_version" value="{{ $profile?->version ?? 0 }}">
<div class="field-grid">
<label>نام کامل<input class="form-control" name="name" required maxlength="150" value="{{ old('name', $patient?->name) }}">@error('name')<span class="text-danger">{{ $message }}</span>@enderror</label>
<label>موبایل تماس (مشترک مجاز)<input class="form-control" name="contact_mobile" dir="ltr" value="{{ old('contact_mobile', $patient?->contact_mobile) }}"></label>
<label>نوع شناسه<select class="form-select" name="identity_type">@foreach(['unknown'=>'نامشخص', 'national'=>'کد ملی ایران', 'passport'=>'گذرنامه', 'other'=>'سایر'] as $value=>$label)<option value="{{ $value }}" @selected(old('identity_type', $identity['identity_type'] ?? 'unknown') === $value)>{{ $label }}</option>@endforeach</select></label>
<label>شناسه (اختیاری)<input class="form-control" name="identity_number" maxlength="64" dir="ltr" value="{{ old('identity_number', $identity['identity_number'] ?? '') }}">@error('identity_number')<span class="text-danger">{{ $message }}</span>@enderror</label>
<label>تاریخ تولد شمسی (اختیاری)<input class="form-control" name="birth_jalali" data-persian-date value="{{ old('birth_jalali', !empty($identity['birth_date']) ? app(App\Modules\Scheduling\Application\JalaliDate::class)->format(\Carbon\CarbonImmutable::parse($identity['birth_date'], 'Asia/Tehran')) : '') }}"></label>
<label>وضعیت تأهل<select class="form-select" name="marital_status">@foreach(['unknown'=>'نامشخص', 'single'=>'مجرد', 'married'=>'متأهل', 'other'=>'سایر'] as $value=>$label)<option value="{{ $value }}" @selected(old('marital_status', $identity['marital_status'] ?? 'unknown') === $value)>{{ $label }}</option>@endforeach</select></label>
<label>وضعیت پرونده<select class="form-select" name="status"><option value="active" @selected(old('status', $profile?->status) !== 'archived')>فعال</option><option value="archived" @selected(old('status', $profile?->status) === 'archived')>بایگانی (بدون حذف سوابق)</option></select></label>
</div>
@foreach(['address'=>'آدرس', 'administrative_note'=>'توضیح اداری بدون اطلاعات بالینی'] as $key=>$label)<label class="d-block mt-3">{{ $label }}<textarea class="form-control" name="{{ $key }}" maxlength="2000">{{ old($key, $identity[$key] ?? '') }}</textarea></label>@endforeach
@if($review)
<section class="alert alert-warning" aria-label="پرونده‌های مشابه"><h2 class="h5">احتمال پرونده تکراری؛ پیش از ادامه بررسی کنید</h2>
@foreach($review['patients'] as $candidate)<p><a href="{{ route('emr.profile.edit', $candidate['id']) }}" target="_blank" rel="noopener">{{ $candidate['name'] }} — {{ $candidate['file_number'] ?: '#'.$candidate['id'] }}</a></p>@endforeach
@if($review['more'])<p>بیش از ۱۰ مورد مشابه وجود دارد؛ جستجوی دقیق‌تر انجام دهید.</p>@endif
<input type="hidden" name="duplicate_token" value="{{ $review['token'] }}">
<label><input type="checkbox" name="duplicate_ack" value="1" required> موارد بالا را بررسی کردم؛ این ثبت متعلق به شخص موردنظر است. (الزامی)</label>
<p>تغییر مشخصات، نیازمند بررسی دوباره است؛ پرونده‌ها خودکار ادغام نمی‌شوند.</p></section>
@else<p>هنگام ذخیره، شباهت نام، تماس و شناسه بررسی می‌شود؛ در صورت شباهت، قبل از ثبت نهایی هشدار خواهید دید.</p>@endif
@error('duplicate_ack')<p class="alert alert-warning">{{ $message }}</p>@enderror
<button class="btn btn-primary mt-3">ذخیره مشخصات اداری</button> <a href="{{ route('emr.index') }}">بازگشت به جستجو</a>
</form>
@endsection
