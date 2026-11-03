@extends('layouts.app')
@section('content')
<h1 class="h3">تاریخچه فعالیت‌های پرونده</h1>
<p>{{ $patient->name }} — متن بالینی در رویدادها ثبت نمی‌شود. حذف یا ویرایش رویداد از پنل مجاز نیست.</p>
@foreach($events as $event)<article class="ux-card mb-2"><p><bdi>{{ $event->action }}</bdi> — کاربر #{{ $event->actor_id }} — {{ app(App\Modules\Scheduling\Application\JalaliDate::class)->format(\Carbon\CarbonImmutable::parse($event->created_at)) }}</p><p>نتیجه: {{ json_decode($event->after_state, true)['outcome'] ?? '' }}؛ رکورد #{{ json_decode($event->after_state, true)['record_id'] ?? '' }}</p></article>@endforeach
{{ $events->links() }}<a href="{{ route('emr.show', $patient) }}">بازگشت به پرونده</a>
@endsection
