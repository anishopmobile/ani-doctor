<?php

namespace Tests\Integration;

use App\Modules\Identity\Infrastructure\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class WeeklyAnnouncementConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_first_save_race_preserves_one_record_and_rejects_stale_editor(): void
    {
        $this->assertSame('mysql', DB::getDriverName());
        $actor = User::create(['mobile' => '09123456789']);
        $barrier = storage_path('framework/weekly-announcement-race-'.bin2hex(random_bytes(8)));
        $workers = [];
        try {
            foreach (['مطب', 'بیمارستان'] as $location) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/weekly-announcement-worker.php')], base_path(), [
                    'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mysql',
                    'DB_HOST' => config('database.connections.mysql.host'), 'DB_PORT' => (string) config('database.connections.mysql.port'),
                    'DB_DATABASE' => config('database.connections.mysql.database'), 'DB_USERNAME' => config('database.connections.mysql.username'),
                    'DB_PASSWORD' => config('database.connections.mysql.password'),
                ]);
                $worker->setInput(json_encode(['actor' => $actor->id, 'barrier' => $barrier, 'location' => $location], JSON_THROW_ON_ERROR));
                $worker->setTimeout(20)->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 10;
            while (!str_contains($workers[0]->getOutput(), 'READY') || !str_contains($workers[1]->getOutput(), 'READY')) {
                if (microtime(true) > $deadline) {
                    $this->fail('Workers did not become ready');
                }
                usleep(10000);
            }
            touch($barrier);
            $output = '';
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
                $output .= $worker->getOutput();
            }
            $this->assertSame(1, substr_count($output, 'SUCCESS'));
            $this->assertSame(1, substr_count($output, 'CONFLICT'));
            $this->assertDatabaseCount('weekly_announcements', 1);
            $this->assertDatabaseHas('weekly_announcements', ['date' => '2026-10-25', 'version' => 1]);
            $this->assertDatabaseCount('configuration_audit_events', 1);
            $this->assertDatabaseCount('weekly_schedules', 0);
            $this->assertDatabaseCount('appointments', 0);
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            if (file_exists($barrier)) {
                unlink($barrier);
            }
        }
    }
}
