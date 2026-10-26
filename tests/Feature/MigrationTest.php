<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class MigrationTest extends TestCase
{
    public function test_empty_database_can_migrate_rollback_and_migrate_again(): void
    {
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('patient_user_links'));
        $this->artisan('migrate:rollback', ['--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasTable('patients'));
        $this->artisan('migrate', ['--seed' => true, '--force' => true])->assertSuccessful();
        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseCount('users', 0);
    }
}
