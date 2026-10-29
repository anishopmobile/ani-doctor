<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('weekly_announcements', function (Blueprint $table): void {
            $table->id();
            $table->date('date')->unique();
            $table->json('source_snapshot')->nullable();
            $table->json('presence_override')->nullable();
            $table->string('location', 200)->nullable();
            $table->boolean('outside_office')->default(false);
            $table->text('notes')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_announcements');
    }
};
