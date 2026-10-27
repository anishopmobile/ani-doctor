<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ClinicFixtures;
use Tests\TestCase;

final class P6MigrationTest extends TestCase
{
    use DatabaseMigrations;
    use ClinicFixtures;
    private $secretary;
    private $service;
    public function test_p6_rollback_preserves_p5_attendance_and_all_financial_records_and_reapplies_without_fake_sms(): void
    {
        $this->seed();
        $this->secretary = $this->booker('09123456787', 'secretary');
        $this->service = $this->bookingServiceFixture($this->secretary);
        $appointment = $this->clinicAppointment();
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website theme settings migration.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website private media migration.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website taxonomy migration.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website articles migration.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website links migration, added after CMS sections.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website CMS migration, added after P7.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // P7
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('form_submissions'));
        $this->assertFalse(Schema::hasTable('message_outbox'));
        $this->assertFalse(Schema::hasColumn('appointments', 'notification_mobile'));
        $this->assertTrue(Schema::hasTable('attendance_events'));
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'confirmed', 'payment_status' => 'paid']);
        $this->assertDatabaseCount('payments', 1);
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertNull(DB::table('appointments')->where('id', $appointment->id)->value('notification_mobile'));
        $this->assertDatabaseCount('message_outbox', 0);
        $this->assertDatabaseCount('form_submissions', 0);
        $this->assertDatabaseCount('payments', 1);
    }
}
