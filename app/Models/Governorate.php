<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Governorate extends Model
{
    protected $fillable = ['name_ar'];

    public function sellers(): BelongsToMany
    {
        return $this->belongsToMany(Seller::class, 'seller_governorate_coverages', 'governorate_id', 'seller_id');
    }

    public function shippingCost(): HasOne
    {
        return $this->hasOne(CityShippingCost::class);
    }
}
