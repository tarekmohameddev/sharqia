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
        Schema::create('easy_orders', function (Blueprint $table) {
            $table->id();
            $table->string('easyorders_id')->unique();
            $table->json('raw_payload')->nullable();

            $table->string('full_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('government')->nullable();
            $table->text('address')->nullable();

            $table->string('sku_string')->nullable();

            $table->decimal('cost', 18, 2)->default(0);
            $table->decimal('shipping_cost', 18, 2)->default(0);
            $table->decimal('total_cost', 18, 2)->default(0);

            $table->enum('status', ['pending', 'imported', 'failed', 'rejected'])->default('pending');
            $table->text('import_error')->nullable();
            $table->unsignedBigInteger('imported_order_id')->nullable();
            $table->timestamp('imported_at')->nullable();

            $table->timestamps();

            $table->foreign('imported_order_id')
                ->references('id')
                ->on('orders')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('easy_orders');
    }
};



