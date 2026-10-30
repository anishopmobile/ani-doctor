<?php

namespace Tests\Feature;

use App\Modules\Identity\Infrastructure\User;
use App\Modules\Catalog\Infrastructure\Service;
use App\Modules\Patients\Infrastructure\Patient;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class P3MigrationTest extends TestCase
{
    public function test_rollback_of_p3_and_reapply_preserve_p1_and_p2_records(): void
    {
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Empty EMR schema.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Homepage contact inbox.
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('website_contact_messages'));
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Optional public article author.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Independent weekly announcements.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website-only display order.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Page builder.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Public brand settings.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website service editorial copy migration.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Additional later website migration boundary.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website theme settings migration.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website private media migration.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website taxonomy migration.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website articles migration.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website links migration, added after CMS sections.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Website CMS migration, added after P7.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Remove P7 first.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // P6
        // Remove P5 and P4 before testing the historic P3 rollback contract.
        $this->artisan('migrate:rollback', ['--step' => 1,'--force' => true])->assertSuccessful();
        $this->artisan('migrate:rollback', ['--step' => 1,'--force' => true])->assertSuccessful();
        $user = User::create(['mobile' => '09120000000']);
        $patient = Patient::create(['name' => 'بیمار آزمایشی قبلی']);
        $service = Service::create(['name' => 'خدمت قبلی','price_irr' => 1000000,'duration_minutes' => 30,'buffer_minutes' => 0,'capacity' => 1,'is_active' => true,'is_online' => true]);
        $this->assertFalse(Schema::hasTable('appointments'));
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('patients', ['id' => $patient->id]);
        $this->assertDatabaseHas('services', ['id' => $service->id]);
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('appointments'));
        $this->assertDatabaseHas('services', ['id' => $service->id,'price_irr' => 1000000]);
    }
}
