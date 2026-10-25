<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('appointments', fn (Blueprint $table) => $table->text('notification_mobile')->nullable());
        Schema::create('forms', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 150);
            $table->foreignId('service_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('page_key', 40)->nullable();
            $table->unsignedInteger('published_version')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('form_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->json('schema');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500);
            $table->timestamp('created_at');
            $table->unique(['form_id', 'number']);
        });
        Schema::create('form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $table->foreignId('form_version_id')->constrained()->restrictOnDelete();
            $table->text('answers');
            $table->string('actor_hash', 64);
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->string('payload_hash', 64);
            $table->timestamp('consented_at');
            $table->timestamp('created_at');
            $table->unique(['actor_hash', 'idempotency_key']);
            $table->index(['appointment_id', 'form_version_id', 'id']);
        });
        Schema::create('form_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $table->foreignId('form_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_hash', 64);
            $table->string('action', 24);
            $table->timestamp('created_at');
        });
        Schema::create('sms_templates', function (Blueprint $table): void {
            $table->string('event', 24)->primary();
            $table->unsignedBigInteger('provider_template_id');
            $table->boolean('enabled')->default(false);
            $table->foreignId('secretary_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('reminder_minutes')->default(1440);
            $table->timestamps();
        });
        Schema::create('message_outbox', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 120)->unique();
            $table->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $table->string('event', 24);
            $table->text('recipient');
            $table->text('parameters');
            $table->unsignedBigInteger('provider_template_id');
            $table->timestamp('appointment_start');
            $table->string('state', 24)->default('queued');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('available_at')->index();
            $table->timestamp('claimed_at')->nullable();
            $table->string('provider_message_id', 64)->nullable();
            $table->string('delivery_code', 16)->nullable();
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamps();
            $table->index(['state', 'available_at']);
        });
        Schema::create('sms_delivery_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_id')->constrained('message_outbox')->restrictOnDelete();
            $table->string('state', 24);
            $table->timestamp('created_at');
        });
    }
    public function down(): void
    {
        foreach (['sms_delivery_logs', 'message_outbox', 'sms_templates', 'form_access_logs', 'form_submissions', 'form_versions', 'forms'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('appointments', fn (Blueprint $table) => $table->dropColumn('notification_mobile'));
    }
};
