<?php

namespace Tests\Feature;

use App\Modules\Booking\Application\BookingService;
use Illuminate\Support\Facades\Schema;
use Tests\Support\BookingFixtures;
use Tests\TestCase;

final class P4MigrationTest extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseMigrations;
    use BookingFixtures;
    public function test_p4_rollback_reapply_preserves_p3_appointments_and_backfills_unpaid_debt(): void
    {
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Homepage contact inbox.
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('website_contact_messages'));
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Optional public article author.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Independent weekly announcements.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website-only display order.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Page builder.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Public brand settings.
        $this->seed();
        $user = $this->booker();
        $service = $this->bookingServiceFixture($user);
        $appointment = app(BookingService::class)->create($this->bookingActor($user), $this->bookingData($service));
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website service editorial copy migration.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Additional later website migration boundary.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website theme settings migration.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website private media migration.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website taxonomy migration.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website articles migration.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website links migration, added after CMS sections.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website CMS migration, added after P7.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // P7
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // P6
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // P5
        $this->assertFalse(Schema::hasTable('payments'));
        $this->assertFalse(Schema::hasColumn('appointments', 'balance_due_irr'));
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'pending_payment']);
        $this->assertDatabaseHas('patients', ['id' => $appointment->patient_id]);
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('payments'));
        $this->assertSame($appointment->price_irr, $appointment->fresh()->balance_due_irr);
    }
}
