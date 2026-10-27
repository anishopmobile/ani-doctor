<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('website_sections', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 24);
            $table->string('title', 180);
            $table->string('subtitle', 240)->nullable();
            $table->text('body')->nullable();
            $table->string('button_text', 80)->nullable();
            $table->string('button_url', 255)->nullable();
            $table->string('background', 20)->default('plain');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->index(['is_published', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_sections');
    }
};
