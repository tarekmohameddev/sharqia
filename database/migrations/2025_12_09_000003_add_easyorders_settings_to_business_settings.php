<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        if (!Schema::hasTable('business_settings')) {
            return;
        }

        DB::table('business_settings')->updateOrInsert(
            ['type' => 'easyorders_auto_import'],
            [
                'value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('business_settings')->updateOrInsert(
            ['type' => 'easyorders_webhook_secret'],
            [
                'value' => 'ddd',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        if (!Schema::hasTable('business_settings')) {
            return;
        }

        DB::table('business_settings')->whereIn('type', [
            'easyorders_auto_import',
            'easyorders_webhook_secret',
        ])->delete();
    }
};


