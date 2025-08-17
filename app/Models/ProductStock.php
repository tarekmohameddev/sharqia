<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductStock extends Model
{
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class, 'variant_id');
    }
}
