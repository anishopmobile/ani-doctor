<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWebsiteThemeSettingsTable extends Migration
{
    public function up(): void
    {
        Schema::create('website_theme_settings', function (Blueprint $table): void {
            $table->id();
            $table->char('primary_color', 7)->default('#ce4081');
            $table->char('text_color', 7)->default('#362147');
            $table->char('surface_color', 7)->default('#fffafc');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_theme_settings');
    }
}
