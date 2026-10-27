@extends('layouts.app')
@section('content')
<h1 class="h3">خدمات مطب</h1>
<section class="border rounded p-3 mb-4">
<h2 class="h5">افزودن خدمت</h2>
<form method="post" action="{{ route('services.store') }}">@csrf<x-service-fields/><button class="btn btn-primary mt-2">ثبت خدمت</button></form>
</section>
@forelse($services as $service)
<section class="border rounded p-3 mb-3">
    <h2 class="h5">{{ $service->name }}</h2>
    @can('website.manage') <a class="btn btn-outline-primary mb-2" href="{{ route('website.service-content.edit', $service->id) }}">ویرایش توضیحات سایت</a> @endcan
    <form method="post" action="{{ route('services.update',$service) }}">@csrf @method('PUT')<x-service-fields :service="$service"/><button class="btn btn-primary mt-2">ذخیره تغییرات</button></form>
    <form class="mt-2" method="post" action="{{ route('services.destroy',$service) }}" data-confirm="این خدمت از تقویم حذف می‌شود؛ تاریخچه تعرفه باقی می‌ماند. ادامه می‌دهید؟">@csrf @method('DELETE')<button class="btn btn-outline-danger">بایگانی خدمت</button></form>
</section>
@empty<p>هنوز خدمتی ثبت نشده است.</p>@endforelse
{{ $services->links() }}
@endsection
