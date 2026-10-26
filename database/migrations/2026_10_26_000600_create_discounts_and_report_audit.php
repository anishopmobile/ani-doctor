<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->string('display_name', 100)->nullable());
        Schema::table('appointments', function (Blueprint $t): void {
            $t->unsignedBigInteger('base_price_irr')->default(0);
            $t->unsignedBigInteger('discount_irr')->default(0);
        });
        DB::table('appointments')->update(['base_price_irr' => DB::raw('price_irr')]);
        Schema::create('discounts', function (Blueprint $t): void {
            $t->id();
            $t->string('code', 32)->unique();
            $t->string('kind', 16);
            $t->unsignedBigInteger('value');
            $t->unsignedBigInteger('max_discount_irr')->nullable();
            $t->unsignedInteger('max_uses');
            $t->unsignedSmallInteger('per_patient');
            $t->timestamp('starts_at');
            $t->timestamp('ends_at');
            $t->json('service_ids');
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('discount_redemptions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('discount_id')->constrained()->restrictOnDelete();
            $t->foreignId('appointment_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('patient_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('base_irr');
            $t->unsignedBigInteger('discount_irr');
            $t->unsignedInteger('offer_version');
            $t->string('state', 16)->default('reserved');
            $t->timestamp('reserved_until');
            $t->timestamps();
            $t->index(['discount_id', 'state', 'reserved_until']);
            $t->index(['discount_id', 'patient_id', 'state']);
        });
        Schema::create('discount_events', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('redemption_id')->constrained('discount_redemptions')->restrictOnDelete();
            $t->string('action', 16);
            $t->timestamp('created_at');
            $t->unique(['redemption_id', 'action']);
        });
        Schema::create('report_access_events', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('actor_hash', 64);
            $t->string('kind', 24);
            $t->string('format', 12);
            $t->json('filters');
            $t->unsignedInteger('row_count');
            $t->timestamp('created_at');
        });
    }
    public function down(): void
    {
        foreach (['report_access_events', 'discount_events', 'discount_redemptions', 'discounts'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('appointments', fn (Blueprint $t) => $t->dropColumn(['base_price_irr', 'discount_irr']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('display_name'));
    }
};
