<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_discount_rule_gifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_discount_rule_id');
            $table->unsignedBigInteger('product_id');

            $table->unique(['category_discount_rule_id', 'product_id'], 'cdrg_rule_product_unique');
            $table->index('category_discount_rule_id', 'cdrg_rule_id_index');
            $table->index('product_id', 'cdrg_product_id_index');
        });

        // Migrate existing single gift_product_id values into the new pivot table
        if (Schema::hasColumn('category_discount_rules', 'gift_product_id')) {
            DB::table('category_discount_rules')
                ->whereNotNull('gift_product_id')
                ->orderBy('id')
                ->each(function ($rule) {
                    DB::table('category_discount_rule_gifts')->insertOrIgnore([
                        'category_discount_rule_id' => $rule->id,
                        'product_id'                => $rule->gift_product_id,
                    ]);
                });

            Schema::table('category_discount_rules', function (Blueprint $table) {
                $table->dropColumn('gift_product_id');
            });
        }
    }

    public function down(): void
    {
        // Re-add the column and restore the first gift per rule
        if (!Schema::hasColumn('category_discount_rules', 'gift_product_id')) {
            Schema::table('category_discount_rules', function (Blueprint $table) {
                $table->unsignedBigInteger('gift_product_id')->nullable()->after('discount_amount');
            });

            // Restore first gift per rule
            DB::table('category_discount_rule_gifts')
                ->select('category_discount_rule_id', DB::raw('MIN(product_id) as product_id'))
                ->groupBy('category_discount_rule_id')
                ->orderBy('category_discount_rule_id')
                ->each(function ($row) {
                    DB::table('category_discount_rules')
                        ->where('id', $row->category_discount_rule_id)
                        ->update(['gift_product_id' => $row->product_id]);
                });
        }

        Schema::dropIfExists('category_discount_rule_gifts');
    }
};
