<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('emr_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->unique()->constrained()->restrictOnDelete();
            $table->text('identity');
            $table->string('identity_hash', 64)->nullable()->index();
            $table->string('status', 16)->default('active')->index();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('emr_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->string('kind', 32);
            $table->foreignId('appointment_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained('emr_records')->restrictOnDelete();
            $table->foreignId('episode_id')->nullable()->constrained('emr_records')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->unique()->constrained('emr_records')->restrictOnDelete();
            $table->uuid('request_key')->unique();
            $table->string('request_hash', 64);
            $table->string('status', 16)->default('draft');
            $table->longText('payload');
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['patient_id', 'kind', 'occurred_at']);
        });
        Schema::create('emr_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained('emr_records')->restrictOnDelete();
            $table->foreignId('episode_id')->nullable()->constrained('emr_records')->restrictOnDelete();
            $table->uuid('request_key')->unique();
            $table->text('metadata');
            $table->string('path')->unique();
            $table->string('mime', 64);
            $table->string('sha256', 64);
            $table->unsignedInteger('bytes');
            $table->string('status', 16)->default('pending');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['patient_id', 'created_at']);
        });
    }

    public function down(): void
    {
        // Never erase clinical history through an ordinary rollback.
        if (\Illuminate\Support\Facades\DB::table('emr_profiles')->exists()
            || \Illuminate\Support\Facades\DB::table('emr_records')->exists()
            || \Illuminate\Support\Facades\DB::table('emr_documents')->exists()) {
            throw new RuntimeException('EMR_DATA_PRESENT_USE_DOCUMENTED_CODE_ROLLBACK');
        }
        Schema::dropIfExists('emr_documents');
        Schema::dropIfExists('emr_records');
        Schema::dropIfExists('emr_profiles');
    }
};
