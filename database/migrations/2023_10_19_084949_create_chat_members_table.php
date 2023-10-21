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
        Schema::create('chat_members', function (Blueprint $table) {
            $table->bigInteger('chat_room_id');
            $table->bigInteger('user_id');
            $table->bigInteger('admin_id');
            $table->enum('notification_setting', ['ON', 'OFF']);
            $table->timestamps();
            $table->primary(['chat_room_id', 'user_id', 'admin_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_members');
    }
};
