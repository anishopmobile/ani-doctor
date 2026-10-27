<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWebsiteLinksTable extends Migration
{
    public function up(): void
    {
        Schema::create('website_links', function (Blueprint $table): void {
            $table->id();
            $table->string('placement', 12);
            $table->string('label', 100);
            $table->string('url', 500);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->boolean('new_tab')->default(false);
            $table->timestamps();
            $table->index(['placement', 'is_published', 'sort_order'], 'website_links_public_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_links');
    }
}
