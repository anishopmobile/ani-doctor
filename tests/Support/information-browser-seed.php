<?php

// Reuse the existing strict guard: only an isolated synthetic SQLite fixture.
require __DIR__.'/panel-browser-seed.php';

app(App\Modules\Website\Application\ThemeService::class)->save([
    'contact_email' => 'office.information@example.test',
    'contact_map_url' => 'https://maps.example.test/clinic',
    'contact_hours' => 'ساعات پاسخ‌گویی ساختگی آزمون رابط',
]);
$pages = app(App\Modules\Website\Application\PageService::class);
foreach (['privacy' => 'حریم خصوصی آزمون', 'policies' => 'سیاست‌های کاری آزمون'] as $slug => $title) {
    $page = App\Modules\Website\Infrastructure\WebsitePage::where('slug', $slug)->first() ?? $pages->create(['slug' => $slug, 'title' => $title], $admin->id);
    $blocks = [['id' => (string) Illuminate\Support\Str::uuid(), 'type' => 'features', 'heading' => 'نکات منتشرشده آزمون', 'layout' => 'grid', 'background' => 'plain', 'items' => array_map(fn ($index) => ['title' => 'ویژگی ساختگی '.$index, 'text' => 'این متن تنها برای آزمون نمایش صفحه است.'], range(1, 4))]];
    foreach (['اطلاعات دریافتی', 'نحوه استفاده', 'دسترسی به اطلاعات', 'درخواست‌های کاربران'] as $index => $heading) {
        $blocks[] = ['id' => (string) Illuminate\Support\Str::uuid(), 'type' => 'text', 'heading' => $heading, 'text' => str_repeat('متن ساختگی برای آزمون خوانایی و سطرهای طولانی صفحه. ', 8), 'anchor' => 'topic-'.($index + 1), 'layout' => 'stacked', 'background' => 'plain'];
    }
    $page = $pages->save($page, ['expected_version' => $page->version, 'title' => $title, 'blocks' => $blocks], $admin->id);
    $pages->publication($page, $page->version, $admin->id, true);
}
echo "INFORMATION_SYNTHETIC_FIXTURE_READY\n";
