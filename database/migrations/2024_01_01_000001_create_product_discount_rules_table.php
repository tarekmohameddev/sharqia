<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_discount_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity')->comment('Required quantity for this rule');
            $table->decimal('discount_amount', 8, 2)->default(0);
            $table->enum('discount_type', ['flat', 'percent'])->default('flat');
            $table->unsignedBigInteger('gift_product_id')->nullable()->comment('Optional gift product for this rule');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('gift_product_id')->references('id')->on('products')->onDelete('set null');
            
            $table->index(['product_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_discount_rules');
    }
}; 