@extends('layouts.app')
@push('assets')
<link rel="stylesheet" href="{{ asset('assets/page-builder.css') }}">
<script src="{{ asset('assets/page-builder.js') }}" defer></script>
@endpush
@section('content')
<x-website-workflow/>
<h1 class="h3">طراحی صفحه: {{ $page->title }}</h1>
<p>شناسه ثابت: <code>{{ $page->slug }}</code>. ذخیره پیش‌نویس انتشار نیست. برای ترتیب بلوک‌ها می‌توانید بکشید یا از دکمه‌های بالا/پایین استفاده کنید. تاریخچه، نسخه‌های قبلی را به پیش‌نویس برمی‌گرداند و نسخه عمومی را عوض نمی‌کند.</p>
<ol class="ux-steps" aria-label="مراحل طراحی صفحه"><li><span>۱</span> ویرایش محتوا</li><li><span>۲</span> ذخیره پیش‌نویس</li><li><span>۳</span> پیش‌نمایش و انتشار</li></ol>
<div data-page-builder data-publish-url="{{ route('website.pages.publish', $page) }}" data-unpublish-url="{{ route('website.pages.unpublish', $page) }}">
    <form data-builder-form method="post" action="{{ route('website.pages.update', $page) }}">
        @csrf @method('PUT')
        <input type="hidden" name="expected_version" value="{{ $page->version }}">
        <textarea name="blocks_json" data-builder-source hidden>{{ old('blocks_json', json_encode($page->blocks, JSON_UNESCAPED_UNICODE)) }}</textarea>
        <div class="builder-toolbar">
            <label>عنوان<input class="form-control" name="title" maxlength="180" required value="{{ old('title', $page->title) }}"></label>
            <label>توضیح SEO<textarea class="form-control" name="meta_description" maxlength="300">{{ old('meta_description', $page->meta_description) }}</textarea></label>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit">ذخیره پیش‌نویس</button>
                <button class="btn btn-success" type="button" data-page-publish>انتشار نسخه ذخیره‌شده</button>
                <button class="btn btn-outline-warning" type="button" data-page-unpublish>لغو انتشار</button>
                <a class="btn btn-outline-secondary" target="_blank" rel="noopener" href="{{ route('website.pages.preview', $page) }}">پیش‌نمایش واقعی نسخه ذخیره‌شده</a>
            </div>
            <p data-builder-status role="status" aria-live="polite">نسخه جاری {{ $page->version }}؛ {{ $page->published_version ? 'نسخه عمومی '.$page->published_version : 'بدون انتشار عمومی' }}</p>
        </div>
        <div class="builder-controls">
            <label>نوع بلوک<select class="form-select" data-new-block-type>@foreach(['hero'=>'معرفی اصلی','text'=>'متن','image'=>'تصویر','split'=>'تصویر و متن','services'=>'خدمات واقعی','articles'=>'مقالات منتشرشده','contact'=>'اطلاعات تماس','cta'=>'دعوت به اقدام','features'=>'کارت‌های ویژگی','faq'=>'پرسش و پاسخ','spacer'=>'فاصله'] as $type=>$label)<option value="{{ $type }}">{{ $label }}</option>@endforeach</select></label>
            <button class="btn btn-outline-primary" type="button" data-block-add>افزودن بلوک</button>
            <button class="btn btn-outline-secondary" type="button" data-builder-undo>واگرد</button>
            <button class="btn btn-outline-secondary" type="button" data-builder-redo>بازانجام</button>
            <label>اندازه بوم<select class="form-select" data-canvas-size><option value="desktop">دسکتاپ</option><option value="mobile">موبایل ۳۲۰</option></select></label>
        </div>
        <div class="builder-workspace"><section aria-label="ویرایش بلوک‌ها" data-builder-editor></section><section class="builder-canvas-wrap" aria-label="بوم بصری پیش‌نویس"><h2 class="h5">بوم پیش‌نویس</h2><p class="text-secondary">بوم برای چیدمان است؛ پیش‌نمایش واقعی، خروجی نهایی با قالب سایت را نشان می‌دهد.</p><div class="builder-canvas" data-builder-canvas></div></section></div>
    </form>
    <noscript><p role="alert">برای صفحه‌ساز بصری جاوااسکریپت را فعال کنید. پیش‌نویس فعلی بدون اقدام شما تغییر نمی‌کند.</p></noscript>
</div>
<section class="card card-body mt-4">
    <h2 class="h4">کتابخانه تصاویر همین صفحه</h2>
    <p>پیش از بارگذاری، پیش‌نویس را ذخیره کنید. هر بار یک تصویر تازه ایجاد می‌شود؛ تصویر منتشرشده با بارگذاری جدید عوض نمی‌شود. تصویر را در بلوک انتخاب، ذخیره و سپس منتشر کنید. حذف تصویر استفاده‌شده در پیش‌نویس یا نسخه عمومی مسدود است؛ تصاویر حذف‌شده از تاریخچه قابل بازگردانی نیستند.</p>
    <form method="post" action="{{ route('website.pages.assets.store', $page) }}" enctype="multipart/form-data" data-asset-upload>
        @csrf
        <label>JPEG، PNG یا WebP حداکثر ۲ مگابایت<input class="form-control" name="image" type="file" accept="image/jpeg,image/png,image/webp" required></label>
        <label>متن جایگزین<input class="form-control" name="alt_text" maxlength="180" required></label>
        <button class="btn btn-outline-primary mt-2">بارگذاری تصویر تازه</button>
    </form>
    <div data-builder-assets hidden>@foreach($assets as $asset)@if($asset->media)<span data-asset-id="{{ $asset->id }}" data-asset-url="{{ route('website.media.show', $asset->media) }}" data-asset-alt="{{ $asset->media->alt_text }}"></span>@endif @endforeach</div>
    <div class="builder-asset-grid mt-3">@foreach($assets as $asset)@if($asset->media)<article class="border rounded p-2"><img class="builder-asset-image" src="{{ route('website.media.show', $asset->media) }}" alt="{{ $asset->media->alt_text }}"><p>تصویر {{ $asset->id }}: {{ $asset->media->alt_text }}</p><form method="post" action="{{ route('website.pages.assets.destroy', [$page, $asset->id]) }}" data-confirm="فایل تصویر حذف شود؟">@csrf @method('DELETE')<button class="btn btn-outline-danger">حذف فایل</button></form></article>@endif @endforeach</div>
</section>
<details class="card card-body mt-4"><summary>تاریخچه و بازگردانی پیش‌نویس (۵۰ نسخه اخیر)</summary>
@foreach($revisions as $revision)<form method="post" action="{{ route('website.pages.restore', [$page, $revision->id]) }}" class="d-flex flex-wrap gap-2 mt-2" data-confirm="این نسخه به پیش‌نویس برگردد؟ نسخه عمومی تغییر نمی‌کند.">@csrf<input type="hidden" name="expected_version" data-version-field value="{{ $page->version }}"><span>نسخه {{ $revision->version }} — {{ $revision->action }} — {{ $revision->created_at->timezone('Asia/Tehran')->format('Y-m-d H:i') }}</span><button class="btn btn-outline-secondary">بازگردانی به پیش‌نویس</button></form>@endforeach
</details>
<form method="post" action="{{ route('website.pages.destroy', $page) }}" class="mt-4" data-confirm="صفحه، تاریخچه و تمام فایل‌های تصاویر آن حذف شوند؟">@csrf @method('DELETE')<input type="hidden" name="expected_version" data-version-field value="{{ $page->version }}"><button class="btn btn-outline-danger">حذف کامل صفحه</button></form>
@endsection
