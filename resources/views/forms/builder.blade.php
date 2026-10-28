@extends('layouts.app')
@push('assets')<script src="{{ asset('assets/forms.js') }}" defer></script>@endpush
@section('content')
<h1 class="h4">{{ $definition ? 'انتشار نسخه جدید فرم' : 'فرم جدید' }}</h1>
<p>پاسخ‌های قبلی با ساختار قبلی حفظ می‌شوند. اطلاعات درمانی غیرضروری و فایل پزشکی جمع‌آوری نکنید. تاریخ در فرم به‌صورت شمسی دریافت می‌شود.</p>
<section class="ux-card"><h2 class="h5">تعریف فرم و فیلدهای موردنیاز</h2><form method="post" action="{{ $definition ? route('forms.update', $definition->id) : route('forms.store') }}" data-form-builder data-confirm="نسخه تازه منتشر شود؟ پاسخ‌های قبلی حفظ می‌شوند.">
@csrf<input type="hidden" name="expected_version" value="{{ $definition?->published_version ?? 0 }}">
<div class="row g-3 mb-3"><div class="col-md-6"><label class="form-label">عنوان<input class="form-control" name="title" value="{{ $definition?->title }}" required maxlength="150"></label></div>
<div class="col-md-6"><label class="form-label">خدمت مرتبط<select class="form-select" name="service_id"><option value="">بدون خدمت اختصاصی</option>@foreach($services as $service)<option value="{{ $service->id }}" @selected($definition?->service_id === $service->id)>{{ $service->name }}</option>@endforeach</select></label></div>
<div class="col-md-6"><label class="form-label">یا انتساب به صفحه<select class="form-select" name="page_key"><option value="">بدون انتساب صفحه</option><option value="appointment" @selected($definition?->page_key === 'appointment')>صفحه خصوصی نوبت (همه خدمات)</option></select></label></div>
<div class="col-md-6"><label class="form-label">وضعیت<select class="form-select" name="is_active"><option value="1">فعال</option><option value="0" @selected($definition && !$definition->is_active)>غیرفعال</option></select></label></div></div>
<p>فقط یکی از خدمت یا صفحه را انتخاب کنید. شرط نمایش فقط به انتخاب یا بله/خیر قبلی وابسته است.</p>
<div data-fields>@foreach($fields as $index=>$field)@include('forms.field-builder')@endforeach</div>
<template data-field-template>@include('forms.field-builder', ['index'=>'NEW', 'field'=>[]])</template>
<button class="btn btn-outline-primary mb-3" type="button" data-add-field>افزودن فیلد</button><p role="status" data-builder-status></p>
<label class="form-label d-block">دلیل انتشار<textarea class="form-control" name="reason" maxlength="500" required></textarea></label>
<button class="btn btn-primary mt-3">انتشار نسخه جدید</button></form></section>
@endsection
