<?php

namespace App\Http\Controllers\Vendor;

use App\Contracts\Repositories\LateDeliveryRequestRepositoryInterface;
use App\Contracts\Repositories\LateDeliveryStatusRepositoryInterface;
use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Enums\ViewPaths\Vendor\LateDelivery as LateDeliveryView;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Vendor\LateDeliveryStatusRequest;
use App\Services\LateDeliveryStatusService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class LateDeliveryController extends BaseController
{
	public function __construct(
		private readonly LateDeliveryRequestRepositoryInterface $lateRequestRepo,
		private readonly LateDeliveryStatusRepositoryInterface $lateStatusRepo,
		private readonly LateDeliveryStatusService $lateStatusService,
		private readonly OrderRepositoryInterface $orderRepo,
	) {
	}

	public function index(?Request $request, ?string $type = null): View|Collection|LengthAwarePaginator|RedirectResponse|JsonResponse|callable|null
	{
		$status = $type ?? 'pending';
		$vendorId = auth('seller')->id();
		$lateList = $this->lateRequestRepo->getListWhereHas(
			orderBy: ['id' => 'desc'],
			searchValue: $request['searchValue'],
			filters: ['status' => $status],
			whereHas: 'order',
			whereHasFilters: ['seller_is' => 'seller', 'seller_id' => $vendorId],
			relations: ['order'],
			dataLimit: getWebConfig('pagination_limit'),
		);
		return view(LateDeliveryView::INDEX[VIEW], compact('lateList', 'status'));
	}

	public function updateStatus(LateDeliveryStatusRequest $request): JsonResponse
	{
		$vendorId = auth('seller')->id();
		$late = $this->lateRequestRepo->getFirstWhereHas(
			params: ['id' => $request['id']],
			whereHas: 'order',
			whereHasFilters: ['seller_is' => 'seller', 'seller_id' => $vendorId],
		);
		if (!$late) {
			return response()->json(['error' => translate('late_delivery_request_not_found')], 404);
		}
		$statusData = $this->lateStatusService->getStatusData($request, $late, 'seller');
		$this->lateStatusRepo->add($statusData);
		$update = ['status' => $request['late_status']];
		if ($request['late_status'] === 'resolved' && $request->filled('resolved_note')) {
			$update['resolved_note'] = $request['resolved_note'];
		}
		if ($request['late_status'] === 'rejected') {
			$update['rejected_note'] = $request['rejected_note'];
		}
		$this->lateRequestRepo->update($late['id'], $update);
		return response()->json(['message' => translate('status_updated_successfully')]);
	}
}


