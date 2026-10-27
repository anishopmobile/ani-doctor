<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWebsiteMediaTable extends Migration
{
    public function up(): void
    {
        Schema::create('website_media', function (Blueprint $table): void {
            $table->id();
            $table->string('owner_type', 16);
            $table->unsignedBigInteger('owner_id');
            $table->string('path', 255)->unique();
            $table->string('mime_type', 32);
            $table->string('alt_text', 180);
            $table->timestamps();
            $table->unique(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_media');
    }
}
