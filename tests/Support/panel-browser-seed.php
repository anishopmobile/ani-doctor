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
foreach (['admin' => '09000000551', 'patient' => '09000000552'] as $role => $mobile) {
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
echo "SYNTHETIC_PANEL_FIXTURE_READY\n";
