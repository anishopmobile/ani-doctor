<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
if (getenv('PANEL_SYNTHETIC_FIXTURE') !== '1' || config('database.default') !== 'sqlite'
    || !str_ends_with(config('database.connections.sqlite.database'), '-panel-ui.sqlite')
    || config('app.url') !== 'http://127.0.0.1:18558' || config('payments.zarinpal.enabled') || config('notifications.enabled')) {
    throw new RuntimeException('ISOLATED_PANEL_FIXTURE_REQUIRED');
}
$kernel->call('migrate', ['--force' => true]);
$kernel->call('db:seed', ['--class' => Database\Seeders\RoleSeeder::class, '--force' => true]);
foreach (['admin' => '09000000551', 'patient' => '09000000552', 'secretary' => '09000000554'] as $role => $mobile) {
    $user = App\Modules\Identity\Infrastructure\User::firstOrCreate(['mobile' => $mobile]);
    $user->forceFill(['mobile_verified_at' => now()])->save();
    $user->roles()->sync([App\Modules\Identity\Infrastructure\Role::where('name', $role)->sole()->id]);
}
$admin = App\Modules\Identity\Infrastructure\User::where('mobile', '09000000551')->sole();
if (!App\Modules\Website\Infrastructure\WebsitePage::where('slug', 'panel-demo')->exists()) {
    app(App\Modules\Website\Application\PageService::class)->create(['slug' => 'panel-demo', 'title' => 'صفحه ساختگی آزمون رابط'], $admin->id);
}
if (!App\Modules\Catalog\Infrastructure\Service::exists()) {
    App\Modules\Catalog\Infrastructure\Service::create(['name' => 'خدمت ساختگی آزمون رابط', 'price_irr' => 1000000, 'duration_minutes' => 30, 'buffer_minutes' => 0, 'capacity' => 1, 'is_active' => true, 'is_online' => true]);
}
if (!App\Modules\Website\Infrastructure\WebsiteTheme::find(1)) {
    app(App\Modules\Website\Application\ThemeService::class)->save(App\Modules\Website\Application\ThemeService::DEFAULTS);
}
if (app(App\Modules\Website\Application\ThemeService::class)->settings()['hero_title'] === 'عنوان ساختگی پیش‌نمایش رابط') {
    app(App\Modules\Website\Application\ThemeService::class)->save(['hero_title' => App\Modules\Website\Application\SiteContent::defaults()['hero_title']]);
}
if (app(App\Modules\Website\Application\ThemeService::class)->settings()['primary_color'] === '#087f84') {
    app(App\Modules\Website\Application\ThemeService::class)->save(App\Modules\Website\Application\ThemeService::DEFAULTS + App\Modules\Website\Application\ThemeService::EXTRA_COLORS);
}
$page = App\Modules\Website\Infrastructure\WebsitePage::where('slug', 'panel-demo')->sole();
if (!$page->published_snapshot) {
    app(App\Modules\Website\Application\PageService::class)->publication($page, $page->version, $admin->id, true);
}
App\Modules\Website\Infrastructure\WebsiteArticle::firstOrCreate(['slug' => 'ui-demo-article'], ['title' => 'مقاله ساختگی آزمون رابط', 'body' => 'فقط متن آزمون، بدون توصیه پزشکی.', 'is_published' => true, 'published_at' => now()->subDay()]);
$reference = '11111111-1111-4111-8111-111111111111';
if (!App\Modules\Booking\Infrastructure\Appointment::where('reference', $reference)->exists()) {
    $service = App\Modules\Catalog\Infrastructure\Service::firstOrFail();
    $user = App\Modules\Identity\Infrastructure\User::where('mobile', '09000000552')->sole();
    $patient = App\Modules\Patients\Infrastructure\Patient::create(['name' => 'مراجع ساختگی آزمون رابط']);
    $actor = new App\Modules\Identity\Domain\BookingActor($user->id, $user->mobile, [], (string) config('app.key'));
    $start = Carbon\CarbonImmutable::now('Asia/Tehran')->startOfDay()->addHours(12)->utc();
    App\Modules\Booking\Infrastructure\Appointment::create(['reference' => $reference, 'patient_id' => $patient->id, 'booker_user_id' => $user->id, 'booker_mobile_hash' => $actor->mobileHash, 'service_id' => $service->id, 'service_title' => $service->name, 'price_irr' => 1000000, 'base_price_irr' => 1000000, 'discount_irr' => 0, 'duration_minutes' => 30, 'buffer_minutes' => 0, 'capacity_limit' => 1, 'local_date' => $start->setTimezone('Asia/Tehran')->toDateString(), 'start_at' => $start, 'end_at' => $start->addMinutes(30), 'occupied_until' => $start->addMinutes(30), 'status' => 'confirmed', 'payment_status' => 'unpaid', 'balance_due_irr' => 1000000, 'channel' => 'phone', 'version' => 1]);
}
if (!Illuminate\Support\Facades\DB::table('forms')->exists()) {
    app(App\Modules\DynamicForms\Application\PrivateFormService::class)->publish($admin, null, ['title' => 'فرم ساختگی آزمون رابط', 'service_id' => App\Modules\Catalog\Infrastructure\Service::firstOrFail()->id, 'is_active' => true, 'expected_version' => 0, 'reason' => 'آزمون رابط با داده ساختگی', 'schema' => [
        ['key' => 'choice', 'label' => 'انتخاب ساختگی', 'type' => 'select', 'required' => true, 'options' => ['بله', 'خیر']],
        ['key' => 'date', 'label' => 'روز ساختگی', 'type' => 'date', 'required' => false],
    ]]);
}
echo "SYNTHETIC_PANEL_FIXTURE_READY\n";
