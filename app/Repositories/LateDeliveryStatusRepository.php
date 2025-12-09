<?php

namespace App\Repositories;

use App\Contracts\Repositories\LateDeliveryStatusRepositoryInterface;
use App\Models\LateDeliveryStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class LateDeliveryStatusRepository implements LateDeliveryStatusRepositoryInterface
{
	public function __construct(
		private readonly LateDeliveryStatus $lateDeliveryStatus,
	)
	{
	}

	public function add(array $data): string|object
	{
		return $this->lateDeliveryStatus->create($data);
	}

	public function getFirstWhere(array $params, array $relations = []): ?Model
	{
		return $this->lateDeliveryStatus->where($params)->with($relations)->first();
	}

	public function getList(array $orderBy = [], array $relations = [], int|string $dataLimit = DEFAULT_DATA_LIMIT, int $offset = null): Collection|LengthAwarePaginator
	{
		$query = $this->lateDeliveryStatus->with($relations);
		if (!empty($orderBy)) {
			$query->orderBy(key($orderBy), current($orderBy));
		}
		return $dataLimit === 'all' ? $query->get() : $query->paginate($dataLimit);
	}

	public function getListWhere(array $orderBy = [], string $searchValue = null, array $filters = [], array $relations = [], int|string $dataLimit = DEFAULT_DATA_LIMIT, int $offset = null): Collection|LengthAwarePaginator
	{
		$query = $this->lateDeliveryStatus->with($relations)
			->when(isset($filters['late_delivery_request_id']), fn($q) => $q->where('late_delivery_request_id', $filters['late_delivery_request_id']))
			->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
			->when(isset($searchValue), function ($q) use ($searchValue) {
				$key = explode(' ', $searchValue);
				$q->where(function ($sub) use ($key) {
					foreach ($key as $value) {
						$sub->orWhere('id', 'like', "%{$value}%")
							->orWhere('late_delivery_request_id', 'like', "%{$value}%")
							->orWhere('status', 'like', "%{$value}%");
					}
				});
			});
		if (!empty($orderBy)) {
			$query->orderBy(key($orderBy), current($orderBy));
		}
		$filters += ['searchValue' => $searchValue];
		return $dataLimit === 'all' ? $query->get() : $query->paginate($dataLimit)->appends($filters);
	}

	public function update(string $id, array $data): bool
	{
		return (bool) $this->lateDeliveryStatus->find($id)?->update($data);
	}

	public function delete(array $params): bool
	{
		return (bool) $this->lateDeliveryStatus->where($params)->delete();
	}
}


