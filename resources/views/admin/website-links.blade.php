@extends('layouts.app')
@section('content')
<h1 class="h3">مدیریت منو و فوتر عمومی</h1>
<p>لینک‌های منتشرشده در بخش انتخابی نمایش داده می‌شوند. برای حفظ امنیت، فقط مسیر داخلی یا لینک HTTPS پذیرفته می‌شود.</p>
<section class="border rounded p-3 mb-4">
    <h2 class="h5">افزودن لینک</h2>
    <form method="post" action="{{ route('website.links.store') }}">
        @csrf
        @include('admin.partials.website-link-fields', ['link' => null])
        <button class="btn btn-primary mt-3">ثبت لینک</button>
    </form>
</section>
@forelse($links as $link)
<section class="border rounded p-3 mb-3">
    <h2 class="h5">{{ $link->label }} <small class="text-secondary">{{ $link->placement === 'header' ? 'هدر' : 'فوتر' }} / {{ $link->is_published ? 'منتشرشده' : 'پیش‌نویس' }}</small></h2>
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
</section>
@empty
<p>لینکی در CMS تعریف نشده است؛ لینک‌های پیش‌فرض سایت همچنان نمایش داده می‌شوند.</p>
@endforelse
@endsection
