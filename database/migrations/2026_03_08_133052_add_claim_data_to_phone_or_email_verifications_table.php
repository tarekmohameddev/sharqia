<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phone_or_email_verifications', function (Blueprint $table) {
            // Stores JSON payload (f_name, l_name, email, password) for the claim-account flow.
            $table->text('claim_data')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('phone_or_email_verifications', function (Blueprint $table) {
            $table->dropColumn('claim_data');
        });
    }
};
