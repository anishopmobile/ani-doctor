@extends('layouts.app')
@section('content')
@php($jalali = app(App\Modules\Scheduling\Application\JalaliDate::class))
@php($readonly = $record?->status === 'final')
@php($values = $record?->payload ?? $parent?->payload ?? [])
<h1 class="h3">{{ App\Modules\MedicalRecords\Application\ClinicalSchema::labels()[$kind] }}</h1>
<p>{{ $patient->name }} — پرونده <bdi>{{ $patient->file_number ?: $patient->id }}</bdi></p>
<p>نامشخص با منفی تأییدشده متفاوت است. این سامانه مستندکننده تصمیم پزشک است؛ نسخه ثبت‌شده اتصال به سامانه رسمی نسخه الکترونیک ندارد.</p>
@if($parent)<p class="alert alert-warning">الحاقیه نسخه #{{ $parent->id }}؛ نسخه قبلی محفوظ می‌ماند. علت اصلاح را بنویسید.</p>@endif
@if($record)<p class="alert alert-info">نسخه {{ $record->version }} — {{ $readonly ? 'نهایی / امضاشده' : 'پیش‌نویس ذخیره‌شده' }} — ثبت: {{ $jalali->format($record->created_at) }} — ثبت‌کننده #{{ $record->created_by }} @if($record->signed_at) — امضا: {{ $jalali->format($record->signed_at) }} / #{{ $record->updated_by }} @endif</p>@if($record->reason)<p>علت اصلاح: {{ $record->reason }}</p>@endif @if($record->parent_id)<p><a href="{{ route('emr.records.edit', [$patient, $record->parent_id]) }}">نسخه قبلی #{{ $record->parent_id }}</a></p>@endif @endif
<form method="post" action="{{ $record ? route('emr.records.update', [$patient, $record]) : route('emr.records.store', [$patient, $kind]) }}" autocomplete="off" data-unsaved-guard>@csrf @if($record)@method('PUT')@endif
@if($parent?->visit_id)<input type="hidden" name="visit_id" value="{{ $parent->visit_id }}">@endif
@if($parent?->episode_id)<input type="hidden" name="episode_id" value="{{ $parent->episode_id }}">@endif
<input type="hidden" name="request_key" value="{{ old('request_key', (string) Illuminate\Support\Str::uuid()) }}">
<input type="hidden" name="expected_version" value="{{ $record?->version ?? 0 }}">
@if($parent)<input type="hidden" name="parent_id" value="{{ $parent->id }}"><label class="d-block mb-3">علت اصلاح<textarea name="reason" class="form-control" required maxlength="2000">{{ old('reason') }}</textarea></label>@endif
<div class="field-grid mb-3"><label>تاریخ وقوع شمسی<input class="form-control" name="occurred_jalali" data-persian-date required value="{{ old('occurred_jalali', $jalali->format($record?->occurred_at ?? $parent?->occurred_at ?? now())) }}" @disabled($readonly)></label><label>ساعت وقوع به وقت تهران<input type="time" class="form-control" name="occurred_time" required value="{{ old('occurred_time', ($record?->occurred_at ?? $parent?->occurred_at ?? now())->timezone('Asia/Tehran')->format('H:i')) }}" @disabled($readonly)></label>
@if($kind === 'visit')<label>نوبت با حضور ثبت‌شده (اختیاری؛ خالی = بدون نوبت)<select class="form-select" name="appointment_id" @disabled($record || $parent)><option value="">بدون نوبت</option>@foreach($appointments as $appointment)<option value="{{ $appointment->id }}" @selected((string) old('appointment_id', $record?->appointment_id) === (string) $appointment->id)>#{{ $appointment->id }} — {{ $jalali->format($appointment->start_at) }}</option>@endforeach</select></label>@endif
@if($kind !== 'visit')<label>ویزیت مرتبط<select class="form-select" name="visit_id" @disabled($record || $parent)><option value="">بدون پیوند</option>@foreach($visits as $visit)<option value="{{ $visit->id }}" @selected((string) old('visit_id', $record?->visit_id ?? $parent?->visit_id) === (string) $visit->id)>#{{ $visit->id }} — {{ $jalali->format($visit->occurred_at) }}</option>@endforeach</select></label>@endif
@if($kind !== 'pregnancy')<label>دوره بارداری مرتبط<select class="form-select" name="episode_id" @disabled($record || $parent)><option value="">بدون پیوند</option>@foreach($episodes as $episode)<option value="{{ $episode->id }}" @selected((string) old('episode_id', $record?->episode_id ?? $parent?->episode_id) === (string) $episode->id)>#{{ $episode->id }} — {{ $jalali->format($episode->occurred_at) }}</option>@endforeach</select></label>@endif
</div>
@include('emr.fields')
@unless($readonly)<button class="btn btn-primary mt-3">ذخیره پیش‌نویس واقعی</button>@endunless
</form>
@if($record && !$readonly)<form method="post" action="{{ route('emr.sign', [$patient, $record]) }}" class="mt-3" data-confirm="پس از نهایی‌سازی، اصلاح فقط با الحاقیه ممکن است. ادامه می‌دهید؟">@csrf<input type="hidden" name="expected_version" value="{{ $record->version }}"><p>فقط آخرین پیش‌نویس ذخیره‌شده نهایی می‌شود؛ ابتدا تغییرات فرم بالا را ذخیره کنید.</p><button class="btn btn-warning">نهایی‌سازی و امضای نسخه ذخیره‌شده</button></form>@endif
@if($successor)<p class="alert alert-warning">این نسخه سابقه قبلی است؛ <a href="{{ route('emr.records.edit', [$patient, $successor]) }}">نسخه بعدی #{{ $successor->id }} ({{ $successor->status === 'final' ? 'نهایی' : 'پیش‌نویس الحاقیه' }})</a></p>@endif
@if($readonly && !$successor)<a class="btn btn-outline-primary mt-3" href="{{ route('emr.records.create', [$patient, $kind, 'parent'=>$record->id]) }}">ثبت الحاقیه با حفظ اصل</a>@endif
<p class="mt-3"><a href="{{ route('emr.show', $patient) }}">بازگشت به پرونده</a></p>
@endsection
