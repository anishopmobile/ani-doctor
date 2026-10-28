@extends('layouts.app')
@section('content')
<div class="ux-page-heading"><h1 class="h3">مقالات و آموزش‌های پزشکی</h1><a class="btn btn-primary" href="#article-create" data-open-record="article-create">افزودن مقاله جدید</a></div>
<x-website-workflow/>
<x-record-tools/>
<p class="alert alert-info">محتوا باید پیش از انتشار توسط پزشک بررسی شود. وضعیت پیش‌نویس به‌صورت عمومی قابل مشاهده نیست. تاریخ انتشار را با تقویم شمسی و ساعت تهران انتخاب کنید.</p>
<x-work-record id="article-create" title="افزودن مقاله جدید" :create="true" :open="$articles->isEmpty()">
<form method="post" action="{{ route('website.articles.store') }}">
        @csrf
        @include('admin.partials.website-article-fields', ['article' => null])
        <button class="btn btn-primary mt-3">ثبت مقاله</button>
    </form>
</x-work-record>
@forelse($articles as $article)
<x-work-record :id="'article-'.$article->id" :title="$article->title" :status="$article->is_published ? ($article->published_at?->isFuture() ? 'انتشار زمان‌بندی‌شده' : 'انتشار فعال') : 'پیش‌نویس'">
<form method="post" action="{{ route('website.articles.update', $article) }}">
        @csrf @method('PUT')
        @include('admin.partials.website-article-fields', ['article' => $article])
        <button class="btn btn-primary mt-3">ذخیره تغییرات</button>
    </form>
    @include('admin.partials.website-media-editor', ['ownerType' => 'article', 'owner' => $article])
    <form method="post" action="{{ route('website.articles.destroy', $article) }}" class="mt-2" data-confirm="مقاله حذف شود؟">
        @csrf @method('DELETE')
        <button class="btn btn-outline-danger">حذف مقاله</button>
    </form>
</x-work-record>
@empty
<p>هنوز مقاله‌ای ثبت نشده است.</p>
@endforelse
{{ $articles->links() }}
@endsection
