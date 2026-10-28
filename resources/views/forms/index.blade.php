@extends('layouts.app')
@section('content')
<h1 class="h4">فرم‌های خصوصی نوبت</h1><p>پاسخ‌ها در صفحه عمومی نوبت یا پیامک نمایش داده نمی‌شوند. فقط اطلاعات ضروری را ثبت کنید.</p>
@forelse($forms as $form)<article class="ux-card"><h2 class="h5">{{ $form->title }}</h2><p>برای مشاهده یا تکمیل این فرم، دکمه زیر را بزنید.</p><a class="btn btn-primary" href="{{ route('private-forms.show', [$appointment->reference, $form->id]) }}">تکمیل یا مشاهده فرم</a></article>@empty<p>فرم فعالی برای این نوبت وجود ندارد.</p>@endforelse
<a class="btn btn-outline-primary" href="{{ route('appointments.show', $appointment->reference) }}">بازگشت به نوبت</a>
@endsection
