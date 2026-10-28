<nav class="app-navigation" aria-label="دسترسی بخش‌ها">
    <span class="app-nav-caption">حساب و نوبت‌ها</span>
    <a href="{{ route('dashboard') }}" @class(['app-nav-link', 'active' => request()->routeIs('dashboard', 'panel.*')]) @if(request()->routeIs('dashboard', 'panel.*')) aria-current="page" @endif><x-ui-icon name="grid"/><span>نمای کلی حساب</span></a>
    <a href="{{ route('calendar') }}" @class(['app-nav-link', 'active' => request()->routeIs('calendar')])><x-ui-icon name="calendar"/><span>رزرو نوبت جدید</span></a>
    <a href="{{ route('appointments.index') }}" @class(['app-nav-link', 'active' => request()->routeIs('appointments.*')])><x-ui-icon name="file"/><span>@can('desk.view')فهرست نوبت‌های مطب@elseنوبت‌های من@endcan</span></a>
    <a href="{{ route('wallet') }}" @class(['app-nav-link', 'active' => request()->routeIs('wallet')])><x-ui-icon name="wallet"/><span>اعتبار و استرداد</span></a>
    @can('desk.view')<span class="app-nav-caption">عملیات مطب</span><x-panel-link route="desk" icon="grid" active="desk">میز کار امروز</x-panel-link>@endcan
    @can('desk.book')<x-panel-link route="desk.create" icon="calendar" active="desk.create">نوبت تلفنی / فوری</x-panel-link>@endcan
    @can('patients.manage')<x-panel-link route="patients.index" icon="users" active="patients.*">بیماران</x-panel-link>@endcan
    @can('catalog.manage')<x-panel-link route="services.index" icon="heart" active="services.*">مدیریت خدمات</x-panel-link>@endcan
    @can('schedules.manage')<x-panel-link route="schedule.index" icon="calendar" active="schedule.*">برنامه کاری</x-panel-link>@endcan
    @can('reports.appointments')<x-panel-link route="reports.index" icon="chart" active="reports.*">گزارش‌ها و خروجی</x-panel-link>@endcan
    @can('discounts.manage')<x-panel-link route="discounts.index" icon="wallet" active="discounts.*">تخفیف خدمات</x-panel-link>@endcan
    @can('payments.review')<x-panel-link route="finance" icon="wallet" active="finance">مدیریت مالی</x-panel-link>@endcan
    @can('booking.settings')<x-panel-link route="booking.settings" icon="settings" active="booking.settings">سیاست رزرو</x-panel-link>@endcan
    @can('forms.manage')<x-panel-link route="forms.manage" icon="file" active="forms.*">فرم‌های خصوصی</x-panel-link>@endcan
    @can('notifications.manage')<x-panel-link route="notifications.manage" icon="file" active="notifications.*">اعلان‌های پیامکی</x-panel-link>@endcan
    @can('website.manage')
        <details class="app-nav-group" @if(request()->routeIs('website.*')) open @endif><summary>وب‌سایت مطب</summary>
        <x-panel-link route="website.pages.index" icon="grid" active="website.pages.*">صفحه‌ساز</x-panel-link>
        <x-panel-link route="website.sections.index" icon="grid" active="website.sections.*">مدیریت سکشن‌های سایت</x-panel-link>
        <x-panel-link route="website.links.index" icon="settings" active="website.links.*">منو و فوتر سایت</x-panel-link>
        <x-panel-link route="website.articles.manage" icon="file" active="website.articles.*">مدیریت مقالات</x-panel-link>
        <x-panel-link route="website.taxonomies.index" icon="file" active="website.taxonomies.*">دسته‌بندی و برچسب</x-panel-link>
        <x-panel-link route="website.theme.edit" icon="settings" active="website.theme.*">برند و محتوای سایت</x-panel-link></details>
    @endcan
</nav>
