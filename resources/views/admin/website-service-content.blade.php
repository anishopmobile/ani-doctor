@extends('layouts.app')
@section('content')
<h1 class="h3">محتوای عمومی خدمت: {{ $service->name }}</h1>
<p class="text-secondary">تغییر این محتوا هیچ اثری بر قیمت، ظرفیت یا شیوه رزرو ندارد. پیش از انتشار، متن پزشکی باید بررسی شود.</p>
@if(session('status')) <p role="status" class="alert alert-success">{{ session('status') }}</p> @endif
<form method="post" action="{{ route('website.service-content.update', $service->id) }}">
    @csrf
    @method('PUT')
    <div class="mb-3">
        <label for="summary" class="form-label">خلاصه خدمت</label>
        <textarea id="summary" name="summary" maxlength="350" rows="3" class="form-control">{{ old('summary', $content?->summary) }}</textarea>
        @error('summary') <p class="text-danger">{{ $message }}</p> @enderror
    </div>
    <div class="mb-3">
        <label for="description" class="form-label">توضیحات خدمت (متن ساده)</label>
        <textarea id="description" name="description" maxlength="10000" rows="8" class="form-control">{{ old('description', $content?->description) }}</textarea>
        @error('description') <p class="text-danger">{{ $message }}</p> @enderror
    </div>
    <label class="form-check mb-3">
        <input type="hidden" name="is_published" value="0">
        <input class="form-check-input" type="checkbox" name="is_published" value="1" @checked(old('is_published', $content?->is_published ?? false))>
        <span class="form-check-label">انتشار در سایت عمومی</span>
    </label>
    <button type="submit" class="btn btn-primary">ذخیره محتوا</button>
</form>
@endsection
