@extends('layouts.app')
@section('content')
<h1>صندوق امن مدارک</h1><p>پزشک همان مدیر است. هر مشاهده و تصمیم با شناسه کاربر ثبت می‌شود.</p>
@forelse($documents as $document)<section class="ux-card mb-3"><h2 class="h5">مدرک <bdi>{{ $document->reference }}</bdi></h2><p>بیمار شماره {{ $document->patient_id }}؛ وضعیت {{ ['pending'=>'در انتظار بررسی','accepted'=>'تأییدشده','rejected'=>'ردشده'][$document->state] }}</p>
<a href="{{ route('medical.download',$document->reference) }}">دریافت خصوصی مدرک</a>
@if($document->state==='pending')<form method="post" action="{{ route('medical.review',$document->reference) }}">@csrf<label>پاسخ خصوصی<textarea class="form-control" name="response" maxlength="3000" required></textarea></label><button class="btn btn-primary" name="decision" value="accepted">تأیید و ثبت در پرونده</button> <button class="btn btn-outline-danger" name="decision" value="rejected">رد مدرک</button></form>
@else<p>بررسی‌کننده: {{ $document->reviewed_by }}؛ زمان: {{ $document->reviewed_at }}</p>@endif</section>
@empty<p>مدرکی دریافت نشده است.</p>@endforelse
{{ $documents->links() }}
@endsection
