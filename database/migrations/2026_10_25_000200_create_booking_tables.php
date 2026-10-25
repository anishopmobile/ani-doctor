<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('booking_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedSmallInteger('hold_minutes')->default(15);
            $table->unsignedSmallInteger('cutoff_hours')->default(24);
            $table->unsignedSmallInteger('max_active_patient')->default(2);
            $table->unsignedSmallInteger('max_active_booker')->default(2);
        });
        DB::table('booking_settings')->insert(['id' => 1]);
        Schema::create('booking_actor_locks', function (Blueprint $table): void {
            $table->string('key', 64)->primary();
        });
        Schema::create('booking_day_locks', function (Blueprint $table): void {
            $table->date('date')->primary();
        });
        Schema::create('guest_patient_links', function (Blueprint $table): void {
            $table->string('mobile_hash', 64);
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->primary(['mobile_hash','patient_id']);
        });
        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('booker_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('booker_mobile_hash', 64)->index();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('service_title', 150);
            $table->unsignedBigInteger('price_irr');
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('buffer_minutes');
            $table->unsignedSmallInteger('capacity_limit');
            $table->date('local_date')->index();
            $table->timestamp('start_at')->index();
            $table->timestamp('end_at');
            $table->timestamp('occupied_until');
            $table->string('status', 32)->default('pending_payment');
            $table->string('payment_status', 32)->default('unpaid');
            $table->timestamp('hold_expires_at')->nullable()->index();
            $table->string('channel', 16);
            $table->boolean('is_override')->default(false);
            $table->string('override_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['patient_id','status']);
            $table->index(['local_date','status','hold_expires_at']);
        });
        Schema::create('appointment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_hash', 64);
            $table->string('action', 32);
            $table->string('reason', 500)->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state');
            $table->timestamp('created_at');
        });
        Schema::create('booking_operations', function (Blueprint $table): void {
            $table->id();
            $table->string('actor_hash', 64);
            $table->uuid('key');
            $table->string('payload_hash', 64);
            $table->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $table->timestamp('created_at');
            $table->unique(['actor_hash','key']);
        });
    }
    public function down(): void
    {
        foreach (['booking_operations','appointment_events','appointments','guest_patient_links','booking_day_locks','booking_actor_locks','booking_settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
