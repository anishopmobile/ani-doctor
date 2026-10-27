<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('website_theme_settings', function (Blueprint $table): void {
            $table->json('brand_settings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('website_theme_settings', function (Blueprint $table): void {
            $table->dropColumn('brand_settings');
        });
    }
};
