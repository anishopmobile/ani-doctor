<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('mobile', 11)->unique();
            $table->timestamp('mobile_verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 32)->unique();
        });
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
        });
        Schema::create('user_roles', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->primary(['user_id', 'role_id']);
        });
        Schema::create('permission_role', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });
        Schema::create('otp_challenges', function (Blueprint $table): void {
            // Stable row per mobile serializes issuance and consumption on InnoDB.
            $table->string('mobile', 11)->primary();
            $table->uuid('challenge_id')->nullable()->unique();
            $table->string('purpose', 16)->nullable();
            $table->string('code_hash')->nullable();
            $table->string('status', 16)->default('unused');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
        });
        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
        Schema::create('identity_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('event', 64);
            $table->string('role', 32)->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['identity_audit_events', 'sessions', 'otp_challenges', 'permission_role', 'user_roles', 'permissions', 'roles', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
