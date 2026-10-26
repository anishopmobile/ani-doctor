<?php

namespace Tests\Integration;

use App\Modules\Identity\Infrastructure\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class ScheduleConcurrencyTest extends TestCase
{
    use DatabaseMigrations;
    public function test_simultaneous_overlapping_exceptions_cannot_both_be_saved(): void
    {
        $this->assertSame('mysql', DB::getDriverName());
        $actor = User::create(['mobile' => '09123456789']);
        $barrier = storage_path('framework/schedule-race-'.bin2hex(random_bytes(8)));
        $workers = [];
        try {
            for ($i = 0;$i < 2;$i++) {
                $process = new Process([PHP_BINARY,base_path('tests/Support/schedule-worker.php')], base_path(), [
                    'APP_ENV' => 'testing','APP_KEY' => config('app.key'),'DB_CONNECTION' => 'mysql',
                    'DB_HOST' => config('database.connections.mysql.host'),'DB_PORT' => (string)config('database.connections.mysql.port'),
                    'DB_DATABASE' => config('database.connections.mysql.database'),'DB_USERNAME' => config('database.connections.mysql.username'),
                    'DB_PASSWORD' => config('database.connections.mysql.password'),
                ]);
                $process->setInput(json_encode(['actor' => $actor->id,'barrier' => $barrier], JSON_THROW_ON_ERROR));
                $process->setTimeout(20)->start();
                $workers[] = $process;
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
            $this->assertDatabaseCount('schedule_exceptions', 1);
            $this->assertDatabaseCount('configuration_audit_events', 1);
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
