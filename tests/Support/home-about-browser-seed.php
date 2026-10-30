<?php

// Existing guard permits only a disposable, synthetic SQLite fixture.
require __DIR__.'/panel-browser-seed.php';

app(App\Modules\Website\Application\ThemeService::class)->save([
    'home_about_layout' => 'classic', 'show_about' => true,
    'about_title' => 'عنوان ساختگی برای آزمون سه مدل درباره ما',
    'about_text' => 'این متن ساختگی فقط برای بررسی چیدمان و خوانایی درباره ما در پایگاه داده آزمون است.',
    'home_about_badge_title' => 'کارت ساختگی آزمون', 'home_about_badge_text' => 'اطلاعات ذخیره‌شده آزمون',
    'home_about_stats' => array_map(fn ($i) => ['title' => 'مقدار آزمون '.$i, 'text' => 'توضیح ساختگی آزمون'], range(1, 3)),
]);
$path = 'website-media/home-about-ui.webp';
Illuminate\Support\Facades\Storage::disk('local')->put($path, file_get_contents(__DIR__.'/../../public/assets/clinic-art.webp'));
App\Modules\Website\Infrastructure\WebsiteMedia::updateOrCreate(['owner_type' => 'about', 'owner_id' => 1], ['path' => $path, 'mime_type' => 'image/webp', 'alt_text' => 'تصویر مستقل ساختگی آزمون درباره ما']);
app(App\Modules\Website\Application\ThemeService::class)->save(['about_is_published' => true]);
echo "HOME_ABOUT_SYNTHETIC_FIXTURE_READY\n";
