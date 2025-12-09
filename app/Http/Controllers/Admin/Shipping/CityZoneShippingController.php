<?php

namespace App\Http\Controllers\Admin\Shipping;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Admin\CityZoneShippingStoreRequest;
use App\Http\Requests\Admin\CityZoneShippingUpdateRequest;
use App\Models\CityShippingCost;
use App\Models\Governorate;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Devrabiul\ToastMagic\Facades\ToastMagic;

class CityZoneShippingController extends BaseController
{
    public function index(?Request $request = null, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse|JsonResponse
    {
        try {
            $searchValue = $request ? $request->get('searchValue') : null;
            
            $governoratesQuery = Governorate::with('shippingCost');
            
            if ($searchValue) {
                $governoratesQuery->where('name_ar', 'like', '%' . $searchValue . '%');
            }
            
            $governorates = $governoratesQuery->orderBy('name_ar')->paginate(15);
            
            return view('admin-views.shipping.city-zone-shipping.index', compact('governorates', 'searchValue'));
        } catch (\Exception $e) {
            \Log::error('City Zone Shipping Error: ' . $e->getMessage());
            ToastMagic::error('Error loading city zone shipping: ' . $e->getMessage());
            return redirect()->route('admin.dashboard.index');
        }
    }

    public function create()
    {
        try {
            $governorates = Governorate::whereDoesntHave('shippingCost')->orderBy('name_ar')->get();
            return view('admin-views.shipping.city-zone-shipping.create', compact('governorates'));
        } catch (\Exception $e) {
            \Log::error('City Zone Shipping Create Error: ' . $e->getMessage());
            ToastMagic::error('Error loading create form: ' . $e->getMessage());
            return redirect()->route('admin.shipping.city-zone-shipping.index');
        }
    }

    public function store(CityZoneShippingStoreRequest $request): RedirectResponse
    {
        try {
            CityShippingCost::create([
                'governorate_id' => $request->governorate_id,
                'cost' => $request->cost
            ]);

            ToastMagic::success(translate('city_zone_shipping_cost_added_successfully'));
            return redirect()->route('admin.shipping.city-zone-shipping.index');
        } catch (\Exception $e) {
            ToastMagic::error(translate('something_went_wrong'));
            return redirect()->back()->withInput();
        }
    }

    public function edit(CityShippingCost $cityZoneShipping)
    {
        try {
            $governorates = Governorate::orderBy('name_ar')->get();
            return view('admin-views.shipping.city-zone-shipping.edit', compact('cityZoneShipping', 'governorates'));
        } catch (\Exception $e) {
            \Log::error('City Zone Shipping Edit Error: ' . $e->getMessage());
            ToastMagic::error('Error loading edit form: ' . $e->getMessage());
            return redirect()->route('admin.shipping.city-zone-shipping.index');
        }
    }

    public function update(CityZoneShippingUpdateRequest $request, CityShippingCost $cityZoneShipping): RedirectResponse
    {
        try {
            $cityZoneShipping->update([
                'governorate_id' => $request->governorate_id,
                'cost' => $request->cost
            ]);

            ToastMagic::success(translate('city_zone_shipping_cost_updated_successfully'));
            return redirect()->route('admin.shipping.city-zone-shipping.index');
        } catch (\Exception $e) {
            ToastMagic::error(translate('something_went_wrong'));
            return redirect()->back()->withInput();
        }
    }

    public function destroy(CityShippingCost $cityZoneShipping): RedirectResponse
    {
        try {
            $cityZoneShipping->delete();
            ToastMagic::success(translate('city_zone_shipping_cost_deleted_successfully'));
            return redirect()->route('admin.shipping.city-zone-shipping.index');
        } catch (\Exception $e) {
            ToastMagic::error(translate('something_went_wrong'));
            return redirect()->back();
        }
    }

    public function show(CityShippingCost $cityZoneShipping)
    {
        try {
            $cityZoneShipping->load('governorate');
            return view('admin-views.shipping.city-zone-shipping.show', compact('cityZoneShipping'));
        } catch (\Exception $e) {
            \Log::error('City Zone Shipping Show Error: ' . $e->getMessage());
            ToastMagic::error('Error loading details: ' . $e->getMessage());
            return redirect()->route('admin.shipping.city-zone-shipping.index');
        }
    }

    public function updateStatus(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|exists:city_shipping_costs,id',
            'status' => 'required|in:0,1'
        ]);

        try {
            $cityShipping = CityShippingCost::findOrFail($request->id);
            $cityShipping->update(['status' => $request->status]);
            
            return response()->json([
                'success' => true,
                'message' => translate('status_updated_successfully')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => translate('something_went_wrong')
            ], 500);
        }
    }
}
