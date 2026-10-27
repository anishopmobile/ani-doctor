@extends('layouts.app')
@section('content')
<h1>صفحه‌ساز سایت</h1>
<p>صفحه مستقل بسازید یا با شناسه <code>home</code>، صفحه اصلی را طراحی کنید. فقط نسخه‌ای که صریح منتشر کنید عمومی می‌شود؛ لغو انتشار home قالب اصلی قبلی را بازمی‌گرداند.</p>
@if(session('status'))<p role="status" class="alert alert-success">{{ session('status') }}</p>@endif
@if($errors->any())<div role="alert" class="alert alert-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="post" action="{{ route('website.pages.store') }}" class="card card-body mb-4">
    @csrf
    <h2 class="h4">صفحه تازه</h2>
    <label>عنوان صفحه<input class="form-control" name="title" maxlength="180" required value="{{ old('title') }}"></label>
    <label>شناسه لاتین ثابت (مثلاً about یا home)<input class="form-control" name="slug" dir="ltr" pattern="[a-z0-9]+(-[a-z0-9]+)*" maxlength="100" required value="{{ old('slug') }}"></label>
    <label>توضیح موتور جستجو<textarea class="form-control" name="meta_description" maxlength="300">{{ old('meta_description') }}</textarea></label>
    <button class="btn btn-primary mt-3">ایجاد پیش‌نویس و ورود به صفحه‌ساز</button>
</form>
<div class="table-responsive"><table class="table"><thead><tr><th>عنوان</th><th>نشانی</th><th>نسخه</th><th>انتشار</th><th>مدیریت</th></tr></thead><tbody>
@forelse($pages as $page)<tr><td>{{ $page->title }}</td><td><code>{{ $page->slug === 'home' ? '/' : '/pages/'.$page->slug }}</code></td><td>{{ $page->version }}</td><td>{{ $page->published_version ? 'نسخه '.$page->published_version : 'پیش‌نویس' }}</td><td><a class="btn btn-outline-primary" href="{{ route('website.pages.edit', $page) }}">طراحی صفحه</a></td></tr>@empty<tr><td colspan="5">هنوز صفحه‌ای ایجاد نشده است.</td></tr>@endforelse
</tbody></table></div>
{{ $pages->links() }}
@endsection
