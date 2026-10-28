@extends('layouts.app')
@section('content')
<div class="ux-page-heading"><div><h1 class="h3">خدمات مطب</h1><p>برای تغییر قیمت، مدت یا وضعیت، خدمت موردنظر را باز کنید.</p></div><a class="btn btn-primary" href="#service-create" data-open-record="service-create">افزودن خدمت جدید</a></div>
@can('website.manage')<x-website-workflow/>@endcan
<x-record-tools/>
<x-work-record id="service-create" title="افزودن خدمت جدید" :create="true" :open="$services->isEmpty()">
<form method="post" action="{{ route('services.store') }}">@csrf<x-service-fields/><button class="btn btn-primary mt-2">ثبت خدمت</button></form>
</x-work-record>
@forelse($services as $service)
<x-work-record :id="'service-'.$service->id" :title="$service->name" :description="number_format($service->price_irr).' ریال · '.$service->duration_minutes.' دقیقه'" :status="$service->is_active ? ($service->is_online ? 'فعال · رزرو اینترنتی' : 'فعال · فقط مطب') : 'غیرفعال'">
    @can('website.manage') <a class="btn btn-outline-primary mb-2" href="{{ route('website.service-content.edit', $service->id) }}">ویرایش توضیحات سایت</a> @endcan
    <form method="post" action="{{ route('services.update',$service) }}">@csrf @method('PUT')<x-service-fields :service="$service"/><button class="btn btn-primary mt-2">ذخیره تغییرات</button></form>
    <form class="mt-2" method="post" action="{{ route('services.destroy',$service) }}" data-confirm="این خدمت از تقویم حذف می‌شود؛ تاریخچه تعرفه باقی می‌ماند. ادامه می‌دهید؟">@csrf @method('DELETE')<button class="btn btn-outline-danger">بایگانی خدمت</button></form>
</x-work-record>
@empty<p>هنوز خدمتی ثبت نشده است.</p>@endforelse
{{ $services->links() }}
@endsection
