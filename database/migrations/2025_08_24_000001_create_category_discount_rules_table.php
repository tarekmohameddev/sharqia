<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_discount_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->integer('quantity')->comment('Required quantity for this rule');
            $table->decimal('discount_amount', 8, 2)->default(0);
            $table->unsignedBigInteger('gift_product_id')->nullable()->comment('Optional gift product for this rule');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->foreign('gift_product_id')->references('id')->on('products')->onDelete('set null');
            $table->index(['category_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_discount_rules');
    }
};


