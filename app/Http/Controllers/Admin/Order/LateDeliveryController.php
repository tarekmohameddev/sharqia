<?php

namespace App\Http\Controllers\Admin\Order;

use App\Contracts\Repositories\LateDeliveryRequestRepositoryInterface;
use App\Contracts\Repositories\LateDeliveryStatusRepositoryInterface;
use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Enums\ViewPaths\Admin\LateDelivery as LateDeliveryView;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Admin\LateDeliveryStatusRequest;
use App\Models\Order;
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
		$lateList = $this->lateRequestRepo->getListWhereHas(
			orderBy: ['id' => 'desc'],
			searchValue: $request['searchValue'],
			filters: ['status' => $status],
			whereHas: 'order',
			whereHasFilters: ['seller_is' => $request['type']],
			relations: ['order', 'order.seller', 'order.deliveryMan', 'customer'],
			dataLimit: getWebConfig('pagination_limit'),
		);
		return view(LateDeliveryView::LIST[VIEW], compact('lateList', 'status'));
	}

	public function flag(Request $request, int $orderId): JsonResponse
	{
		$order = $this->orderRepo->getFirstWhere(['id' => $orderId]);
		if (!$order) {
			return response()->json(['error' => translate('order_not_found')], 404);
		}
		$existing = $this->lateRequestRepo->getFirstWhere(['order_id' => $orderId]);
		if ($existing) {
			return response()->json(['message' => translate('already_flagged_as_late')], 200);
		}
		$this->lateRequestRepo->add([
			'order_id' => $orderId,
			'customer_id' => $order['customer_id'] ?? null,
			'status' => 'pending',
			'change_by' => 'admin',
		]);
		return response()->json(['message' => translate('late_delivery_request_created')]);
	}

	public function updateStatus(LateDeliveryStatusRequest $request): JsonResponse
	{
		$late = $this->lateRequestRepo->getFirstWhere(['id' => $request['id']]);
		if (!$late) {
			return response()->json(['error' => translate('late_delivery_request_not_found')], 404);
		}
		$statusData = $this->lateStatusService->getStatusData($request, $late, 'admin');
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


