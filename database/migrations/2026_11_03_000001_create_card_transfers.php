<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('card_transfer_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('enabled')->default(false);
            $table->string('card_number', 16);
            $table->string('holder', 150);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('card_transfers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('appointment_id')->unique()->constrained()->restrictOnDelete();
            $table->string('state', 32)->index();
            $table->string('card_number', 16);
            $table->string('holder', 150);
            $table->unsignedBigInteger('amount_irr');
            $table->timestamp('deadline');
            $table->string('receipt_path')->nullable();
            $table->string('receipt_hash', 64)->nullable()->index();
            $table->boolean('duplicate_receipt')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('decision', 16)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });
        foreach (['payments.card_review', 'medical.review'] as $name) {
            \Illuminate\Support\Facades\DB::table('permissions')->insertOrIgnore(['name' => $name]);
            $permission = \Illuminate\Support\Facades\DB::table('permissions')->where('name', $name)->value('id');
            foreach (\Illuminate\Support\Facades\DB::table('roles')->whereIn('name', ['admin', 'secretary'])->pluck('id') as $role) {
                \Illuminate\Support\Facades\DB::table('permission_role')->insertOrIgnore(['permission_id' => $permission, 'role_id' => $role]);
            }
        }
    }
    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('card_transfers')->exists()) {
            throw new RuntimeException('CARD_DATA_PRESENT_USE_CODE_ROLLBACK');
        }
        Schema::dropIfExists('card_transfers');
        Schema::dropIfExists('card_transfer_settings');
    }
};
