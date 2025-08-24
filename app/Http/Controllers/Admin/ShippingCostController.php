<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\CityShippingCost;
use App\Models\Governorate;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Devrabiul\ToastMagic\Facades\ToastMagic;

class ShippingCostController extends BaseController
{
    public function index(?Request $request = null, ?string $type = null): View|Collection|LengthAwarePaginator|RedirectResponse|JsonResponse|callable|null
    {
        $governorates = Governorate::with('shippingCost')->orderBy('name_ar')->get();
        return view('admin-views.shipping-cost.index', compact('governorates'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'governorate_id' => 'required|exists:governorates,id',
            'cost' => 'required|numeric|min:0',
        ]);

        CityShippingCost::updateOrCreate(
            ['governorate_id' => $request->governorate_id],
            ['cost' => $request->cost]
        );

        ToastMagic::success(translate('shipping_cost_updated_successfully'));
        return redirect()->back();
    }

    public function update(Request $request, CityShippingCost $shippingCost): RedirectResponse
    {
        $request->validate([
            'cost' => 'required|numeric|min:0',
        ]);

        $shippingCost->update(['cost' => $request->cost]);

        ToastMagic::success(translate('shipping_cost_updated_successfully'));
        return redirect()->back();
    }

    public function destroy(CityShippingCost $shippingCost): RedirectResponse
    {
        $shippingCost->delete();
        ToastMagic::success(translate('shipping_cost_deleted_successfully'));
        return redirect()->back();
    }
} 