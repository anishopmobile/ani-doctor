<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->unsignedBigInteger('price_irr');
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('buffer_minutes')->default(0);
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_online')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('service_price_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('price_irr');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
        });
        Schema::create('schedule_state', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedSmallInteger('horizon_days')->default(30);
            $table->unsignedBigInteger('version')->default(1);
        });
        DB::table('schedule_state')->insert(['id' => 1, 'horizon_days' => 30, 'version' => 1]);
        Schema::create('weekly_schedules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('weekday')->unique(); // ISO: Monday=1, Sunday=7.
            $table->boolean('is_closed')->default(false);
        });
        Schema::create('work_shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('weekly_schedule_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('start_minute');
            $table->unsignedSmallInteger('end_minute');
            $table->unsignedSmallInteger('step_minutes');
            $table->unsignedSmallInteger('capacity')->default(1);
        });
        Schema::create('schedule_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->date('start_date')->index(); // Gregorian clinic-local date, not a timestamp.
            $table->date('end_date')->index();
            $table->string('kind', 16); // closed or override (explicit reopening).
            $table->json('shifts');
            $table->string('reason', 500);
            $table->timestamps();
        });
        Schema::create('configuration_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 64);
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['configuration_audit_events', 'schedule_exceptions', 'work_shifts', 'weekly_schedules', 'schedule_state', 'service_price_histories', 'services'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
