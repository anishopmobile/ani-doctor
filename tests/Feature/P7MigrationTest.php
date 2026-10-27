<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\P7Fixtures;
use Tests\TestCase;

final class P7MigrationTest extends TestCase
{
    use DatabaseMigrations;
    use P7Fixtures;
    private $admin;
    private $patient;
    private $secretary;
    private $service;
    public function test_rollback_preserves_net_receipts_and_p6_and_reapply_does_not_recreate_discounts(): void
    {
        $this->seed();
        $this->admin = $this->booker('09120000001', 'admin');
        $this->secretary = $this->booker('09120000002', 'secretary');
        $this->patient = $this->booker();
        $this->service = $this->bookingServiceFixture($this->admin);
        $this->offer();
        $appointment = $this->collect($this->discounted());
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website theme settings migration.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website private media migration.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website taxonomy migration.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website articles migration.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website links migration, added after CMS sections.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website CMS migration, added after P7.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('discounts'));
        $this->assertFalse(Schema::hasTable('report_access_events'));
        $this->assertTrue(Schema::hasTable('form_submissions'));
        $this->assertTrue(Schema::hasTable('message_outbox'));
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'price_irr' => 800000, 'status' => 'confirmed']);
        $this->assertDatabaseHas('payments', ['appointment_id' => $appointment->id, 'amount_irr' => 800000]);
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertDatabaseCount('discount_redemptions', 0);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'base_price_irr' => 800000, 'discount_irr' => 0]);
        $this->assertSame(800000, (int) DB::table('payments')->sum('amount_irr'));
    }
}
