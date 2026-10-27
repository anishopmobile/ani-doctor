@extends('layouts.app')
@section('content')
<h1 class="h3">مقالات و آموزش‌های پزشکی</h1>
<p class="alert alert-info">محتوا باید پیش از انتشار توسط پزشک بررسی شود. وضعیت پیش‌نویس به‌صورت عمومی قابل مشاهده نیست. زمان انتشار به میلادی و بر اساس منطقه زمانی سرور ثبت می‌شود.</p>
<section class="border rounded p-3 mb-4">
    <h2 class="h5">مقاله جدید</h2>
    <form method="post" action="{{ route('website.articles.store') }}">
        @csrf
        @include('admin.partials.website-article-fields', ['article' => null])
        <button class="btn btn-primary mt-3">ثبت مقاله</button>
    </form>
</section>
@forelse($articles as $article)
<section class="border rounded p-3 mb-3">
    <h2 class="h5">{{ $article->title }} <small class="text-secondary">{{ $article->is_published ? 'انتشار فعال' : 'پیش‌نویس' }}</small></h2>
    <form method="post" action="{{ route('website.articles.update', $article) }}">
        @csrf @method('PUT')
        @include('admin.partials.website-article-fields', ['article' => $article])
        <button class="btn btn-primary mt-3">ذخیره تغییرات</button>
    </form>
    <form method="post" action="{{ route('website.articles.destroy', $article) }}" class="mt-2" data-confirm="مقاله حذف شود؟">
        @csrf @method('DELETE')
        <button class="btn btn-outline-danger">حذف مقاله</button>
    </form>
</section>
@empty
<p>هنوز مقاله‌ای ثبت نشده است.</p>
@endforelse
{{ $articles->links() }}
@endsection
