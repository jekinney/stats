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
        Schema::create('event_frags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('killer_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('victim_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('server_id')->nullable()->constrained('servers')->onDelete('cascade');
            $table->string('weapon_code', 64);
            $table->foreign('weapon_code')->references('code')->on('weapons')->onDelete('cascade');
            $table->boolean('headshot')->default(false);
            $table->string('map', 64);
            $table->timestamp('event_time');
            $table->mediumInteger('pos_x')->nullable();
            $table->mediumInteger('pos_y')->nullable();
            $table->mediumInteger('pos_z')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['killer_id', 'event_time']);
            $table->index(['victim_id', 'event_time']);
            $table->index(['server_id', 'event_time']);
            $table->index(['weapon_code', 'event_time']);
            $table->index(['map', 'event_time']);
            $table->index('headshot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_frags');
    }
};
