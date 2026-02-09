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
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('game_code', 32);
            $table->string('name', 128);
            $table->string('address', 64);
            $table->integer('port')->default(27015);
            $table->string('public_address', 64)->nullable();
            $table->boolean('enabled')->default(true);
            $table->string('map', 64)->nullable();
            $table->timestamp('last_activity')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('game_code');
            $table->index('enabled');
            $table->index('last_activity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
