<section class="ux-card" aria-labelledby="attendance-title">
<h2 id="attendance-title" class="h4">مدیریت حضور</h2>
<p>حضور و پایان خدمت، وضعیت مالی را عوض نمی‌کنند. عدم مراجعه پس از پایان بازه نوبت مجاز است؛ برای پایان خدمت ابتدا مراجعه ثبت شود.</p>
@if($attendanceActions)
<form method="post" action="{{ route('attendance.change', $appointment->reference) }}" data-confirm="وضعیت حضور با نام شما ثبت می‌شود. ادامه می‌دهید؟">@csrf
<input type="hidden" name="idempotency_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_version" value="{{ $appointment->version }}">
<label>اقدام<select class="form-select" name="action">@foreach($attendanceActions as $action)<option value="{{ $action }}">{{ ['arrived'=>'ثبت مراجعه','scheduled'=>'اصلاح مراجعه به در انتظار','completed'=>'ثبت پایان خدمت','no_show'=>'ثبت عدم مراجعه'][$action] }}</option>@endforeach</select></label>
<label>توضیح اقدام؛ اطلاعات پزشکی وارد نکنید<input class="form-control" name="reason" maxlength="500" required></label>
<button class="btn btn-primary mt-2">ثبت حضور</button></form>
@else<p>در وضعیت و زمان فعلی اقدام حضور مجاز نیست.</p>@endif
<details class="ux-disclosure mt-3"><summary>تاریخچه حضور</summary>
@forelse($attendanceEvents as $event)<p>{{ ['arrived'=>'مراجعه','scheduled'=>'اصلاح مراجعه','completed'=>'پایان خدمت','no_show'=>'عدم مراجعه'][$event->action] }} — ثبت‌کننده {{ $event->actor_id }} — <bdi>{{ $event->created_at }}</bdi> UTC — {{ $event->reason }}</p>@empty<p>رویداد حضوری ثبت نشده است.</p>@endforelse
</details><a class="btn btn-outline-primary mt-3" href="{{ route('desk') }}">بازگشت به میز کار امروز</a>
</section>
