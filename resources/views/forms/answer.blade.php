@extends('layouts.app')
@push('assets')<script src="{{ asset('assets/forms.js') }}" defer></script>@endpush
@section('content')
<h1 class="h4">فرم خصوصی نوبت</h1><p>{{ $form->title }} — نسخه {{ $version->number }}</p>
<p class="alert alert-info">این فرم برای اطلاعات ضروری نوبت است؛ اطلاعات درمانی اضافی ننویسید. پاسخ‌ها رمزگذاری و دسترسی به آن‌ها ثبت می‌شود. رزروکننده مسئول داشتن اجازه بیمار برای ثبت و مشاهده این اطلاعات است.</p>
<form method="post" action="{{ route('private-forms.store', [$appointment->reference, $form->id]) }}" autocomplete="off" data-private-form data-confirm="با اجازه بیمار، پاسخ خصوصی ثبت شود؟">
@csrf<input type="hidden" name="version_id" value="{{ $version->id }}"><input type="hidden" name="expected_submission_id" value="{{ $latestId }}"><input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">
@foreach($fields as $field)
<div class="mb-3" data-answer-field data-key="{{ $field['key'] }}" @isset($field['condition']) data-source="{{ $field['condition']['field'] }}" data-equals="{{ is_bool($field['condition']['equals']) ? (int)$field['condition']['equals'] : $field['condition']['equals'] }}" @endisset>
<label class="form-label" for="answer-{{ $field['key'] }}">{{ $field['label'] }} {{ $field['required'] ? '(اجباری)' : '' }}</label>
@if(in_array($field['type'], ['select','multiselect']))
<select class="form-select" id="answer-{{ $field['key'] }}" name="answers[{{ $field['key'] }}]{{ $field['type']==='multiselect' ? '[]' : '' }}" @if($field['type']==='multiselect') multiple @endif @disabled(!$canWrite) data-answer @if($field['required']) data-required @endif>
@if($field['type']==='select')<option value="">انتخاب کنید</option>@endif
@foreach($field['options'] as $option)<option @selected(in_array($option, (array)($answers[$field['key']] ?? []), true))>{{ $option }}</option>@endforeach</select>
@elseif($field['type']==='checkbox')
<select class="form-select" id="answer-{{ $field['key'] }}" name="answers[{{ $field['key'] }}]" data-answer @disabled(!$canWrite) @if($field['required']) data-required @endif><option value="">انتخاب کنید</option><option value="1" @selected(($answers[$field['key']] ?? null) === true)>بله</option><option value="0" @selected(($answers[$field['key']] ?? null) === false)>خیر</option></select>
@elseif($field['type']==='date')
<input class="form-control" id="answer-{{ $field['key'] }}" name="answers[{{ $field['key'] }}]" value="{{ isset($answers[$field['key']]) ? app(App\Modules\Scheduling\Application\JalaliDate::class)->format(Carbon\CarbonImmutable::parse($answers[$field['key']], 'Asia/Tehran')) : '' }}" data-persian-date type="text" inputmode="numeric" placeholder="انتخاب تاریخ شمسی" pattern="[1۱١][3۳٣4۴٤][0-9۰-۹٠-٩]{2}-[0-9۰-۹٠-٩]{2}-[0-9۰-۹٠-٩]{2}" maxlength="10" aria-describedby="date-help-{{ $field['key'] }}" data-answer @if($field['required']) data-required @endif @disabled(!$canWrite)>
<small id="date-help-{{ $field['key'] }}">تاریخ شمسی؛ سال-ماه-روز، مانند ۱۴۰۵-۰۸-۰۳</small>
@else
<input class="form-control" id="answer-{{ $field['key'] }}" name="answers[{{ $field['key'] }}]" value="{{ $answers[$field['key']] ?? '' }}" type="{{ ['number'=>'number','date'=>'date','text'=>'text'][$field['type']] }}" @if($field['type']==='number') step="any" min="{{ $field['min'] ?? -1000000 }}" max="{{ $field['max'] ?? 1000000 }}" @else maxlength="2000" @endif data-answer @if($field['required']) data-required @endif @disabled(!$canWrite)>
@endif
@error($field['key'])<p class="text-danger" role="alert">{{ $message }}</p>@enderror
</div>@endforeach
@if($canWrite && $appointment->active() && $appointment->attendance_status === 'scheduled')<label class="d-block"><input type="checkbox" name="consent" value="1" required> با اطلاع از نگهداری خصوصی و داشتن اجازه بیمار، پاسخ را ثبت می‌کنم.</label><button class="btn btn-primary mt-3">ثبت پاسخ خصوصی</button>@else<p>این صفحه فقط برای مشاهده است؛ وضعیت نوبت یا مجوز اجازه ثبت نمی‌دهد.</p>@endif
</form>
<noscript><p>برای نمایش شرطی، JavaScript را فعال کنید؛ پاسخ فیلدهای نامرتبط در سرور کنار گذاشته می‌شود.</p></noscript>
@if($errors->any())<p>برای محرمانگی، ورودی ناموفق در نشست ذخیره نشده است؛ پاسخ را دوباره وارد کنید.</p>@endif
<details class="mt-4"><summary>تاریخچه پاسخ‌ها (۲۰ نسخه آخر)</summary>@foreach($history as $entry)<div class="border rounded p-3 mt-2"><p>{{ $entry->created_at }} UTC</p><dl>@foreach($fields as $field)@if(array_key_exists($field['key'], $entry->answers))<dt>{{ $field['label'] }}</dt><dd>{{ is_array($entry->answers[$field['key']]) ? implode('، ', $entry->answers[$field['key']]) : (is_bool($entry->answers[$field['key']]) ? ($entry->answers[$field['key']] ? 'بله' : 'خیر') : $entry->answers[$field['key']]) }}</dd>@endif @endforeach</dl></div>@endforeach</details>
@endsection
