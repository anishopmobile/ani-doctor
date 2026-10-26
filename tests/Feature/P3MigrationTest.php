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
        // Remove P5 and P4 before testing the historic P3 rollback contract.
        $this->artisan('migrate:rollback', ['--step' => 1,'--force' => true])->assertSuccessful();
        $this->artisan('migrate:rollback', ['--step' => 1,'--force' => true])->assertSuccessful();
        $user = User::create(['mobile' => '09120000000']);
        $patient = Patient::create(['name' => 'بیمار آزمایشی قبلی']);
        $service = Service::create(['name' => 'خدمت قبلی','price_irr' => 1000000,'duration_minutes' => 30,'buffer_minutes' => 0,'capacity' => 1,'is_active' => true,'is_online' => true]);
        $this->artisan('migrate:rollback', ['--step' => 1,'--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('appointments'));
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('patients', ['id' => $patient->id]);
        $this->assertDatabaseHas('services', ['id' => $service->id]);
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('appointments'));
        $this->assertDatabaseHas('services', ['id' => $service->id,'price_irr' => 1000000]);
    }
}
