<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CityShippingCost extends Model
{
    protected $fillable = [
        'governorate_id',
        'cost'
    ];

    protected $casts = [
        'cost' => 'decimal:2'
    ];

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }
} 