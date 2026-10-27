@extends('layouts.app')
@section('content')
<h1 class="h3">مدیریت سکشن‌های سایت عمومی</h1>
<p>این بخش فقط محتوای نمایشی را تغییر می‌دهد و به قوانین نوبت‌دهی و پرداخت دسترسی ندارد. انتشار هر سکشن مستقل است.</p>
<section class="border rounded p-3 mb-4">
    <h2 class="h5">افزودن سکشن</h2>
    <form method="post" action="{{ route('website.sections.store') }}">
        @csrf
        @include('admin.partials.website-section-fields', ['section' => null])
        <button class="btn btn-primary mt-3">ثبت سکشن</button>
    </form>
</section>
@forelse($sections as $section)
<section class="border rounded p-3 mb-3">
    <h2 class="h5">{{ $section->title }} <small class="text-secondary">{{ $section->is_published ? 'منتشرشده' : 'پیش‌نویس' }}</small></h2>
    <form method="post" action="{{ route('website.sections.update', $section) }}">
        @csrf
        @method('PUT')
        @include('admin.partials.website-section-fields', ['section' => $section])
        <button class="btn btn-primary mt-3">ذخیره تغییرات</button>
    </form>
    <form method="post" action="{{ route('website.sections.destroy', $section) }}" class="mt-2" data-confirm="سکشن از سایت حذف شود؟">
        @csrf
        @method('DELETE')
        <button class="btn btn-outline-danger">حذف سکشن</button>
    </form>
</section>
@empty
<p>هنوز سکشنی اضافه نشده است. صفحه اصلی پیش‌فرض تا انتشار محتوای جدید فعال می‌ماند.</p>
@endforelse
@endsection
