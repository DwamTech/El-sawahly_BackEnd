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
        // Tables that already have section_id but need to be nullable
        $existingTables = ['articles', 'audios', 'visuals', 'galleries', 'links'];
        foreach ($existingTables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'section_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('section_id')->nullable()->change();
                });
            }
        }

        // Tables that need section_id added
        $newTables = ['books', 'documents'];
        foreach ($newTables as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'section_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('section_id')->nullable()->constrained('sections')->nullOnDelete();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We cannot easily revert "nullable" change without knowing previous state (usually not nullable)
        // But we can drop the column from new tables
        $newTables = ['books', 'documents'];
        foreach ($newTables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'section_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign(['section_id']);
                    $table->dropColumn('section_id');
                });
            }
        }
        
        // For existing tables, we might want to revert to not nullable, but data might exist with nulls now.
        // So we skip reverting the nullable change to avoid errors.
    }
};
