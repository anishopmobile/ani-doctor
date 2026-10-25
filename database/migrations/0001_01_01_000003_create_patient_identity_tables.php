<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('contact_mobile', 11)->nullable()->index();
            $table->string('file_number', 64)->nullable()->unique();
            $table->timestamps();
        });
        Schema::create('patient_user_links', function (Blueprint $table): void {
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('created_at');
            $table->primary(['patient_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_user_links');
        Schema::dropIfExists('patients');
    }
};
