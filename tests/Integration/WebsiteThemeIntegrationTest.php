<?php

namespace Tests\Integration;

use App\Modules\Website\Application\ThemeService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class WebsiteThemeIntegrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_competing_settings_editors_cannot_overwrite_each_other(): void
    {
        $this->assertSame('mysql', DB::getDriverName());
        app(ThemeService::class)->save(ThemeService::DEFAULTS);
        $barrier = storage_path('framework/website-race-'.bin2hex(random_bytes(8)));
        $processes = [];
        try {
            foreach (['first', 'second'] as $operation) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/website-page-worker.php')], base_path(), [
                    'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mysql',
                    'DB_HOST' => config('database.connections.mysql.host'), 'DB_PORT' => (string) config('database.connections.mysql.port'),
                    'DB_DATABASE' => config('database.connections.mysql.database'), 'DB_USERNAME' => config('database.connections.mysql.username'),
                    'DB_PASSWORD' => config('database.connections.mysql.password'),
                ]);
                $process->setInput(json_encode(['barrier' => $barrier, 'operation' => 'theme', 'title' => $operation], JSON_THROW_ON_ERROR));
                $process->setTimeout(20)->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 10;
            while (!str_contains($processes[0]->getOutput(), 'READY') || !str_contains($processes[1]->getOutput(), 'READY')) {
                if (microtime(true) > $deadline) {
                    $this->fail('Page workers did not become ready.');
                }
                usleep(10000);
            }
            touch($barrier);
            $output = '';
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $output .= $process->getOutput();
            }
            $this->assertSame(1, substr_count($output, 'SUCCESS'));
            $this->assertSame(1, substr_count($output, 'STALE_VERSION'));
            $this->assertSame(2, app(ThemeService::class)->settings()['settings_version']);
            $this->assertContains(app(ThemeService::class)->settings()['hero_title'], ['first', 'second']);
            $this->assertDatabaseCount('website_theme_settings', 1);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            if (file_exists($barrier)) {
                unlink($barrier);
            }
        }
    }

    public function test_media_replacement_cleanup_follows_the_outer_transaction(): void
    {
        $this->assertSame('mysql', DB::getDriverName());
        \Illuminate\Support\Facades\Storage::fake('local');
        app(ThemeService::class)->save(ThemeService::DEFAULTS);
        $media = app(\App\Modules\Website\Application\MediaService::class);
        $media->save('portrait', 1, \Illuminate\Http\UploadedFile::fake()->image('old.png'), 'تصویر ساختگی');
        $old = \App\Modules\Website\Infrastructure\WebsiteMedia::sole()->path;
        DB::beginTransaction();
        $media->save('portrait', 1, \Illuminate\Http\UploadedFile::fake()->image('rolled-back.png'), 'پیش‌نویس');
        $new = \App\Modules\Website\Infrastructure\WebsiteMedia::sole()->path;
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($old);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($new);
        DB::rollBack();
        $this->assertSame($old, \App\Modules\Website\Infrastructure\WebsiteMedia::sole()->path);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($old);
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($new);
        DB::beginTransaction();
        $media->save('portrait', 1, \Illuminate\Http\UploadedFile::fake()->image('committed.png'), 'نسخه تازه');
        $new = \App\Modules\Website\Infrastructure\WebsiteMedia::sole()->path;
        DB::commit();
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($old);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($new);
    }
}
