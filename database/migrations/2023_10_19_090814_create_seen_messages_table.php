<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('seen_messages', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('chat_message_id');
            $table->bigInteger('seen_by_admin_id');
            $table->bigInteger('seen_by_user_id');
            $table->dateTime('seen_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seen_messages');
    }
};
