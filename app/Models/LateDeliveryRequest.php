<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LateDeliveryRequest extends Model
{
	use HasFactory;

	protected $fillable = [
		'order_id',
		'customer_id',
		'status',
		'resolved_note',
		'rejected_note',
		'change_by',
	];

	protected $casts = [
		'order_id' => 'integer',
		'customer_id' => 'integer',
		'status' => 'string',
		'resolved_note' => 'string',
		'rejected_note' => 'string',
		'change_by' => 'string',
	];

	public function order(): BelongsTo
	{
		return $this->belongsTo(Order::class, 'order_id');
	}

	public function customer(): BelongsTo
	{
		return $this->belongsTo(User::class, 'customer_id');
	}

	public function statusHistory(): HasMany
	{
		return $this->hasMany(LateDeliveryStatus::class, 'late_delivery_request_id');
	}
}


