<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\Offer;
use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfferController extends BaseController
{
    public function index(): JsonResponse
    {
        $offers = Offer::with(['product', 'variant', 'giftProduct'])->get();
        return response()->json($offers);
    }

    public function show(Offer $offer): JsonResponse
    {
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

        if (!empty($data['is_active'])) {
            $this->validateStock($data['product_id'], $data['variant_id'] ?? null, $data['quantity']);
        }

        $offer = Offer::create($data);

        return response()->json($offer, 201);
    }

    public function update(Request $request, Offer $offer): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['sometimes', 'exists:products,id'],
            'variant_id' => ['nullable', 'exists:product_stocks,id'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'bundle_price' => ['sometimes', 'numeric', 'min:0'],
            'gift_product_id' => ['nullable', 'exists:products,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $isActivating = isset($data['is_active']) && $data['is_active'];
        $quantity = $data['quantity'] ?? $offer->quantity;
        $productId = $data['product_id'] ?? $offer->product_id;
        $variantId = array_key_exists('variant_id', $data) ? $data['variant_id'] : $offer->variant_id;

        if ($isActivating) {
            $this->validateStock($productId, $variantId, $quantity);
        }

        $offer->update($data);

        return response()->json($offer);
    }

    public function destroy(Offer $offer): JsonResponse
    {
        $offer->delete();
        return response()->json([], 204);
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
