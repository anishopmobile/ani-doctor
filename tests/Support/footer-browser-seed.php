<?php

// Reuse the strict guard: only the disposable synthetic SQLite database.
require __DIR__.'/panel-browser-seed.php';

app(App\Modules\Website\Application\ThemeService::class)->save([
    'footer_text' => 'معرفی ساختگی برای آزمون فوتر. این اطلاعات فقط در پایگاه داده مجزای آزمون ذخیره می‌شوند.',
    'footer_booking_text' => 'توضیح رزرو ساختگی آزمون', 'footer_slogan' => 'شعار ساختگی برای آزمون',
    'contact_email' => 'footer@example.test', 'contact_secondary_phone' => '02112345678',
    'contact_hours' => "ساعات پاسخ‌گویی ساختگی آزمون\nروزها و ساعت‌ها تنها برای بررسی چیدمان است.",
]);
foreach (['social' => ['شبکه ساختگی آزمون', 'https://instagram.com/example'], 'footer_legal' => ['حریم خصوصی ساختگی آزمون', '/privacy']] as $placement => [$label, $url]) {
    App\Modules\Website\Infrastructure\WebsiteLink::updateOrCreate(['placement' => $placement, 'label' => $label], ['url' => $url, 'sort_order' => 0, 'is_published' => true, 'new_tab' => true]);
}
echo "FOOTER_SYNTHETIC_FIXTURE_READY\n";
