<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CategoryDiscountRule extends Model
{
    protected $fillable = [
        'category_id',
        'quantity',
        'discount_amount',
        'is_active',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'quantity' => 'integer',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function giftProducts(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'category_discount_rule_gifts',
            'category_discount_rule_id',
            'product_id'
        );
    }
}
