@php
    $contextTitle = 'فضای کار مطب';
    foreach(config('ui.pages', []) as $pattern => $label) {
        if(request()->routeIs($pattern)) { $contextTitle = $label; break; }
    }
@endphp
<nav class="ui-breadcrumb" aria-label="مسیر صفحه"><a href="{{ route('dashboard') }}">فضای کار</a><span aria-hidden="true">/</span><span aria-current="page">{{ $contextTitle }}</span></nav>
