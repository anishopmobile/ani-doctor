@extends('website.layout')
@section('title', e($snapshot['title']))
@section('meta_description', e($snapshot['meta_description'] ?: $snapshot['title']))
@section('content')
@if($isPreview)<p role="status" class="builder-preview-notice">پیش‌نمایش خصوصی پیش‌نویس — این تغییرات تا انتشار برای بازدیدکننده قابل مشاهده نیستند.</p>@endif
<header class="page-heading"><h1>{{ $snapshot['title'] }}</h1></header>
@foreach($snapshot['blocks'] as $block)
    @include('website.pages.block', ['block' => $block])
@endforeach
@endsection
