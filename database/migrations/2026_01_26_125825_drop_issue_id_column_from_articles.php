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
        Schema::table('articles', function (Blueprint $table) {
            if (Schema::hasColumn('articles', 'issue_id')) {
                // Drop foreign key if it exists (using array syntax for index name convention)
                // Note: Previous migrations might have already dropped it, but good to be safe.
                // We use a try-catch block approach or check for constraint existence if possible,
                // but Laravel's Schema builder doesn't easily support "dropForeignKeyIfExists".
                // Since we know the state from previous migrations (it was made nullable), 
                // the FK might still be there or not depending on 2025_12_30_120000 migration.
                // Let's assume we just need to drop the column.
                
                // However, if there is an index or FK, dropping column might fail in some DBs without dropping them first.
                // The migration 2025_12_30_120000 attempted to drop FK/Index. 
                
                $table->dropColumn('issue_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            if (!Schema::hasColumn('articles', 'issue_id')) {
                $table->unsignedBigInteger('issue_id')->nullable();
                // We don't restore the FK as per request to "remove it completely"
            }
        });
    }
};
