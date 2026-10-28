@extends('layouts.app')
@section('content')
<div class="ux-page-heading"><h1 class="h3">مدیریت منو و فوتر عمومی</h1><a class="btn btn-primary" href="#link-create" data-open-record="link-create">افزودن پیوند جدید</a></div>
<x-website-workflow/>
<x-record-tools/>
<p>لینک‌های منتشرشده در بخش انتخابی نمایش داده می‌شوند. برای حفظ امنیت، فقط مسیر داخلی یا لینک HTTPS پذیرفته می‌شود.</p>
<x-work-record id="link-create" title="افزودن پیوند جدید" :create="true" :open="$links->isEmpty()">
<form method="post" action="{{ route('website.links.store') }}">
        @csrf
        @include('admin.partials.website-link-fields', ['link' => null])
        <button class="btn btn-primary mt-3">ثبت لینک</button>
    </form>
</x-work-record>
@forelse($links as $link)
<x-work-record :id="'link-'.$link->id" :title="$link->label" :status="$link->is_published ? 'منتشرشده' : 'پیش‌نویس'">
<form method="post" action="{{ route('website.links.update', $link) }}">
        @csrf
        @method('PUT')
        @include('admin.partials.website-link-fields', ['link' => $link])
        <button class="btn btn-primary mt-3">ذخیره تغییرات</button>
    </form>
    <form method="post" action="{{ route('website.links.destroy', $link) }}" class="mt-2" data-confirm="این لینک حذف شود؟">
        @csrf
        @method('DELETE')
        <button class="btn btn-outline-danger">حذف</button>
    </form>
</x-work-record>
@empty
<p>لینکی در CMS تعریف نشده است؛ لینک‌های پیش‌فرض سایت همچنان نمایش داده می‌شوند.</p>
@endforelse
@endsection
