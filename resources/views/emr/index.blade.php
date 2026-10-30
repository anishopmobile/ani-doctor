@extends('layouts.app')
@section('content')
<h1 class="h3">پرونده بیماران</h1>
<p>رزروکننده و بیمار ممکن است متفاوت باشند؛ تماس مشترک دلیل ادغام پرونده نیست.</p>
<form method="post" action="{{ route('emr.search') }}" class="ux-card mb-3">@csrf
<div class="field-grid">
<label>نام، موبایل، شماره پرونده یا شناسه دقیق<input class="form-control" name="query" maxlength="100" value="{{ $filter['query'] ?? '' }}" autocomplete="off"></label>
<label>وضعیت<select class="form-select" name="status"><option value="">همه بیماران</option value="active" @selected(($filter['status'] ?? '') === 'active')>پرونده فعال</option><option value="archived" @selected(($filter['status'] ?? '') === 'archived')>بایگانی</option></select></label>
@foreach(['from'=>'از تاریخ تشکیل (شمسی)', 'to'=>'تا تاریخ تشکیل (شمسی)'] as $key=>$label)<label>{{ $label }}<input class="form-control" name="{{ $key }}" data-persian-date value="{{ $filter[$key] ?? '' }}"></label>@endforeach
<label>مرتب‌سازی<select class="form-select" name="sort">@foreach(['newest'=>'جدیدترین بیمار', 'oldest'=>'قدیمی‌ترین بیمار', 'name'=>'نام', 'file'=>'شماره پرونده'] as $key=>$label)<option value="{{ $key }}" @selected(($filter['sort'] ?? 'newest') === $key)>{{ $label }}</option>@endforeach</select></label>
</div><button class="btn btn-primary mt-3">جستجو</button></form>
<a class="btn btn-outline-primary mb-3" href="{{ route('emr.create') }}">ایجاد پرونده پس از بررسی تکرار</a>
@forelse($patients as $patient)<article class="ux-card mb-2"><h2 class="h5"><a href="{{ route('emr.show', $patient) }}">{{ $patient->name }}</a></h2><p>شماره پرونده: <bdi>{{ $patient->file_number ?: 'هنوز تخصیص نیافته' }}</bdi> — تماس: <bdi>{{ $patient->contact_mobile ?: 'نامشخص' }}</bdi></p></article>@empty<p class="alert alert-info">بیماری با این شرایط ثبت نشده است.</p>@endforelse
{{ $patients->links() }}
@endsection
