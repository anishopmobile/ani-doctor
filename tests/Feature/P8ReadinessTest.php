<?php

namespace Tests\Feature;

use App\Modules\Operations\Application\ReadinessService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class P8ReadinessTest extends TestCase
{
    use DatabaseMigrations;

    public function test_readiness_does_not_claim_production_or_recovery_from_a_test_database(): void
    {
        $result = app(ReadinessService::class)->inspect();
        $this->assertFalse($result['ready']);
        $this->assertSame('fail', $result['checks']['production_debug_off']);
        $this->assertSame('fail', $result['checks']['scheduler_heartbeat']);
        $this->assertSame('fail', $result['checks']['backup_key']);
        $this->assertStringNotContainsString(config('app.key'), json_encode($result));
        $this->artisan('ops:heartbeat')->assertSuccessful();
        $result = app(ReadinessService::class)->inspect();
        $this->assertSame('pass', $result['checks']['scheduler_heartbeat']);
        Cache::put('operations.scheduler_seen', time() - 181);
        $this->assertSame('fail', app(ReadinessService::class)->inspect()['checks']['scheduler_heartbeat']);
        $this->artisan('ops:check')->assertFailed();
    }
}
