<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWebsiteArticleTaxonomiesTable extends Migration
{
    public function up(): void
    {
        Schema::create('website_taxonomies', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 12);
            $table->string('slug', 120);
            $table->string('name', 120);
            $table->timestamps();
            $table->unique(['type', 'slug']);
        });

        Schema::create('website_article_taxonomy', function (Blueprint $table): void {
            $table->foreignId('website_article_id')->constrained('website_articles')->cascadeOnDelete();
            $table->foreignId('website_taxonomy_id')->constrained('website_taxonomies')->cascadeOnDelete();
            $table->primary(['website_article_id', 'website_taxonomy_id'], 'article_taxonomy_pk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_article_taxonomy');
        Schema::dropIfExists('website_taxonomies');
    }
}
