@extends('layouts.app')
@section('content')
<h1 class="h4">فرم‌های خصوصی نوبت</h1><p>پاسخ‌ها در صفحه عمومی نوبت یا پیامک نمایش داده نمی‌شوند. فقط اطلاعات ضروری را ثبت کنید.</p>
@forelse($forms as $form)<p><a href="{{ route('private-forms.show', [$appointment->reference, $form->id]) }}">{{ $form->title }}</a></p>@empty<p>فرم فعالی برای این نوبت وجود ندارد.</p>@endforelse
<a href="{{ route('appointments.show', $appointment->reference) }}">بازگشت به نوبت</a>
@endsection
