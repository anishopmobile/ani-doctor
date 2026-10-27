<?php

namespace Tests\Integration;

use App\Modules\Identity\Infrastructure\User;
use App\Modules\Website\Application\PageService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class WebsitePageIntegrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_save_and_publish_competing_for_one_version_commit_only_one_revision(): void
    {
        $this->assertSame('mysql', DB::getDriverName());
        $actor = User::create(['mobile' => '09120000443']);
        $page = app(PageService::class)->create(['slug' => 'race', 'title' => 'عنوان اولیه'], $actor->id);
        $barrier = storage_path('framework/website-race-'.bin2hex(random_bytes(8)));
        $processes = [];
        try {
            foreach (['save', 'publish'] as $operation) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/website-page-worker.php')], base_path(), [
                    'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mysql',
                    'DB_HOST' => config('database.connections.mysql.host'), 'DB_PORT' => (string) config('database.connections.mysql.port'),
                    'DB_DATABASE' => config('database.connections.mysql.database'), 'DB_USERNAME' => config('database.connections.mysql.username'),
                    'DB_PASSWORD' => config('database.connections.mysql.password'),
                ]);
                $process->setInput(json_encode(['page' => $page->id, 'actor' => $actor->id, 'barrier' => $barrier, 'operation' => $operation], JSON_THROW_ON_ERROR));
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
            $this->assertSame(2, $page->fresh()->version);
            $this->assertDatabaseCount('website_page_revisions', 2);
            if ($page->fresh()->published_version !== null) {
                $this->assertSame('عنوان اولیه', $page->fresh()->published_snapshot['title']);
            }
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
}
