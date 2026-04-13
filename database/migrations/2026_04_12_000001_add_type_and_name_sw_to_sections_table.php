<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->string('name_sw')->nullable()->after('name'); // Swahili name
            $table->string('type')->default('مقال')->after('name_sw'); // مقال | كتب | فيديو | صوت
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn(['name_sw', 'type']);
        });
    }
};
