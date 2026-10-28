@extends('layouts.app')
@section('content')
<h1 class="h4">فرم‌های خصوصی</h1><p>فقط اطلاعات ضروری نوبت را جمع‌آوری کنید؛ این بخش پرونده درمانی نیست. دسترسی پاسخ‌ها از مدیریت فرم مستقل است.</p>
<a class="btn btn-primary" href="{{ route('forms.new') }}">فرم جدید</a>
@forelse($forms as $form)<div class="ux-card mt-3"><a class="btn btn-outline-primary" href="{{ route('forms.edit', $form->id) }}">{{ $form->title }}</a> — نسخه {{ $form->published_version }} — {{ $form->is_active ? 'فعال' : 'غیرفعال' }}</div>@empty<p class="mt-3">فرمی منتشر نشده است.</p>@endforelse
{{ $forms->links() }}
@endsection
