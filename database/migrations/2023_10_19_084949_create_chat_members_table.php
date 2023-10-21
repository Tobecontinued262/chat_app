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
            $table->bigInteger('member_id');
            $table->tinyInteger('member_type');
            $table->enum('notification_setting', ['ON', 'OFF']);
            $table->timestamps();
            $table->primary(['chat_room_id', 'member_id', 'member_type']);
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
