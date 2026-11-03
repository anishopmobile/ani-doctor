<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('bale_links', function (Blueprint $table): void {
            $table->string('bale_id', 32)->primary();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('bale_outbox', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 150)->unique();
            $table->string('bale_id', 32);
            $table->text('message');
            $table->string('state', 20)->default('queued');
            $table->timestamps();
        });
        Schema::create('medical_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('ingestion_key', 100)->nullable()->unique();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->string('path');
            $table->string('mime', 100);
            $table->string('state', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('response')->nullable();
            $table->timestamps();
        });
        Schema::create('bale_poll_state', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('offset')->default(0);
        });
        \Illuminate\Support\Facades\DB::table('bale_poll_state')->insert(['id' => 1]);
    }
    public function down(): void
    {
        foreach (['bale_poll_state', 'medical_documents', 'bale_outbox', 'bale_links'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
