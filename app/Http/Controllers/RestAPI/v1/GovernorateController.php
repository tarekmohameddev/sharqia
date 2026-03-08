<?php

namespace App\Http\Controllers\RestAPI\v1;

use App\Http\Controllers\Controller;
use App\Models\Governorate;
use Illuminate\Http\JsonResponse;

class GovernorateController extends Controller
{
    /**
     * List all governorates (cities) for delivery selection.
     * Used by the mobile app to populate the city dropdown when placing an order.
     * Each governorate has an id that is sent as city_id in place-simple.
     */
    public function index(): JsonResponse
    {
        $governorates = Governorate::with('shippingCost')
            ->orderBy('name_ar')
            ->get()
            ->map(function (Governorate $g) {
                return [
                    'id' => $g->id,
                    'name' => $g->name_ar,
                    'shipping_cost' => $g->shippingCost ? (float) $g->shippingCost->cost : 0.0,
                ];
            });

        return response()->json(['governorates' => $governorates]);
    }
}
