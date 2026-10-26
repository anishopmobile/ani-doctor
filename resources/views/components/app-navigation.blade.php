<nav class="app-navigation" aria-label="منوی اصلی">
<p class="nav-heading">دسترسی سریع</p>
<a class="app-nav-link {{ request()->routeIs('dashboard','panel.*','desk') ? 'is-active' : '' }}" href="{{ route('dashboard') }}">◫ <span>داشبورد</span></a>
<a class="app-nav-link {{ request()->routeIs('calendar*') ? 'is-active' : '' }}" href="{{ route('calendar') }}">▦ <span>تقویم و رزرو نوبت</span></a>
<a class="app-nav-link {{ request()->routeIs('appointments.*') ? 'is-active' : '' }}" href="{{ route('appointments.index') }}">◷ <span>نوبت‌ها و پیگیری</span></a>
<a class="app-nav-link {{ request()->routeIs('wallet*') ? 'is-active' : '' }}" href="{{ route('wallet') }}">◇ <span>اعتبار و استرداد</span></a>
@can('desk.view')<p class="nav-heading">عملیات مطب</p>
<a class="app-nav-link" href="{{ route('desk') }}">میز کار امروز</a>
@can('desk.book')<a class="app-nav-link {{ request()->routeIs('desk.create') ? 'is-active' : '' }}" href="{{ route('desk.create') }}">نوبت تلفنی / فوری</a>@endcan
@can('patients.manage')<a class="app-nav-link {{ request()->routeIs('patients.*') ? 'is-active' : '' }}" href="{{ route('patients.index') }}">بیماران و سابقه</a>@endcan
@endcan
@canany(['catalog.manage','schedules.manage','booking.settings','reports.appointments','payments.review','forms.manage','discounts.manage','notifications.manage'])<p class="nav-heading">مدیریت و گزارش</p>@endcanany
@can('catalog.manage')<a class="app-nav-link {{ request()->routeIs('services.*') ? 'is-active' : '' }}" href="{{ route('services.index') }}">خدمات و تعرفه‌ها</a>@endcan
@can('schedules.manage')<a class="app-nav-link {{ request()->routeIs('schedule.*') ? 'is-active' : '' }}" href="{{ route('schedule.index') }}">شیفت‌ها و تعطیلات</a>@endcan
@can('booking.settings')<a class="app-nav-link {{ request()->routeIs('booking.settings*') ? 'is-active' : '' }}" href="{{ route('booking.settings') }}">سیاست رزرو</a>@endcan
@can('reports.appointments')<a class="app-nav-link {{ request()->routeIs('reports.*') ? 'is-active' : '' }}" href="{{ route('reports.index') }}">گزارش‌ها و خروجی</a>@endcan
@can('payments.review')<a class="app-nav-link {{ request()->routeIs('finance*') ? 'is-active' : '' }}" href="{{ route('finance') }}">مدیریت مالی</a>@endcan
@can('discounts.manage')<a class="app-nav-link {{ request()->routeIs('discounts.*') ? 'is-active' : '' }}" href="{{ route('discounts.index') }}">تخفیف خدمات</a>@endcan
@can('forms.manage')<a class="app-nav-link {{ request()->routeIs('forms.*') ? 'is-active' : '' }}" href="{{ route('forms.manage') }}">مدیریت فرم‌های خصوصی</a>@endcan
@can('notifications.manage')<a class="app-nav-link {{ request()->routeIs('notifications.*') ? 'is-active' : '' }}" href="{{ route('notifications.manage') }}">اعلان‌های پیامکی</a>@endcan
</nav>
