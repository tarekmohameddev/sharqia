<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDiscountRule extends Model
{
    protected $fillable = [
        'product_id',
        'quantity',
        'discount_amount',
        'discount_type',
        'gift_product_id',
        'is_active'
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'quantity' => 'integer',
        'is_active' => 'boolean'
    ];

    /**
     * Get the product that owns this discount rule
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get the gift product for this rule
     */
    public function giftProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'gift_product_id');
    }

    /**
     * Scope for active rules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for rules by product
     */
    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Calculate discount amount for given price
     */
    public function calculateDiscount($basePrice): float
    {
        if ($this->discount_type === 'percent') {
            return ($basePrice * $this->discount_amount) / 100;
        }
        
        return $this->discount_amount;
    }

    /**
     * Get formatted discount display
     */
    public function getDiscountDisplayAttribute(): string
    {
        if ($this->discount_type === 'percent') {
            return $this->discount_amount . '%';
        }
        
        return getCurrencySymbol() . $this->discount_amount;
    }
} 