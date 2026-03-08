<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthenticityCounterfeitReport extends Model
{
    protected $fillable = [
        'code_id',
        'user_id',
        'notes',
        'reported_at',
        'admin_reviewed_at',
        'admin_notes',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'admin_reviewed_at' => 'datetime',
    ];

    public function code(): BelongsTo
    {
        return $this->belongsTo(AuthenticityCode::class, 'code_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeUnreviewed($query)
    {
        return $query->whereNull('admin_reviewed_at');
    }

    public function scopeReviewed($query)
    {
        return $query->whereNotNull('admin_reviewed_at');
    }
}
