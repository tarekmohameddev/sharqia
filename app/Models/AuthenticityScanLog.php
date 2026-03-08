<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthenticityScanLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code_id',
        'code_entered',
        'user_id',
        'result',
        'ip_address',
        'user_agent',
        'device_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function code(): BelongsTo
    {
        return $this->belongsTo(AuthenticityCode::class, 'code_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
