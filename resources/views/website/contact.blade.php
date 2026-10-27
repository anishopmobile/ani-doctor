@extends('website.layout')
@section('title', 'اطلاعات تماس و مطب')
@section('content')
<nav class="ui-breadcrumb" aria-label="مسیر صفحه"><a href="{{ route('website.home') }}">خانه</a><span aria-hidden="true">/</span><span aria-current="page">تماس با مطب</span></nav>
<h1>{{ $websiteTheme['contact_title'] }}</h1>
<div class="contact-grid"><div>@include('website.partials.contact-details')</div><div class="contact-cta"><x-ui-icon name="calendar"/><h2>{{ $websiteTheme['appointment_title'] }}</h2><p>{{ $websiteTheme['appointment_text'] }}</p><a class="btn-primary-site" href="{{ route('calendar') }}">{{ $websiteTheme['appointment_button_label'] }}</a></div></div>
@endsection
