<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->string('attendance_status', 24)->default('scheduled');
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('no_show_at')->nullable();
            $table->index(['local_date', 'attendance_status']);
        });
        foreach (['completed', 'no_show'] as $status) {
            \Illuminate\Support\Facades\DB::table('appointments')->where('status', $status)->update(['attendance_status' => $status]);
        }
        Schema::create('attendance_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_hash', 64);
            $table->uuid('idempotency_key');
            $table->string('payload_hash', 64);
            $table->string('action', 24);
            $table->json('before_state');
            $table->json('after_state');
            $table->string('reason', 500);
            $table->timestamp('created_at');
            $table->unique(['actor_hash', 'idempotency_key']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('attendance_events');
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropIndex(['local_date', 'attendance_status']);
            $table->dropColumn(['attendance_status', 'arrived_at', 'completed_at', 'no_show_at']);
        });
    }
};
