<?php

namespace App\Http\Controllers\Admin\EasyOrders;

use App\Http\Controllers\BaseController;
use App\Models\EasyOrdersGovernorateMapping;
use App\Models\Governorate;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class EasyOrdersGovernorateMappingController extends BaseController
{
    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse|JsonResponse
    {
        $status = null; // reserved for future filters if needed

        $editId = $request?->get('edit');
        $editMapping = null;
        if ($editId) {
            $editMapping = EasyOrdersGovernorateMapping::find($editId);
        }

        $mappings = EasyOrdersGovernorateMapping::with('governorate')
            ->orderBy('easyorders_name')
            ->paginate(20);

        $governorates = Governorate::orderBy('name_ar')->get();

        return view('admin-views.easy-orders.governorate-mappings', compact('mappings', 'governorates', 'editMapping', 'status'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'easyorders_name' => 'required|string|max:255|unique:easy_orders_governorate_mappings,easyorders_name',
            'governorate_id' => 'required|exists:governorates,id',
        ]);

        EasyOrdersGovernorateMapping::create($data);

        ToastMagic::success(translate('mapping_created_successfully'));

        return redirect()->route('admin.business-settings.easyorders.governorate-mappings.index');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $mapping = EasyOrdersGovernorateMapping::findOrFail($id);

        $data = $request->validate([
            'easyorders_name' => 'required|string|max:255|unique:easy_orders_governorate_mappings,easyorders_name,' . $mapping->id,
            'governorate_id' => 'required|exists:governorates,id',
        ]);

        $mapping->update($data);

        ToastMagic::success(translate('mapping_updated_successfully'));

        return redirect()->route('admin.business-settings.easyorders.governorate-mappings.index');
    }

    public function destroy(int $id): RedirectResponse
    {
        $mapping = EasyOrdersGovernorateMapping::findOrFail($id);
        $mapping->delete();

        ToastMagic::success(translate('mapping_deleted_successfully'));

        return redirect()->route('admin.business-settings.easyorders.governorate-mappings.index');
    }
}


