<?php

// Isolated exact-package staging only. Never shipped or used with clinic data.
require '/var/www/app/vendor/autoload.php';
$app = require '/var/www/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (getenv('P8_SYNTHETIC_FIXTURE') !== '1' || config('database.connections.mysql.database') !== 'ani_doctor_p8_smoke'
    || config('app.url') !== 'https://localhost:18443' || config('payments.zarinpal.enabled') || config('notifications.enabled')) {
    throw new RuntimeException('ISOLATED_SECURITY_FIXTURE_REQUIRED');
}
$actor = App\Modules\Identity\Infrastructure\User::where('mobile', '09000000008')->firstOrFail();
$pages = app(App\Modules\Website\Application\PageService::class);
$page = $pages->create(['slug' => 'security-audit', 'title' => 'راهنمای ساختگی ارزیابی امنیتی', 'meta_description' => 'صفحه ساختگی اسکن'], $actor->id);
$blocks = [];
foreach (['hero', 'text', 'services', 'articles', 'contact', 'cta', 'features', 'faq', 'spacer'] as $type) {
    $block = ['id' => (string) Illuminate\Support\Str::uuid(), 'type' => $type, 'heading' => 'بخش آزمایشی '.$type, 'text' => 'محتوای ساختگی', 'layout' => 'stacked', 'background' => 'plain'];
    if (in_array($type, ['features', 'faq'], true)) {
        $block['items'] = [['title' => 'عنوان مورد', 'text' => 'متن مورد']];
    }
    $blocks[] = $block;
}
$page = $pages->save($page, ['title' => $page->title, 'meta_description' => $page->meta_description, 'blocks' => $blocks, 'expected_version' => 1], $actor->id);
$pages->publication($page, 2, $actor->id, true);
echo "SYNTHETIC_CMS_SCAN_FIXTURE_READY\n";
