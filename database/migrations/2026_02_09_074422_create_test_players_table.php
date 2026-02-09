<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create table with MyISAM engine (simulating legacy HLstatsX schema)
        Schema::create('test_players', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('kills')->default(0);
            $table->integer('deaths')->default(0);
            $table->decimal('skill', 10, 2)->default(1000.00);
            $table->timestamps();
        });

        // Force MyISAM engine (the legacy way from HLstatsX)
        DB::statement('ALTER TABLE test_players ENGINE=MyISAM');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_players');
    }
};
