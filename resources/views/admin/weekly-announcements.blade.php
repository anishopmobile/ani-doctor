@extends('layouts.app')
@push('assets')<link rel="stylesheet" href="{{ asset('assets/weekly-schedule.css') }}">@endpush
@section('content')
<div class="ux-page-heading"><div><h1 class="h3">برنامه هفتگی پزشک</h1><p>روز، ساعت و محل حضور را برای بیماران اعلام کنید.</p></div><a class="btn btn-outline-primary" href="{{ route('schedule.index') }}">برنامه کاری و ظرفیت رزرو</a></div>
<p class="alert alert-info">ویرایش این صفحه فقط اطلاع‌رسانی است و شیفت‌ها، ظرفیت رزرو و نوبت‌های ثبت‌شده را تغییر نمی‌دهد.</p>
<nav class="weekly-week-nav" aria-label="انتخاب هفته"><a class="btn btn-outline-primary" href="{{ route('weekly-announcements.index', ['week' => $week['previous']]) }}">هفته قبل</a><strong><bdi>{{ $week['start'] }}</bdi> تا <bdi>{{ $week['end'] }}</bdi></strong><a class="btn btn-outline-primary" href="{{ route('weekly-announcements.index', ['week' => $week['next']]) }}">هفته بعد</a><a href="{{ route('weekly-announcements.index') }}">هفته جاری</a></nav>
<form method="get" class="weekly-week-nav"><label>تاریخ شمسی در هفته موردنظر<input class="form-control" data-persian-date name="week" value="{{ App\Support\UiInput::scalar('week', $week['start'], 'weekly-filter') }}" dir="ltr" required></label><button class="btn btn-outline-primary">نمایش هفته</button></form>
<div class="weekly-cards">
@foreach($week['days'] as $day)
    @php
        $context = 'announcement-'.$day['date'];
        $input = fn ($field, $fallback = null) => App\Support\UiInput::scalar($field, $fallback, $context);
        $mode = $input('mode', $day['manual'] ? 'manual' : 'source');
    @endphp
    <article @class(['weekly-day', 'weekly-today' => $day['today'], 'weekly-'.$day['presence']['status']])>
        <header class="weekly-day-heading"><div><h2>{{ $day['weekday'] }}</h2><time datetime="{{ $day['date'] }}">{{ $day['jalali'] }}</time></div>@if($day['today'])<span class="weekly-badge">امروز</span>@endif</header>
        <span class="weekly-badge">{{ $day['manual'] ? 'حضور دستی' : 'حضور از برنامه کاری' }}</span>
        @if($day['location'] || $day['notes'])<span class="weekly-badge">اطلاعات تکمیلی دستی</span>@endif
        <x-weekly-presence :presence="$day['presence']"/>
        @if($day['presence']['status'] === 'working' && $day['location'])<p>{{ $day['location'] }}</p>@endif
        @if($day['source_changed'])<p class="alert alert-warning" role="status">برنامه کاری تغییر کرده است. حضور دستی حفظ شده؛ اطلاعات منبع را بررسی و سپس ذخیره کنید یا به منبع بازگردید.</p>@endif
        <details id="{{ $context }}" data-record-panel @if(old('_ux_context') === $context) open @endif>
            <summary>ویرایش {{ $day['weekday'] }}</summary>
            <div class="weekly-source"><h3 class="h6">اطلاعات جاری برنامه کاری</h3><x-weekly-presence :presence="$day['source']"/></div>
            <form method="post" action="{{ route('weekly-announcements.save') }}" data-weekly-editor>
                @csrf @method('PUT')
                <input type="hidden" name="_ux_context" value="{{ $context }}">
                <input type="hidden" name="date_jalali" value="{{ $day['jalali'] }}">
                <input type="hidden" name="expected_version" value="{{ $input('expected_version', $day['version']) }}">
                <p>تاریخ: <bdi>{{ $day['jalali'] }}</bdi> ({{ $day['weekday'] }})</p>
                <label>منبع حضور<select class="form-select" name="mode"><option value="source" @selected($mode === 'source')>پیروی از برنامه کاری / بازگرداندن حضور به منبع</option><option value="manual" @selected($mode === 'manual')>حضور دستی و مستقل</option></select></label>
                <p class="text-secondary">بازگشت به منبع فقط وضعیت و ساعت حضور را بازمی‌گرداند؛ محل و توضیحات حفظ می‌شوند.</p>
                <div data-weekly-manual>
                    <label>وضعیت نمایشی<select class="form-select" name="status"><option value="working" @selected($input('status', $day['presence']['status']) === 'working')>کاری و دارای برنامه حضور</option><option value="closed" @selected($input('status', $day['presence']['status']) === 'closed')>تعطیل؛ پزشک حضور ندارد</option><option value="official_holiday" @selected($input('status', $day['presence']['status']) === 'official_holiday')>تعطیلی رسمی (تعیین دستی)</option></select></label>
                    <p>ساعت‌ها به وقت تهران هستند. بازه عبور از نیمه‌شب را در دو روز ثبت کنید. برای حذف بازه، هر سه فیلد آن را خالی کنید.</p>
                    <div data-weekly-shifts>
                    @for($index = 0; $index < 8; $index++)
                        @php($shift = $day['presence']['shifts'][$index] ?? ['start' => '', 'end' => '', 'label' => ''])
                        <details @if($index === 0 || isset($day['presence']['shifts'][$index]) || $input('shifts.'.$index.'.start')) open @endif><summary>بازه حضور {{ $index + 1 }}</summary>
                        <fieldset><legend>ساعت و عنوان بازه {{ $index + 1 }}</legend>
                            <label>عنوان بازه<input class="form-control" name="shifts[{{ $index }}][label]" value="{{ $input('shifts.'.$index.'.label', $shift['label']) }}" maxlength="80" placeholder="صبح، عصر یا عنوان دلخواه"></label>
                            <label>ساعت شروع<input class="form-control" name="shifts[{{ $index }}][start]" value="{{ $input('shifts.'.$index.'.start', $shift['start']) }}" dir="ltr" placeholder="17:00" inputmode="numeric" maxlength="5"></label>
                            <label>ساعت پایان<input class="form-control" name="shifts[{{ $index }}][end]" value="{{ $input('shifts.'.$index.'.end', $shift['end']) }}" dir="ltr" placeholder="22:00" inputmode="numeric" maxlength="5"></label>
                        </fieldset></details>
                    @endfor
                    </div>
                </div>
                <label>محل ویزیت<input class="form-control" name="location" value="{{ $input('location', $day['location']) }}" maxlength="200" placeholder="مطب، بیمارستان یا روستا"></label>
                <input type="hidden" name="outside_office" value="0"><label><input type="checkbox" name="outside_office" value="1" @checked($input('outside_office', $day['outside_office']))> ویزیت خارج از مطب</label>
                <label>توضیحات روز (اختیاری)<textarea class="form-control" name="notes" maxlength="2000" rows="3">{{ $input('notes', $day['notes']) }}</textarea></label>
                <button class="btn btn-primary">ذخیره {{ $day['weekday'] }}</button><span class="weekly-save-state" role="status" hidden>در حال ذخیره…</span>
            </form>
        </details>
    </article>
@endforeach
</div>
<section class="ux-card"><h2 class="h4">تنظیمات نمایش عمومی</h2>
<form method="post" action="{{ route('weekly-announcements.settings') }}">@csrf @method('PUT')
    <input type="hidden" name="_ux_context" value="weekly-settings">
    <input type="hidden" name="expected_settings_version" value="{{ App\Support\UiInput::scalar('expected_settings_version', $settings['settings_version'], 'weekly-settings') }}">
    <input type="hidden" name="show_weekly_schedule" value="0"><label class="form-check"><input class="form-check-input" type="checkbox" name="show_weekly_schedule" value="1" @checked(App\Support\UiInput::scalar('show_weekly_schedule', $settings['show_weekly_schedule'], 'weekly-settings'))> نمایش برنامه هفتگی در سایت</label>
    <label class="d-block my-3">توضیحات عمومی (اختیاری)<textarea class="form-control" name="weekly_schedule_notes" rows="3" maxlength="2000">{{ App\Support\UiInput::scalar('weekly_schedule_notes', $settings['weekly_schedule_notes'], 'weekly-settings') }}</textarea></label>
    <div class="weekly-editor-actions"><button class="btn btn-primary">ذخیره تنظیمات</button>@if($settings['show_weekly_schedule'])<a class="btn btn-outline-primary" href="{{ route('weekly-announcements.public') }}">مشاهده صفحه عمومی</a>@endif</div>
</form></section>
@endsection
@push('assets')<script src="{{ asset('assets/weekly-schedule.js') }}" defer></script>@endpush
