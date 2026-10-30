@extends('layouts.app')
@section('content')
<h1 class="h3">پیام‌های تماس سایت</h1><x-website-workflow/>
<p class="alert alert-info">پیام‌های عمومی برای هماهنگی تماس هستند و نوبت یا پرونده پزشکی ایجاد نمی‌کنند. پیام‌های غیرضروری را پس از رسیدگی حذف کنید.</p>
@forelse($messages as $message)<article class="card mb-3"><div class="card-body"><h2 class="h5">{{ $message->sender_name }}</h2><p><span class="badge bg-{{ $message->read_at ? 'secondary' : 'primary' }}">{{ $message->read_at ? 'خوانده‌شده' : 'جدید' }}</span> <bdi dir="ltr">{{ $message->phone }}</bdi></p><p class="preserve-lines">{{ $message->message }}</p><div class="d-flex flex-wrap gap-2">@unless($message->read_at)<form method="post" action="{{ route('website.contact-messages.update',$message) }}">@csrf @method('PUT')<button class="btn btn-outline-primary">علامت‌گذاری خوانده‌شده</button></form>@endunless<form method="post" action="{{ route('website.contact-messages.destroy',$message) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger">حذف پیام</button></form></div></div></article>@empty<p class="alert alert-secondary">پیامی دریافت نشده است.</p>@endforelse
{{ $messages->links() }}
@endsection
