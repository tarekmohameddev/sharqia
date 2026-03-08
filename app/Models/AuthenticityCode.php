<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AuthenticityCode extends Model
{
    protected $fillable = [
        'batch_id',
        'code',
        'status',
        'first_scanned_at',
        'first_scanned_by_user_id',
        'first_scanned_ip',
    ];

    protected $casts = [
        'first_scanned_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AuthenticityCodeBatch::class, 'batch_id');
    }

    public function firstScannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'first_scanned_by_user_id');
    }

    public function scanLogs(): HasMany
    {
        return $this->hasMany(AuthenticityScanLog::class, 'code_id');
    }

    public function counterfeitReports(): HasMany
    {
        return $this->hasMany(AuthenticityCounterfeitReport::class, 'code_id');
    }

    public function scopeUnused($query)
    {
        return $query->where('status', 'unused');
    }

    public function scopeUsed($query)
    {
        return $query->where('status', 'used');
    }
}
