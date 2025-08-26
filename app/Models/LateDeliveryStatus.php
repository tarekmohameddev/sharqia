<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LateDeliveryStatus extends Model
{
	use HasFactory;

	protected $fillable = [
		'late_delivery_request_id',
		'change_by',
		'change_by_id',
		'status',
		'message',
	];

	protected $casts = [
		'late_delivery_request_id' => 'integer',
		'change_by' => 'string',
		'change_by_id' => 'integer',
		'status' => 'string',
		'message' => 'string',
	];
}


