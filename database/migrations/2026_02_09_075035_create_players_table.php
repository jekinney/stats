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
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->string('game_code', 32)->index();
            $table->string('steam_id', 64)->unique();
            $table->string('last_name', 128);
            $table->decimal('skill', 10, 2)->default(1000.00);
            $table->integer('kills')->default(0);
            $table->integer('deaths')->default(0);
            $table->integer('headshots')->default(0);
            $table->boolean('hide_ranking')->default(false);
            $table->integer('connection_time')->default(0);
            $table->timestamp('last_event')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['game_code', 'skill', 'hide_ranking'], 'players_game_skill_hide_ranking_index');
            $table->index(['skill', 'kills'], 'players_skill_kills_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
