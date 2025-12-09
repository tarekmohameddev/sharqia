<?php

namespace App\Repositories;

use App\Contracts\Repositories\OrderRefundRepositoryInterface;
use App\Models\OrderRefund;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderRefundRepository extends BasicRepository implements OrderRefundRepositoryInterface
{
    public function __construct(OrderRefund $model)
    {
        parent::__construct($model);
    }
}
