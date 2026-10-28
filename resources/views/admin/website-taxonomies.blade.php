@extends('layouts.app')
@section('content')
<x-website-workflow/>
<h1 class="h3">دسته‌بندی‌ها و برچسب‌های مقالات</h1>
<p>برای جلوگیری از تغییر خودکار محتوای مقالات، حذف دسته‌بندی یا برچسب فقط پیوند آن را از مقالات برمی‌دارد.</p>
<x-work-record id="taxonomy-create" title="افزودن دسته‌بندی یا برچسب" :create="true" :open="$taxonomies->isEmpty()"><form method="post" action="{{ route('website.taxonomies.store') }}">
    @csrf
    <div class="field-grid">
        <label>نوع
            <select name="type" class="form-select" required><option value="category">دسته‌بندی</option><option value="tag">برچسب</option></select>
        </label>
        <label>عنوان
            <input name="name" class="form-control" maxlength="120" required value="{{ App\Support\UiInput::scalar('name', null, 'taxonomy-create') }}">
        </label>
        <label>شناسه انگلیسی
            <input name="slug" class="form-control" maxlength="120" dir="ltr" required value="{{ App\Support\UiInput::scalar('slug', null, 'taxonomy-create') }}">
        </label>
    </div>
    <button class="btn btn-primary mt-3">ثبت</button>
</form></x-work-record>
@forelse($taxonomies as $taxonomy)
    <div class="ux-card ux-taxonomy-row d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div><strong>{{ $taxonomy->name }}</strong><span class="text-secondary"> — {{ $taxonomy->type === 'tag' ? 'برچسب' : 'دسته‌بندی' }} / {{ $taxonomy->slug }}</span></div>
        <form method="post" action="{{ route('website.taxonomies.destroy', $taxonomy) }}" data-confirm="این دسته‌بندی یا برچسب حذف شود؟">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger">حذف</button>
        </form>
    </div>
@empty
    <p>هنوز دسته‌بندی یا برچسبی ثبت نشده است.</p>
@endforelse
@endsection
