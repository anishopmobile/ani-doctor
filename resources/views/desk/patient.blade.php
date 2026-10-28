@extends('layouts.app')
@section('content')
<h1 class="h3">مشخصات پایه بیمار</h1>
<p>شناسه بیمار: {{ $patient->id }}. ویرایش تماس، مالکیت نوبت‌ها یا پیوند بیماران دیگر را تغییر نمی‌دهد.</p>
<section class="ux-card"><form method="post" action="{{ route('patients.update', $patient) }}">@csrf @method('PUT')
@include('desk.patient-fields')<button class="btn btn-primary mt-2">ذخیره مشخصات پایه</button></form></section>
<a class="btn btn-outline-primary mt-3" href="{{ route('desk.create', ['patient_id'=>$patient->id]) }}">نوبت تلفنی / فوری برای همین بیمار</a>
<h2 class="h4 mt-4">سابقه نوبت‌ها</h2>
@forelse($appointments as $appointment)<p><bdi>{{ $jalali->format($appointment->start_at) }} {{ $appointment->start_at->setTimezone('Asia/Tehran')->format('H:i') }}</bdi> — {{ $appointment->service_title }} — {{ ['pending_payment'=>'رزرو موقت','confirmed'=>'قطعی','expired'=>'منقضی','cancelled'=>'لغوشده','completed'=>'انجام‌شده','no_show'=>'عدم مراجعه'][$appointment->effectiveStatus()] }} — <a href="{{ route('appointments.show', $appointment->reference) }}">جزئیات مجاز نوبت</a></p>@empty<p>نوبتی ثبت نشده است.</p>@endforelse
{{ $appointments->links() }}
<a href="{{ route('patients.index') }}">فهرست بیماران</a>
@endsection
