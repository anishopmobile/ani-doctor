<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('website_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('title', 180);
            $table->string('meta_description', 300)->nullable();
            $table->json('blocks');
            $table->unsignedInteger('version')->default(1);
            $table->json('published_snapshot')->nullable();
            $table->unsignedInteger('published_version')->nullable();
            $table->timestamps();
        });
        Schema::create('website_page_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_page_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('action', 20);
            $table->json('snapshot');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->unique(['website_page_id', 'version']);
        });
        Schema::create('website_page_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_page_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_page_assets');
        Schema::dropIfExists('website_page_revisions');
        Schema::dropIfExists('website_pages');
    }
};
