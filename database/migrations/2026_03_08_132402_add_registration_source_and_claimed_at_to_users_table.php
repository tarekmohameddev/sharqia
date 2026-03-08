<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('registration_source', 20)->nullable()->default(null)->after('referred_by');
            $table->timestamp('claimed_at')->nullable()->default(null)->after('registration_source');
            // Phone has no index today -- every phone lookup is a full table scan.
            // The unique constraint also prevents duplicate phone entries under concurrency.
            $table->unique('phone');
        });

        // Backfill: mark all existing users with an email as already claimed.
        // POS-created users have email = null, so they correctly remain unclaimed.
        DB::table('users')
            ->whereNotNull('email')
            ->whereNull('claimed_at')
            ->update([
                'registration_source' => 'web',
                'claimed_at' => DB::raw('created_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn(['registration_source', 'claimed_at']);
        });
    }
};
