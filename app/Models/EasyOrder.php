<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EasyOrder extends Model
{
    protected $fillable = [
        'easyorders_id',
        'raw_payload',
        'full_name',
        'phone',
        'government',
        'address',
        'sku_string',
        'cost',
        'shipping_cost',
        'total_cost',
        'status',
        'import_error',
        'imported_order_id',
        'imported_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'cost' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'imported_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'imported_order_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeImported($query)
    {
        return $query->where('status', 'imported');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}



