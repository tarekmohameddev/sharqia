<?php

namespace App\Contracts\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface LateDeliveryRequestRepositoryInterface extends RepositoryInterface
{
	public function getListWhereHas(array $orderBy = [], string $searchValue = null, array $filters = [], string $whereHas = null, array $whereHasFilters = [], array $relations = [], int|string $dataLimit = DEFAULT_DATA_LIMIT, int $offset = null): Collection|LengthAwarePaginator;

	public function getFirstWhereHas(array $params, string $whereHas = null, array $whereHasFilters = [], array $relations = []): ?Model;
}


