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
        Schema::create('weapons', function (Blueprint $table) {
            $table->string('code', 64)->primary();
            $table->string('game_code', 32);
            $table->string('name', 128);
            $table->decimal('modifier', 5, 2)->default(1.00);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            // Indexes
            $table->index('game_code');
            $table->index('modifier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weapons');
    }
};
