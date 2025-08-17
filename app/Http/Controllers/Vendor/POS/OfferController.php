<?php

namespace App\Http\Controllers\Vendor\POS;

use App\Http\Controllers\BaseController;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class OfferController extends BaseController
{
    public function index(?Request $request, ?string $type = null): View
    {
        $sellerId = auth('seller')->id();
        $products = Product::select('id','name')->where(['status'=>1,'user_id'=>$sellerId])->get();
        $offers = [];
        return view('vendor-views.pos.offers', compact('products','offers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $offers = $request->input('offers', []);
        if ($url = env('OFFER_API_URL')) {
            try {
                Http::post(rtrim($url,'/'), ['offers'=>$offers]);
            } catch (\Exception $e) {
                // ignore
            }
        }
        return back()->with('success', translate('offer_saved_successfully'));
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        $payload = $request->only(['id','status']);
        if ($url = env('OFFER_STATUS_API_URL')) {
            try {
                Http::post(rtrim($url,'/'), $payload);
            } catch (\Exception $e) {
                // ignore
            }
        }
        return back();
    }
}
