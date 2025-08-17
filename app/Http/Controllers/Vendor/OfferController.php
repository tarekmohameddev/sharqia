<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\BaseController;
use App\Models\Offer;
use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OfferController extends BaseController
{
    public function index(): JsonResponse
    {
        $offers = Offer::whereHas('product', function ($query) {
            $query->where('user_id', Auth::id());
        })->with(['product', 'variant', 'giftProduct'])->get();

        return response()->json($offers);
    }

    public function show(Offer $offer): JsonResponse
    {
        $this->authorizeOffer($offer);
        $offer->load(['product', 'variant', 'giftProduct']);
        return response()->json($offer);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'variant_id' => ['nullable', 'exists:product_stocks,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'bundle_price' => ['required', 'numeric', 'min:0'],
            'gift_product_id' => ['nullable', 'exists:products,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $product = Product::where('id', $data['product_id'])->where('user_id', Auth::id())->firstOrFail();
        if (!empty($data['variant_id'])) {
            ProductStock::where('id', $data['variant_id'])->where('product_id', $product->id)->firstOrFail();
        }

        if (!empty($data['is_active'])) {
            $this->validateStock($product->id, $data['variant_id'] ?? null, $data['quantity']);
        }

        $offer = Offer::create($data);

        return response()->json($offer, 201);
    }

    public function update(Request $request, Offer $offer): JsonResponse
    {
        $this->authorizeOffer($offer);

        $data = $request->validate([
            'product_id' => ['sometimes', 'exists:products,id'],
            'variant_id' => ['nullable', 'exists:product_stocks,id'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'bundle_price' => ['sometimes', 'numeric', 'min:0'],
            'gift_product_id' => ['nullable', 'exists:products,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $productId = $data['product_id'] ?? $offer->product_id;
        $product = Product::where('id', $productId)->where('user_id', Auth::id())->firstOrFail();

        $variantId = array_key_exists('variant_id', $data) ? $data['variant_id'] : $offer->variant_id;
        if ($variantId) {
            ProductStock::where('id', $variantId)->where('product_id', $product->id)->firstOrFail();
        }

        $isActivating = isset($data['is_active']) && $data['is_active'];
        $quantity = $data['quantity'] ?? $offer->quantity;
        if ($isActivating) {
            $this->validateStock($product->id, $variantId, $quantity);
        }

        $offer->update($data);

        return response()->json($offer);
    }

    public function destroy(Offer $offer): JsonResponse
    {
        $this->authorizeOffer($offer);
        $offer->delete();
        return response()->json([], 204);
    }

    protected function authorizeOffer(Offer $offer): void
    {
        if ($offer->product->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }
    }

    protected function validateStock($productId, $variantId, $quantity): void
    {
        if ($variantId) {
            $stock = ProductStock::find($variantId);
            if (!$stock || $stock->qty < $quantity) {
                abort(422, 'Insufficient stock for the variant.');
            }
        } else {
            $product = Product::find($productId);
            if (!$product || $product->current_stock < $quantity) {
                abort(422, 'Insufficient stock for the product.');
            }
        }
    }
}
