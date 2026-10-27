<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ClinicFixtures;
use Tests\TestCase;

final class P5MigrationTest extends TestCase
{
    use DatabaseMigrations;
    use ClinicFixtures;
    private $secretary;
    private $service;
    public function test_p5_rollback_preserves_appointments_receipts_wallet_and_reapplies_attendance_projection(): void
    {
        $this->seed();
        $this->secretary = $this->booker('09123456787', 'secretary');
        $this->service = $this->bookingServiceFixture($this->secretary);
        $appointment = $this->clinicAppointment();
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website taxonomy migration.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website articles migration.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website links migration, added after CMS sections.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website CMS migration, added after P7.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // P7
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // P6
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('attendance_events'));
        $this->assertFalse(Schema::hasColumn('appointments', 'attendance_status'));
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'confirmed', 'payment_status' => 'paid']);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('wallet_entries', 0);
        DB::table('appointments')->where('id', $appointment->id)->update(['status' => 'completed']);
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('attendance_events'));
        $this->assertSame('completed', $appointment->fresh()->attendance_status);
        $this->assertNull($appointment->fresh()->completed_at); // no invented historical timestamp
        $this->assertDatabaseCount('payments', 1);
    }
}
