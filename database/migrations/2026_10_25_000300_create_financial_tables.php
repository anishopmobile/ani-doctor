<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->unsignedBigInteger('balance_due_irr')->default(0);
        });
        DB::table('appointments')->where('payment_status', 'unpaid')->update(['balance_due_irr' => DB::raw('price_irr')]);
        Schema::create('payment_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->timestamp('emergency_until')->nullable();
            $table->string('reason', 500)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('updated_at')->nullable();
        });
        DB::table('payment_settings')->insert(['id' => 1]);
        Schema::create('financial_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 64);
            $table->string('entity_type', 32);
            $table->string('entity_id', 64);
            $table->json('before_state');
            $table->json('after_state');
            $table->string('reason', 500);
            $table->timestamp('created_at');
        });
        Schema::create('wallet_accounts', function (Blueprint $table): void {
            $table->string('owner_hash', 64)->primary();
            $table->unsignedBigInteger('balance_irr')->default(0);
            $table->unsignedBigInteger('reserved_irr')->default(0);
        });
        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $table->string('actor_hash', 64);
            $table->uuid('idempotency_key');
            $table->string('payload_hash', 64);
            $table->string('owner_hash', 64)->index();
            $table->string('provider', 24)->default('zarinpal');
            $table->string('authority', 64)->nullable()->unique();
            $table->text('merchant_secret')->nullable();
            $table->boolean('sandbox')->default(false);
            $table->unsignedBigInteger('amount_irr');
            $table->unsignedBigInteger('credit_irr')->default(0);
            $table->boolean('credit_reserved')->default(false);
            $table->string('state', 32)->default('requesting')->index();
            $table->timestamp('hold_expires_at');
            $table->timestamps();
            $table->unique(['actor_hash', 'idempotency_key']);
        });
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_attempt_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('provider', 24);
            $table->string('provider_reference', 100);
            $table->unsignedBigInteger('amount_irr');
            $table->unsignedBigInteger('credit_irr')->default(0);
            $table->boolean('allocated');
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestamp('created_at');
            $table->unique(['provider', 'provider_reference']);
        });
        Schema::create('payment_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_attempt_id')->unique()->constrained()->restrictOnDelete();
            $table->string('reason', 64);
            $table->string('resolution', 32)->default('credited');
            $table->timestamp('created_at');
        });
        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('owner_hash', 64)->index();
            $table->string('actor_hash', 64);
            $table->uuid('idempotency_key');
            $table->unsignedBigInteger('amount_irr');
            $table->string('status', 24)->default('requested');
            $table->string('reason', 500);
            $table->string('decision_reason', 500)->nullable();
            $table->string('bank_reference', 100)->nullable()->unique();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['actor_hash', 'idempotency_key']);
        });
        Schema::create('wallet_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('owner_hash', 64)->index();
            $table->bigInteger('amount_irr');
            $table->string('business_key', 100)->unique();
            $table->string('kind', 32);
            $table->foreignId('appointment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('created_at');
        });
    }
    public function down(): void
    {
        foreach (['wallet_entries', 'refunds', 'payment_reconciliations', 'payments', 'payment_attempts', 'wallet_accounts', 'financial_events', 'payment_settings'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('appointments', fn (Blueprint $table) => $table->dropColumn('balance_due_irr'));
    }
};
