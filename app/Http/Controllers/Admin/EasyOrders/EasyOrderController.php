<?php

namespace App\Http\Controllers\Admin\EasyOrders;

use App\Http\Controllers\BaseController;
use App\Models\EasyOrder;
use App\Services\EasyOrdersService;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class EasyOrderController extends BaseController
{
    public function __construct(
        private readonly EasyOrdersService $easyOrdersService,
    ) {
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse|JsonResponse
    {
        $status = $request?->get('status');

        $query = EasyOrder::query()->latest();
        if ($status) {
            $query->where('status', $status);
        }

        $easyOrders = $query->paginate(20);

        return view('admin-views.easy-orders.index', compact('easyOrders', 'status'));
    }

    public function show(int $id): View
    {
        $easyOrder = EasyOrder::findOrFail($id);

        $parsedItems = $this->easyOrdersService->parseSkuString($easyOrder->sku_string);

        return view('admin-views.easy-orders.show', compact('easyOrder', 'parsedItems'));
    }

    public function import(int $id): RedirectResponse
    {
        $easyOrder = EasyOrder::findOrFail($id);

        try {
            $this->easyOrdersService->importOrder($easyOrder);
            ToastMagic::success(translate('order_imported_successfully'));
        } catch (\Throwable $e) {
            $easyOrder->status = 'failed';
            $easyOrder->import_error = $e->getMessage();
            $easyOrder->save();
            ToastMagic::error($e->getMessage());
        }

        return back();
    }

    public function bulkImport(Request $request): RedirectResponse
    {
        $ids = (array)$request->get('ids', []);
        foreach ($ids as $id) {
            $easyOrder = EasyOrder::find($id);
            if (!$easyOrder) {
                continue;
            }
            try {
                $this->easyOrdersService->importOrder($easyOrder);
            } catch (\Throwable $e) {
                $easyOrder->status = 'failed';
                $easyOrder->import_error = $e->getMessage();
                $easyOrder->save();
            }
        }

        ToastMagic::success(translate('bulk_import_process_completed'));
        return back();
    }

    public function reject(int $id): RedirectResponse
    {
        $easyOrder = EasyOrder::findOrFail($id);
        $easyOrder->status = 'rejected';
        $easyOrder->save();

        ToastMagic::success(translate('easyorder_marked_as_rejected'));
        return back();
    }
}


