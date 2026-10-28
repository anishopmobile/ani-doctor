@extends('layouts.app')
@section('content')
<div class="ux-page-heading"><h1 class="h3">مدیریت سکشن‌های سایت عمومی</h1><a class="btn btn-primary" href="#section-create" data-open-record="section-create">افزودن بخش نمایشی جدید</a></div>
<x-website-workflow/>
<x-record-tools/>
<p>این بخش فقط محتوای نمایشی را تغییر می‌دهد و به قوانین نوبت‌دهی و پرداخت دسترسی ندارد. انتشار هر سکشن مستقل است.</p>
<x-work-record id="section-create" title="افزودن بخش نمایشی جدید" :create="true" :open="$sections->isEmpty()">
<form method="post" action="{{ route('website.sections.store') }}">
        @csrf
        @include('admin.partials.website-section-fields', ['section' => null])
        <button class="btn btn-primary mt-3">ثبت سکشن</button>
    </form>
</x-work-record>
@forelse($sections as $section)
<x-work-record :id="'section-'.$section->id" :title="$section->title" :status="$section->is_published ? 'منتشرشده' : 'پیش‌نویس'">
<form method="post" action="{{ route('website.sections.update', $section) }}">
        @csrf
        @method('PUT')
        @include('admin.partials.website-section-fields', ['section' => $section])
        <button class="btn btn-primary mt-3">ذخیره تغییرات</button>
    </form>
    @include('admin.partials.website-media-editor', ['ownerType' => 'section', 'owner' => $section])
    <form method="post" action="{{ route('website.sections.destroy', $section) }}" class="mt-2" data-confirm="سکشن از سایت حذف شود؟">
        @csrf
        @method('DELETE')
        <button class="btn btn-outline-danger">حذف سکشن</button>
    </form>
</x-work-record>
@empty
<p>هنوز سکشنی اضافه نشده است. صفحه اصلی پیش‌فرض تا انتشار محتوای جدید فعال می‌ماند.</p>
@endforelse
@endsection
