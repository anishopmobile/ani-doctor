<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('website_articles', function (Blueprint $table): void {
            $table->string('author_name', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('website_articles', function (Blueprint $table): void {
            $table->dropColumn('author_name');
        });
    }
};
