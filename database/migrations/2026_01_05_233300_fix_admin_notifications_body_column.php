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
        Schema::table('admin_notifications', function (Blueprint $table) {
            // If 'body' exists but 'message' doesn't, rename it
            if (Schema::hasColumn('admin_notifications', 'body') && ! Schema::hasColumn('admin_notifications', 'message')) {
                $table->renameColumn('body', 'message');
            }

            // Make 'body' nullable if it still exists
            if (DB::getDriverName() === 'mysql' && Schema::hasColumn('admin_notifications', 'body')) {
                DB::statement('ALTER TABLE admin_notifications MODIFY body TEXT NULL');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('admin_notifications', 'message') && ! Schema::hasColumn('admin_notifications', 'body')) {
                $table->renameColumn('message', 'body');
            }
        });
    }
};
