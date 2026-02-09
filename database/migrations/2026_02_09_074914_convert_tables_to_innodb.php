<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get all MyISAM tables in current database
        $myIsamTables = DB::select("
            SELECT TABLE_NAME 
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND ENGINE = 'MyISAM'
        ");

        // Convert each table to InnoDB
        foreach ($myIsamTables as $table) {
            $tableName = $table->TABLE_NAME;

            echo "Converting {$tableName} to InnoDB...\n";

            DB::statement("ALTER TABLE `{$tableName}` ENGINE=InnoDB");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversible - would require converting back to MyISAM
        // which is not recommended. Backup before running this migration!
        throw new \Exception('Cannot revert InnoDB to MyISAM. This migration is not reversible.');
    }
};
