<?php

namespace Tests\Feature;

use App\Modules\Catalog\Infrastructure\Service;
use App\Modules\Operations\Application\InstallationCheckService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class InstallationCheckTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://127.0.0.1:8000', 'app.env' => 'testing', 'session.secure' => false, 'app.debug' => false]);
    }

    public function test_read_only_check_passes_current_install_without_exposing_the_key(): void
    {
        $before = DB::table('migrations')->count();
        $result = app(InstallationCheckService::class)->inspect();
        $this->assertTrue($result['ready']);
        $this->assertSame([], $result['pending_migrations']);
        $this->assertStringNotContainsString(config('app.key'), json_encode($result));
        $this->assertSame($before, DB::table('migrations')->count());
        $this->artisan('ops:installation-check')->assertSuccessful();
    }

    public function test_copying_cms_files_without_migrating_reproduces_500_and_upgrade_preserves_catalog(): void
    {
        $service = Service::create([
            'name' => 'خدمت موجود قبل از ارتقا', 'price_irr' => 950000, 'duration_minutes' => 30,
            'buffer_minutes' => 0, 'capacity' => 1, 'is_active' => true, 'is_online' => true,
        ]);
        $this->artisan('migrate:rollback', ['--step' => 10, '--force' => true])->assertSuccessful();
        $this->get('/')->assertStatus(500);
        $result = app(InstallationCheckService::class)->inspect();
        $this->assertFalse($result['ready']);
        $this->assertCount(10, $result['pending_migrations']);
        $this->assertSame('fail', $result['checks']['website_schema']);
        $this->artisan('ops:installation-check')->assertFailed();

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder', '--force' => true])->assertSuccessful();
        $this->assertTrue(app(InstallationCheckService::class)->inspect()['ready']);
        $this->get('/')->assertOk()->assertSee('خدمت موجود قبل از ارتقا');
        $this->get('/services')->assertOk();
        $this->get('/articles')->assertOk();
        $this->get('/sitemap.xml')->assertOk();
        $this->get('/calendar')->assertOk();
        $this->assertDatabaseHas('services', ['id' => $service->id, 'price_irr' => 950000]);
    }

    public function test_failed_database_check_never_returns_credentials_or_exception_payload(): void
    {
        $connection = config('database.default');
        config(['database.default' => 'installation_missing_driver', 'database.connections.installation_missing_driver' => [
            'driver' => 'missing', 'password' => 'PRIVATE-INSTALLATION-SENTINEL',
        ]]);
        try {
            $result = app(InstallationCheckService::class)->inspect();
            $this->assertFalse($result['ready']);
            $this->assertSame('fail', $result['checks']['database_connection']);
            $this->assertStringNotContainsString('PRIVATE-INSTALLATION-SENTINEL', json_encode($result));
            $this->assertArrayNotHasKey('website_schema', $result['checks']);
        } finally {
            config(['database.default' => $connection]);
        }
    }

    public function test_pre_composer_check_exits_cleanly_when_extensions_are_disabled(): void
    {
        $process = new Process([PHP_BINARY, '-n', base_path('scripts/check-install.php')]);
        $process->run();
        $this->assertSame(1, $process->getExitCode());
        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertFalse($result['runtime_ready']);
        $this->assertFalse($result['checks']['extension_mbstring']);
        $this->assertStringNotContainsString('Stack trace', $process->getErrorOutput());
    }

    public function test_missing_key_and_wrong_http_cookie_configuration_fail_without_changes(): void
    {
        config(['app.key' => '', 'app.env' => 'production', 'session.secure' => true]);
        $result = app(InstallationCheckService::class)->inspect();
        $this->assertFalse($result['ready']);
        $this->assertSame('fail', $result['checks']['app_key']);
        $this->assertSame('fail', $result['checks']['http_local_configuration']);
    }
}
