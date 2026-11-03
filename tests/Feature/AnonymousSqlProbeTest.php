<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AnonymousSqlProbeTest extends TestCase
{
    use RefreshDatabase;

    public static function privatePaths(): array
    {
        return [['/wallet'], ['/appointments']];
    }

    #[DataProvider('privatePaths')]
    public function test_boolean_payloads_cannot_reach_business_queries_and_throttling_remains_enabled(string $path): void
    {
        $this->app->instance('env', 'production');
        config(['app.url' => 'https://localhost:18443']);
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        $payloads = ['query', "query' AND '1'='1'", "query' OR '1'='1'"];
        for ($request = 0; $request < 20; $request++) {
            $response = $this->get('https://localhost:18443'.$path.'?'.http_build_query(['query' => $payloads[$request % 3]]));
            $response->assertRedirect('https://localhost:18443/login');
            $this->assertSame('', $response->getContent());
        }
        $this->get('https://localhost:18443'.$path.'?'.http_build_query(['query' => $payloads[2]]))->assertStatus(429);
        foreach ($queries as $sql) {
            $this->assertDoesNotMatchRegularExpression('/(?:appointments|wallet_entries|refunds)/i', $sql);
        }
        $this->travel(61)->seconds();
        $this->get('https://localhost:18443'.$path.'?'.http_build_query(['query' => $payloads[2]]))
            ->assertRedirect('https://localhost:18443/login');
    }
}
