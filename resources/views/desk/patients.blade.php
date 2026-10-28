@extends('layouts.app')
@section('content')
<h1 class="h3">بیماران مطب</h1>
<a href="{{ route('desk') }}">میز کار امروز</a>
<form method="post" action="{{ route('patients.search') }}" class="mt-3 mb-3">@csrf
<label>نام، شماره تماس کامل یا شماره پرونده<input class="form-control" name="query" maxlength="100" value="{{ $query }}" autocomplete="off"></label><button class="btn btn-primary mt-2">جستجو</button><button class="btn btn-outline-secondary mt-2" name="clear" value="1">پاک‌کردن</button></form>
@forelse($patients as $patient)<article class="ux-card"><h2 class="h5">{{ $patient->name }}</h2><p>پرونده: <bdi>{{ $patient->file_number ?? 'ندارد' }}</bdi>؛ تماس: <bdi>{{ $patient->contact_mobile ?? 'ندارد' }}</bdi></p><a class="btn btn-outline-primary" href="{{ route('patients.show', $patient) }}">مشخصات و سابقه نوبت</a> · <a class="btn btn-primary" href="{{ route('desk.create', ['patient_id'=>$patient->id]) }}">ثبت نوبت برای همین بیمار</a></article>@empty<p>بیماری پیدا نشد؛ ثبت جدید به معنی ادغام با فرد هم‌نام یا هم‌شماره نیست.</p>@endforelse
{{ $patients->links() }}
<details class="ux-disclosure mt-4"><summary>بیمار در فهرست نیست؟ افزودن بیمار جدید</summary>
<form method="post" action="{{ route('patients.store') }}">@csrf
@include('desk.patient-fields', ['patient'=>null])
<button class="btn btn-primary mt-2">افزودن بیمار بدون ساخت حساب</button></form>
</details>
@endsection
