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
		Schema::create('late_delivery_statuses', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('late_delivery_request_id');
			$table->string('change_by')->nullable();
			$table->unsignedBigInteger('change_by_id')->nullable();
			$table->string('status');
			$table->longText('message')->nullable();
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('late_delivery_statuses');
	}
};


