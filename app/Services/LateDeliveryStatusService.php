<?php

namespace App\Services;

class LateDeliveryStatusService
{
	public function getStatusData(object $request, object $late, string $changeBy): array
	{
		return [
			'late_delivery_request_id' => $late['id'],
			'change_by' => $changeBy,
			'change_by_id' => $changeBy === 'seller' ? auth('seller')->id() : auth('admin')->id(),
			'status' => $request['late_status'],
			'message' => $request['resolved_note'] ?? $request['rejected_note'] ?? null,
		];
	}
}


