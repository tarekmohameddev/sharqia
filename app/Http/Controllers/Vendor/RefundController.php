<?php

namespace App\Http\Controllers\Vendor;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Contracts\Repositories\OrderDetailRepositoryInterface;
use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Contracts\Repositories\OrderRefundRepositoryInterface;
use App\Contracts\Repositories\RefundStatusRepositoryInterface;
use App\Contracts\Repositories\VendorRepositoryInterface;
use App\Enums\ExportFileNames\Admin\RefundRequest as RefundRequestExportFile;
use App\Enums\ViewPaths\Vendor\Refund;
use App\Events\RefundEvent;
use App\Exports\RefundRequestExport;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Vendor\RefundStatusRequest;
use App\Repositories\VendorRepository;
use App\Services\RefundStatusService;
use App\Traits\CustomerTrait;
use Devrabiul\ToastMagic\Facades\ToastMagic;
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
        private readonly OrderRefundRepositoryInterface  $refundRequestRepo,
        private readonly CustomerRepositoryInterface      $customerRepo,
        private readonly OrderDetailRepositoryInterface   $orderDetailRepo,
        private readonly RefundStatusRepositoryInterface  $refundStatusRepo,
        private readonly RefundStatusService              $refundStatusService,
        private readonly OrderRepositoryInterface         $orderRepo,
        private readonly VendorRepositoryInterface        $vendorRepo,

    )
    {

    }

    /**
     * @param Request|null $request
     * @param string|null $type
     * @return View|Collection|LengthAwarePaginator|callable|RedirectResponse|null
     */
    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        return $this->getList(request: $request, status: $type);
    }

    /**
     * @param object $request
     * @param string $status
     * @return View
     */
    public function getList(object $request, string $status): View
    {
        $vendorId = auth('seller')->id();
        $searchValue = $request['search'] ?? null;
        
        // We need to create a custom query to filter by vendor orders
        $refundQuery = \App\Models\OrderRefund::query()
            ->whereHas('order', function($query) use ($vendorId) {
                $query->where('seller_is', 'seller')
                      ->where('seller_id', $vendorId);
            })
            ->with(['order', 'order.seller', 'customer'])
            ->orderBy('id', 'desc');
            
        if ($status && $status !== 'all') {
            $refundQuery->where('status', $status);
        }
        
        if ($searchValue) {
            $refundQuery->where('id', 'like', "%{$searchValue}%");
        }
        
        $refundList = $refundQuery->paginate(getWebConfig('pagination_limit'));
        
        return view('vendor-views.refund.index', compact('refundList', 'searchValue'));
    }



    public function exportList(Request $request, $status): BinaryFileResponse
    {
        $vendorId = auth('seller')->id();
        $vendor = $this->vendorRepo->getFirstWhere(params: ['id' => $vendorId]);
        
        // Create custom query for export
        $refundQuery = \App\Models\OrderRefund::query()
            ->whereHas('order', function($query) use ($vendorId) {
                $query->where('seller_is', 'seller')
                      ->where('seller_id', $vendorId);
            })
            ->with(['order', 'order.seller', 'customer'])
            ->orderBy('id', 'desc');
            
        if ($status && $status !== 'all') {
            $refundQuery->where('status', $status);
        }
        
        if ($request['search']) {
            $refundQuery->where('id', 'like', "%{$request['search']}%");
        }
        
        $refundList = $refundQuery->get();
        
        return Excel::download(new RefundRequestExport([
            'data-from' => 'vendor',
            'vendor' => $vendor,
            'refundList' => $refundList,
            'search' => $request['search'],
            'status' => $status,
            'filter_By' => $request->get('type', 'all'),
        ]), RefundRequestExportFile::EXPORT_XLSX);
    }
}
