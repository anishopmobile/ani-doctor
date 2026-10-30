<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('website_contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('submission_hash', 64)->unique();
            $table->text('sender_name');
            $table->text('phone');
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_contact_messages');
    }
};
