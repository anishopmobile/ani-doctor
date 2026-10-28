@extends('layouts.app')
@section('content')
<div class="ux-page-heading"><div><h1 class="h3">برنامه کاری مطب</h1><p>برنامه معمول هفته را تنظیم کنید؛ تعطیلی یا تغییر یک تاریخ را در بخش جدا ثبت کنید.</p></div><a class="btn btn-outline-primary" href="{{ route('desk') }}">بازگشت به میز کار</a></div>
<nav class="ux-task-links" aria-label="بخش‌های برنامه کاری"><a href="#weekly-plan">برنامه هر هفته</a><a href="#exception-create" data-open-record="exception-create">تعطیلی یا ساعات ویژه</a></nav>
<p>زمان‌ها به وقت تهران و تاریخ‌های ورودی شمسی هستند. برنامه یک پزشک برای همه خدمات مشترک است.</p>
@can('schedules.settings')
<form class="border rounded p-3 mb-4" method="post" action="{{ route('schedule.horizon') }}">@csrf @method('PUT')<label>افق رزرو (روز، با احتساب امروز)<input class="form-control" type="number" min="1" max="365" name="horizon_days" value="{{ $horizon }}" required></label><button class="btn btn-primary mt-2">ذخیره افق</button></form>
@endcan
<h2 id="weekly-plan" class="h4">۱. برنامه معمول هفته</h2><div class="ux-week-grid">
@foreach([6=>'شنبه',7=>'یکشنبه',1=>'دوشنبه',2=>'سه‌شنبه',3=>'چهارشنبه',4=>'پنجشنبه',5=>'جمعه'] as $weekday=>$title)
<details class="ux-card ux-record" id="weekday-{{ $weekday }}" data-record-panel @if(old('_ux_context') === 'weekday-'.$weekday) open @endif><summary>{{ $title }} — {{ isset($weekly[$weekday]) && !$weekly[$weekday]->is_closed ? 'برنامه فعال' : 'تعطیل / بدون شیفت' }}</summary>
<form class="mt-3" method="post" action="{{ route('schedule.weekly') }}" data-schedule-form>@csrf<input type="hidden" name="weekday" value="{{ $weekday }}"><input type="hidden" name="is_closed" value="0"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_closed" value="1" @checked(App\Support\UiInput::scalar('is_closed', $weekly[$weekday]->is_closed ?? false, 'weekday-'.$weekday))> تعطیل در این روز هفته</label>
<div data-schedule-shifts><x-shift-fields :context="'weekday-'.$weekday" :shifts="isset($weekly[$weekday]) ? $weekly[$weekday]->shifts->toArray() : []"/></div>
<label class="form-check"><input class="form-check-input" type="checkbox" name="confirm_affected" value="1"> پیامد تغییر بر نوبت‌های قطعی اعلام‌شده را تأیید می‌کنم؛ هیچ نوبتی حذف نشود.</label>
<button class="btn btn-primary">ذخیره {{ $title }}</button></form></details>
@endforeach
</div><h2 class="h4 mt-4">۲. تغییر برنامه برای یک تاریخ</h2>
<p>استثنا بر برنامه هفتگی مقدم است. بازه‌های استثنا نباید هم‌پوشانی داشته باشند. برای اصلاح، استثنای قبلی را حذف و دوباره ثبت کنید.</p>
@php($exceptionOld = fn ($field, $fallback = null) => App\Support\UiInput::scalar($field, $fallback, 'exception-create'))
<x-work-record id="exception-create" title="ثبت تعطیلی یا ساعات ویژه" :create="true"><form method="post" action="{{ route('schedule.exception') }}" data-schedule-form>@csrf
<div class="field-grid"><label>از تاریخ شمسی<input class="form-control" data-persian-date name="start_jalali" value="{{ $exceptionOld('start_jalali',$todayJalali) }}" placeholder="1405-08-01" dir="ltr" required></label><label>تا تاریخ شمسی (شامل این روز)<input class="form-control" data-persian-date name="end_jalali" value="{{ $exceptionOld('end_jalali',$todayJalali) }}" dir="ltr" required></label></div>
<label class="d-block my-2">نوع استثنا<select class="form-select" name="kind"><option value="closed" @selected($exceptionOld('kind', 'closed')==='closed')>تعطیل</option><option value="override" @selected($exceptionOld('kind')==='override')>بازگشایی یا ساعات جایگزین</option></select></label>
<label class="d-block">دلیل داخلی<input class="form-control" name="reason" value="{{ $exceptionOld('reason') }}" maxlength="500" required></label>
<div data-schedule-shifts><x-shift-fields context="exception-create"/></div>
<label class="form-check"><input class="form-check-input" type="checkbox" name="confirm_affected" value="1"> اثر اعلام‌شده بر نوبت‌های قطعی را تأیید می‌کنم؛ هیچ نوبتی حذف نشود.</label>
<button class="btn btn-primary">ثبت تغییر این بازه</button></form></x-work-record>
@forelse($exceptions as $exception)
<section class="ux-card"><h3 class="h6">{{ $jalali->format(\Carbon\CarbonImmutable::parse($exception->start_date,'Asia/Tehran')) }} تا {{ $jalali->format(\Carbon\CarbonImmutable::parse($exception->end_date,'Asia/Tehran')) }} — {{ $exception->kind === 'closed' ? 'تعطیل' : 'ساعات جایگزین' }}</h3><p>{{ $exception->reason }}</p>
<form method="post" action="{{ route('schedule.destroy',$exception) }}" data-confirm="برنامه هفتگی دوباره اعمال می‌شود. حذف استثنا را تأیید می‌کنید؟">@csrf @method('DELETE')<label class="form-check"><input class="form-check-input" type="checkbox" name="confirm_affected" value="1"> پیامد نوبت‌های قطعی اعلام‌شده را تأیید می‌کنم.</label><button class="btn btn-outline-danger">حذف استثنا</button></form></section>
@empty<p>استثنایی ثبت نشده است.</p>@endforelse
{{ $exceptions->links() }}
@endsection
