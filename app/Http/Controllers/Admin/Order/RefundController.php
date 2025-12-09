<?php

namespace App\Http\Controllers\Admin\Order;

use App\Contracts\Repositories\AdminWalletRepositoryInterface;
use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Contracts\Repositories\LoyaltyPointTransactionRepositoryInterface;
use App\Contracts\Repositories\OrderDetailRepositoryInterface;
use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Contracts\Repositories\OrderRefundRepositoryInterface;
use App\Contracts\Repositories\RefundStatusRepositoryInterface;
use App\Contracts\Repositories\RefundTransactionRepositoryInterface;
use App\Contracts\Repositories\VendorWalletRepositoryInterface;
use App\Enums\ExportFileNames\Admin\RefundRequest as RefundRequestExportFile;
use App\Events\RefundEvent;
use App\Exports\RefundRequestExport;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Admin\RefundStatusRequest;
use App\Services\RefundStatusService;
use App\Services\RefundTransactionService;
use App\Traits\CustomerTrait;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RefundController extends BaseController
{
    use CustomerTrait;

    public function __construct(
        private readonly OrderRefundRepositoryInterface           $refundRequestRepo,
        private readonly CustomerRepositoryInterface                $customerRepo,
        private readonly OrderRepositoryInterface                   $orderRepo,
        private readonly OrderDetailRepositoryInterface             $orderDetailRepo,
        private readonly AdminWalletRepositoryInterface             $adminWalletRepo,
        private readonly VendorWalletRepositoryInterface            $vendorWalletRepo,
        private readonly RefundStatusRepositoryInterface            $refundStatusRepos,
        private readonly RefundTransactionRepositoryInterface       $refundTransactionRepo,
        private readonly LoyaltyPointTransactionRepositoryInterface $loyaltyPointTransactionRepo
    )
    {
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $status = $type;
        $refundList = $this->refundRequestRepo->getListWhere(
            orderBy: ['id' => 'desc'],
            searchValue: $request['searchValue'],
            filters: ['status' => $status],
            relations: ['order', 'order.seller', 'order.deliveryMan', 'customer'],
            dataLimit: getWebConfig('pagination_limit'),
        );
        return view('admin-views.refund.list', compact('refundList', 'status'));
    }

    public function exportList(Request $request, $status): BinaryFileResponse
    {
        $refundList = $this->refundRequestRepo->getListWhereHas(
            orderBy: ['id' => 'desc'],
            searchValue: $request['searchValue'],
            filters: ['status' => $status],
            whereHas: 'order',
            whereHasFilters: ['seller_is' => $request['type']],
            relations: ['order', 'order.seller', 'order.deliveryMan', 'product'],
            dataLimit: 'all',
        );
        return Excel::download(new RefundRequestExport([
            'data-from' => 'admin',
            'refundList' => $refundList,
            'search' => $request['searchValue'],
            'status' => $status,
            'filter_By' => $request->get('type', 'all'),
        ]), RefundRequestExportFile::EXPORT_XLSX);
    }

}
