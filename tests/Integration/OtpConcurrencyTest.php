<?php

namespace Tests\Integration;

use App\Modules\Identity\Application\AuthService;
use App\Modules\Identity\Domain\SmsProviderInterface;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\FakeSmsProvider;
use Tests\TestCase;

final class OtpConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_simultaneous_verifications_consume_code_only_once(): void
    {
        $this->assertSame('mysql', DB::getDriverName(), 'This suite must run against disposable MySQL, not SQLite.');
        $this->seed();
        $sms = new FakeSmsProvider();
        $this->app->instance(SmsProviderInterface::class, $sms);
        $challenge = app(AuthService::class)->requestCode('09123456789', 'login');
        $results = $this->race(['operation' => 'verify', 'mobile' => '09123456789', 'id' => $challenge['id'], 'code' => $sms->lastCode()]);
        $this->assertSame(1, substr_count($results, 'SUCCESS'));
        $this->assertSame(1, substr_count($results, 'OTP_INVALID'));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('identity_audit_events', 1);
    }

    public function test_simultaneous_requests_only_issue_one_challenge(): void
    {
        $this->assertSame('mysql', DB::getDriverName());
        $this->seed();
        $results = $this->race(['operation' => 'issue', 'mobile' => '09123456789']);
        $this->assertSame(1, substr_count($results, 'SUCCESS'));
        $this->assertSame(1, substr_count($results, 'OTP_COOLDOWN'));
        $this->assertDatabaseCount('otp_challenges', 1);
    }

    private function race(array $input): string
    {
        $barrier = storage_path('framework/otp-race-'.bin2hex(random_bytes(8)));
        $processes = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/otp-worker.php')], base_path(), [
                    'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'),
                    'DB_CONNECTION' => 'mysql', 'DB_HOST' => config('database.connections.mysql.host'),
                    'DB_PORT' => (string) config('database.connections.mysql.port'),
                    'DB_DATABASE' => config('database.connections.mysql.database'),
                    'DB_USERNAME' => config('database.connections.mysql.username'),
                    'DB_PASSWORD' => config('database.connections.mysql.password'),
                ]);
                $process->setInput(json_encode([...$input, 'barrier' => $barrier], JSON_THROW_ON_ERROR));
                $process->setTimeout(20)->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 10;
            while (!str_contains($processes[0]->getOutput(), 'READY') || !str_contains($processes[1]->getOutput(), 'READY')) {
                if (microtime(true) > $deadline) {
                    $this->fail('Workers did not become ready: '.$processes[0]->getErrorOutput().$processes[1]->getErrorOutput());
                }
                usleep(10000);
            }
            touch($barrier);
            $output = '';
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
                $output .= $process->getOutput();
            }
            return $output;
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
