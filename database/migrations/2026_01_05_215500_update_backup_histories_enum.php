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
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('backup_histories')) {
            return;
        }

        // تعديل enum type لإضافة القيم الجديدة
        DB::statement("ALTER TABLE backup_histories MODIFY COLUMN type ENUM('create', 'restore', 'clean', 'monitor', 'upload', 'delete', 'queued')");

        // تعديل enum status لإضافة queued
        DB::statement("ALTER TABLE backup_histories MODIFY COLUMN status ENUM('started', 'success', 'failed', 'queued')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('backup_histories')) {
            return;
        }

        // إرجاع القيم الأصلية
        DB::statement("ALTER TABLE backup_histories MODIFY COLUMN type ENUM('create', 'restore', 'clean', 'monitor')");
        DB::statement("ALTER TABLE backup_histories MODIFY COLUMN status ENUM('started', 'success', 'failed')");
    }
};
