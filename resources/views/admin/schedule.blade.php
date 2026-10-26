@extends('layouts.app')
@section('content')
<h1 class="h3">برنامه کاری مطب</h1>
<p>زمان‌ها به وقت تهران و تاریخ‌های ورودی شمسی هستند. برنامه یک پزشک برای همه خدمات مشترک است.</p>
@can('schedules.settings')
<form class="border rounded p-3 mb-4" method="post" action="{{ route('schedule.horizon') }}">@csrf @method('PUT')<label>افق رزرو (روز، با احتساب امروز)<input class="form-control" type="number" min="1" max="365" name="horizon_days" value="{{ $horizon }}" required></label><button class="btn btn-primary mt-2">ذخیره افق</button></form>
@endcan
<h2 class="h4">برنامه هفتگی و تعطیلی تکرارشونده</h2>
@foreach([6=>'شنبه',7=>'یکشنبه',1=>'دوشنبه',2=>'سه‌شنبه',3=>'چهارشنبه',4=>'پنجشنبه',5=>'جمعه'] as $weekday=>$title)
<details class="border rounded p-3 mb-2"><summary>{{ $title }} — {{ isset($weekly[$weekday]) && !$weekly[$weekday]->is_closed ? 'برنامه فعال' : 'تعطیل / بدون شیفت' }}</summary>
<form class="mt-3" method="post" action="{{ route('schedule.weekly') }}">@csrf<input type="hidden" name="weekday" value="{{ $weekday }}"><input type="hidden" name="is_closed" value="0"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_closed" value="1" @checked($weekly[$weekday]->is_closed ?? false)> تعطیل در این روز هفته</label>
<x-shift-fields :shifts="isset($weekly[$weekday]) ? $weekly[$weekday]->shifts->toArray() : []"/>
<label class="form-check"><input class="form-check-input" type="checkbox" name="confirm_affected" value="1"> پیامد تغییر بر نوبت‌های قطعی اعلام‌شده را تأیید می‌کنم؛ هیچ نوبتی حذف نشود.</label>
<button class="btn btn-primary">ذخیره {{ $title }}</button></form></details>
@endforeach
<h2 class="h4 mt-4">تعطیلی یا بازگشایی استثنایی</h2>
<p>استثنا بر برنامه هفتگی مقدم است. بازه‌های استثنا نباید هم‌پوشانی داشته باشند. برای اصلاح، استثنای قبلی را حذف و دوباره ثبت کنید.</p>
<form class="border rounded p-3 mb-3" method="post" action="{{ route('schedule.exception') }}">@csrf
<div class="field-grid"><label>از تاریخ شمسی<input data-persian-date class="form-control" name="start_jalali" value="{{ old('start_jalali',$todayJalali) }}" placeholder="1405-08-01" dir="ltr" required></label><label>تا تاریخ شمسی (شامل این روز)<input data-persian-date class="form-control" name="end_jalali" value="{{ old('end_jalali',$todayJalali) }}" dir="ltr" required></label></div>
<label class="d-block my-2">نوع استثنا<select class="form-select" name="kind"><option value="closed">تعطیل</option><option value="override">بازگشایی یا ساعات جایگزین</option></select></label>
<label class="d-block">دلیل داخلی<input class="form-control" name="reason" value="{{ old('reason') }}" maxlength="500" required></label>
<x-shift-fields/>
<label class="form-check"><input class="form-check-input" type="checkbox" name="confirm_affected" value="1"> اثر اعلام‌شده بر نوبت‌های قطعی را تأیید می‌کنم؛ هیچ نوبتی حذف نشود.</label>
<button class="btn btn-primary">ثبت استثنا</button></form>
@forelse($exceptions as $exception)
<section class="border rounded p-3 mb-2"><h3 class="h6">{{ $jalali->format(\Carbon\CarbonImmutable::parse($exception->start_date,'Asia/Tehran')) }} تا {{ $jalali->format(\Carbon\CarbonImmutable::parse($exception->end_date,'Asia/Tehran')) }} — {{ $exception->kind === 'closed' ? 'تعطیل' : 'ساعات جایگزین' }}</h3><p>{{ $exception->reason }}</p>
<form method="post" action="{{ route('schedule.destroy',$exception) }}" data-confirm="برنامه هفتگی دوباره اعمال می‌شود. حذف استثنا را تأیید می‌کنید؟">@csrf @method('DELETE')<label class="form-check"><input class="form-check-input" type="checkbox" name="confirm_affected" value="1"> پیامد نوبت‌های قطعی اعلام‌شده را تأیید می‌کنم.</label><button class="btn btn-outline-danger">حذف استثنا</button></form></section>
@empty<p>استثنایی ثبت نشده است.</p>@endforelse
{{ $exceptions->links() }}
@endsection
