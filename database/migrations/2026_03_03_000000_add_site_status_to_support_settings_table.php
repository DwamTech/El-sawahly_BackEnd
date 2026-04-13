<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Insert site_status setting if not exists
        if (!DB::table('support_settings')->where('key', 'site_status')->exists()) {
            DB::table('support_settings')->insert([
                'key' => 'site_status',
                'value' => 'open', // open or closed
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Delete site_status setting
        DB::table('support_settings')->where('key', 'site_status')->delete();
    }
};
