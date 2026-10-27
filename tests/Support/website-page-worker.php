<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (config('app.env') !== 'testing') {
    throw new RuntimeException('TESTING_REQUIRED');
}
fwrite(STDOUT, "READY\n");
while (!file_exists($input['barrier'])) {
    usleep(10000);
}
try {
    if ($input['operation'] === 'theme') {
        app(App\Modules\Website\Application\ThemeService::class)->save(App\Modules\Website\Application\ThemeService::DEFAULTS + ['hero_title' => $input['title'], 'expected_settings_version' => 1]);
        fwrite(STDOUT, "SUCCESS\n");
        exit(0);
    }
    $page = App\Modules\Website\Infrastructure\WebsitePage::findOrFail($input['page']);
    $pages = app(App\Modules\Website\Application\PageService::class);
    if ($input['operation'] === 'publish') {
        $pages->publication($page, 1, $input['actor'], true);
    } else {
        $pages->save($page, ['title' => 'ویرایش همزمان', 'blocks' => $page->blocks, 'expected_version' => 1], $input['actor']);
    }
    fwrite(STDOUT, "SUCCESS\n");
} catch (Illuminate\Validation\ValidationException) {
    fwrite(STDOUT, "STALE_VERSION\n");
}
