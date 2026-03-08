<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuthenticityCodeBatch extends Model
{
    protected $fillable = [
        'batch_number',
        'total_count',
        'generated_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'total_count' => 'integer',
    ];

    public function codes(): HasMany
    {
        return $this->hasMany(AuthenticityCode::class, 'batch_id');
    }

    public function usedCount(): int
    {
        return $this->codes()->where('status', 'used')->count();
    }

    public function unusedCount(): int
    {
        return $this->codes()->where('status', 'unused')->count();
    }
}
