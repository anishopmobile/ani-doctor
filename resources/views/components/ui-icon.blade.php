@props(['name' => 'grid'])
<svg {{ $attributes->class(['ui-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
@switch($name)
@case('phone')<path d="m7 3 3 5-2 2a15 15 0 0 0 6 6l2-2 5 3v3c-10 3-22-9-19-19h5Z"/>@break
@case('mail')<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m3 7 9 6 9-6"/>@break
@case('chevron-left')<path d="m15 5-7 7 7 7"/>@break
@case('chevron-up')<path d="m5 15 7-7 7 7"/>@break
@case('link')<path d="m10 13 4-4m-6 7-1 1a4 4 0 0 1-6-6l5-5a4 4 0 0 1 6 0m0 2 1-1a4 4 0 0 1 6 6l-5 5a4 4 0 0 1-6 0"/>@break
@case('play')<circle cx="12" cy="12" r="9"/><path d="m10 8 6 4-6 4V8Z"/>@break
@case('instagram')<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17 7h.01"/>@break
@case('telegram')<path d="m3 11 18-7-4 17-6-5-4 3 1-6 9-6-7 8"/>@break
@case('linkedin')<path d="M5 10v10m0-15h.01M10 20V10h4v2c3-4 7-2 7 2v6m-7-6v6"/>@break
@case('search')<circle cx="10.5" cy="10.5" r="7.5"/><path d="m16 16 5 5"/>@break
@case('clock')<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>@break
@case('pin')<path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2"/>@break
@case('calendar')<rect x="3" y="5" width="18" height="16" rx="4"/><path d="M7 3v4m10-4v4M3 11h18m-13 5h2m4 0h2"/>@break
@case('heart')<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>@break
@case('users')<circle cx="9" cy="7" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3m2-17a3 3 0 0 1 0 6m1 4a5 5 0 0 1 3 5v2"/>@break
@case('shield')<path d="m12 3 9 4v5c0 5-9 10-9 10S3 17 3 12V7l9-4Z"/><path d="m8 12 3 3 5-6"/>@break
@case('chart')<path d="M4 3v17h17M8 16v-4m5 4V7m5 9v-6"/>@break
@case('wallet')<rect x="3" y="5" width="18" height="15" rx="3"/><path d="M3 8V6a3 3 0 0 1 3-3h12m3 8h-6v5h6m-3-2.5h.01"/>@break
@case('settings')<path d="M4 6h16M4 12h16M4 18h16"/><circle cx="8" cy="6" r="2"/><circle cx="16" cy="12" r="2"/><circle cx="10" cy="18" r="2"/>@break
@case('file')<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Zm0 0v6h6M8 13h8m-8 4h5"/>@break
@case('arrow')<path d="M20 12H4m6-6-6 6 6 6"/>@break
@case('spark')<path d="m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5L12 3Z"/>@break
@default<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>
@endswitch
</svg>
