@props(['route', 'icon' => 'grid', 'active' => null])
@php($current = request()->routeIs($active ?? $route))
<a href="{{ route($route) }}" @class(['app-nav-link', 'active' => $current]) @if($current) aria-current="page" @endif><x-ui-icon :name="$icon"/><span>{{ $slot }}</span></a>
