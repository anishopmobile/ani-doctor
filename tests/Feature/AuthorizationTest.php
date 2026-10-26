<?php

namespace Tests\Feature;

use App\Modules\Identity\Infrastructure\Role;
use App\Modules\Identity\Infrastructure\User;
use App\Modules\Patients\Infrastructure\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_role_can_only_open_its_own_panel(): void
    {
        $this->seed();
        foreach (['patient', 'secretary', 'admin'] as $index => $role) {
            $user = User::create(['mobile' => '0912345678'.$index]);
            $user->forceFill(['mobile_verified_at' => now()])->save();
            $user->roles()->attach(Role::where('name', $role)->sole());
            $this->actingAs($user);
            foreach (['patient', 'secretary', 'admin'] as $panel) {
                $this->get('/'.$panel)->assertStatus($panel === $role ? 200 : 403);
            }
            $user->forceFill(['is_active' => false])->save();
            $this->get('/'.$role)->assertForbidden();
        }
    }

    public function test_unverified_users_and_guests_have_no_panel_access(): void
    {
        $this->seed();
        $this->get('/admin')->assertRedirect('/login');
        $user = User::create(['mobile' => '09123456789']);
        $user->roles()->attach(Role::where('name', 'admin')->sole());
        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_patient_ownership_does_not_follow_shared_mobile(): void
    {
        $a = User::create(['mobile' => '09123456789']);
        $b = User::create(['mobile' => '09123456788']);
        $a->forceFill(['mobile_verified_at' => now()])->save();
        $b->forceFill(['mobile_verified_at' => now()])->save();
        $patient = Patient::create(['name' => 'بیمار آزمایشی', 'contact_mobile' => $a->mobile]);
        $other = Patient::create(['name' => 'بیمار دیگر', 'contact_mobile' => $a->mobile]);
        DB::table('patient_user_links')->insert(['user_id' => $a->id, 'patient_id' => $patient->id, 'created_at' => now()]);
        $this->assertTrue(Gate::forUser($a)->allows('view', $patient));
        $this->assertFalse(Gate::forUser($a)->allows('view', $other));
        $this->assertFalse(Gate::forUser($b)->allows('view', $patient));
    }

    public function test_staff_provisioning_is_explicit_audited_and_not_preverified(): void
    {
        $this->seed();
        $this->seed();
        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseCount('users', 0);
        $this->artisan('identity:provision-staff', ['mobile' => '۰۹۱۲۳۴۵۶۷۸۹', 'role' => 'secretary'])->assertSuccessful();
        $user = User::sole();
        $this->assertNull($user->mobile_verified_at);
        $this->assertFalse($user->hasPermission('panel.secretary'));
        $this->assertDatabaseHas('identity_audit_events', ['event' => 'staff_role_granted_by_cli', 'role' => 'secretary']);
        $this->artisan('identity:provision-staff', ['mobile' => '09123456789', 'role' => 'doctor'])->assertFailed();
    }
}
