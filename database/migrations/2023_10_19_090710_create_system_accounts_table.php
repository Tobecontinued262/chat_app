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
        Schema::create('system_accounts', function (Blueprint $table) {
            $table->id('user_id');
            $table->string('cognito_map_id');
            $table->string('email');
            $table->string('password');
            $table->string('name');
            $table->string('display_name');
            $table->string('role');
            //new
            $table->bigInteger('connection_id');
            $table->enum('user_status', ['Offline', 'Online']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_accounts');
    }
};
