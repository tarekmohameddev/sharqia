<?php

namespace App\Repositories;

use App\Contracts\Repositories\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class BasicRepository implements RepositoryInterface
{
    public function __construct(protected Model $model)
    {
    }

    public function getList(array $orderBy = [], array $relations = [], int|string $dataLimit = DEFAULT_DATA_LIMIT, int $offset = null): Collection|LengthAwarePaginator
    {
        $query = $this->model->with($relations)->orderBy(key($orderBy), current($orderBy));
        return $dataLimit == 'all' ? $query->get() : $query->paginate($dataLimit);
    }

    public function getListWhere(array $orderBy = [], string $searchValue = null, array $filters = [], array $relations = [], int|string $dataLimit = DEFAULT_DATA_LIMIT, int $offset = null): Collection|LengthAwarePaginator
    {
        $query = $this->model->when($searchValue, function ($query) use ($searchValue) {
            $query->where('id', 'like', "%{$searchValue}%");
        })
        ->when(!empty($filters), function ($query) use ($filters) {
            $query->where($filters);
        })
        ->with($relations)
        ->orderBy(key($orderBy), current($orderBy));

        return $dataLimit == 'all' ? $query->get() : $query->paginate($dataLimit);
    }

    public function getFirstWhere(array $params, array $relations = []): ?Model
    {
        return $this->model->with($relations)->where($params)->first();
    }

    public function add(array $data): Model
    {
        return $this->model->create($data);
    }

    public function update(string|int $id, array $data): bool
    {
        return $this->model->find($id)->update($data);
    }

    public function delete(array $params): bool
    {
        return $this->model->where($params)->delete();
    }
}
