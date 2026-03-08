<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('authenticity_code_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number')->unique();
            $table->integer('total_count');
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('authenticity_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id');
            $table->string('code', 20)->unique();
            $table->enum('status', ['unused', 'used'])->default('unused');
            $table->timestamp('first_scanned_at')->nullable();
            $table->unsignedBigInteger('first_scanned_by_user_id')->nullable();
            $table->string('first_scanned_ip', 45)->nullable();
            $table->timestamps();

            $table->foreign('batch_id')->references('id')->on('authenticity_code_batches')->onDelete('cascade');
            $table->foreign('first_scanned_by_user_id')->references('id')->on('users')->onDelete('set null');

            $table->index('batch_id');
            $table->index('status');
        });

        Schema::create('authenticity_scan_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('code_id')->nullable();
            $table->string('code_entered', 50);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->enum('result', ['valid_first', 'valid_rescan', 'invalid', 'rate_limited', 'blocked']);
            $table->string('ip_address', 45);
            $table->string('user_agent', 500)->nullable();
            $table->string('device_id', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('code_id')->references('id')->on('authenticity_codes')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            $table->index('code_id');
            $table->index('user_id');
            $table->index('ip_address');
            $table->index('result');
            $table->index('created_at');
        });

        Schema::create('authenticity_counterfeit_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('code_id');
            $table->unsignedBigInteger('user_id');
            $table->text('notes')->nullable();
            $table->timestamp('reported_at')->useCurrent();
            $table->timestamp('admin_reviewed_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->foreign('code_id')->references('id')->on('authenticity_codes')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->index('code_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authenticity_counterfeit_reports');
        Schema::dropIfExists('authenticity_scan_logs');
        Schema::dropIfExists('authenticity_codes');
        Schema::dropIfExists('authenticity_code_batches');
    }
};
