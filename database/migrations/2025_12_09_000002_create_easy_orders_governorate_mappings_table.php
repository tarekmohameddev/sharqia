<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        // Create table only if it does not already exist (handles partially-created tables)
        if (!Schema::hasTable('easy_orders_governorate_mappings')) {
            Schema::create('easy_orders_governorate_mappings', function (Blueprint $table) {
                $table->id();
                $table->string('easyorders_name');
                $table->unsignedBigInteger('governorate_id');
                $table->timestamps();

                $table->unique(['easyorders_name']);
            });
        }

        // Add foreign key only if the referenced table exists, and ignore if it's already there
        if (Schema::hasTable('governorates') && Schema::hasTable('easy_orders_governorate_mappings')) {
            try {
                Schema::table('easy_orders_governorate_mappings', function (Blueprint $table) {
                    $table->foreign('governorate_id')
                        ->references('id')
                        ->on('governorates')
                        ->onDelete('cascade');
                });
            } catch (\Throwable $e) {
                // If the foreign key or index already exists, safely ignore the error
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('easy_orders_governorate_mappings');
    }
};



