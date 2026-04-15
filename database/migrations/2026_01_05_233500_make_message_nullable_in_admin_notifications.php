<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Make message column nullable if it exists
        if (DB::getDriverName() === 'mysql' && Schema::hasColumn('admin_notifications', 'message')) {
            DB::statement('ALTER TABLE admin_notifications MODIFY message TEXT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // لا نغير شيء
    }
};
