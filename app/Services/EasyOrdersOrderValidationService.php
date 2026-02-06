<?php

namespace App\Services;

use App\Models\EasyOrder;
use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Support\Facades\Log;

class EasyOrdersOrderValidationService
{
    public function __construct(
        private readonly EasyOrdersApiService $apiService,
        private readonly EasyOrdersService $easyOrdersService,
    ) {
    }

    /**
     * Validate a single EasyOrder record against the EasyOrders API and our imported order.
     *
     * @param EasyOrder $easyOrder
     * @return array Validation result
     */
    public function validateOrder(EasyOrder $easyOrder): array
    {
        $result = [
            'easyorders_id' => $easyOrder->easyorders_id,
            'full_name' => $easyOrder->full_name,
            'phone' => $easyOrder->phone,
            'our_staging_status' => $easyOrder->status,
            'imported_order_id' => $easyOrder->imported_order_id,
            'created_at' => $easyOrder->created_at?->format('Y-m-d H:i:s'),
            'status' => 'unknown',
            'mismatches' => [],
            'api_data' => null,
        ];

        // 1. Fetch fresh data from EasyOrders API
        try {
            $apiData = $this->apiService->getOrderById($easyOrder->easyorders_id);
        } catch (\Throwable $e) {
            $result['status'] = 'api_error';
            $result['mismatches'][] = [
                'field' => 'API Error',
                'easyorders_value' => $e->getMessage(),
                'our_value' => '-',
            ];
            Log::warning('EasyOrders order validation: API error', [
                'easyorders_id' => $easyOrder->easyorders_id,
                'error' => $e->getMessage(),
            ]);
            return $result;
        }

        if ($apiData === null) {
            $result['status'] = 'not_found_on_easyorders';
            $result['mismatches'][] = [
                'field' => 'Order',
                'easyorders_value' => 'Not found on EasyOrders',
                'our_value' => 'Exists in our staging',
            ];
            return $result;
        }

        $result['api_data'] = [
            'cost' => $apiData['cost'] ?? 0,
            'shipping_cost' => $apiData['shipping_cost'] ?? 0,
            'total_cost' => $apiData['total_cost'] ?? 0,
            'status' => $apiData['status'] ?? '',
            'cart_items_count' => count($apiData['cart_items'] ?? []),
        ];

        // 2. If order is not imported, flag it
        if ($easyOrder->status !== 'imported' || !$easyOrder->imported_order_id) {
            $result['status'] = 'not_imported';
            $result['mismatches'][] = [
                'field' => 'Import Status',
                'easyorders_value' => 'Order exists on EasyOrders',
                'our_value' => 'Not imported (status: ' . $easyOrder->status . ')',
            ];

            // Still compare staging data vs API data
            $this->compareStagingData($easyOrder, $apiData, $result);

            return $result;
        }

        // 3. Compare staging data (raw_payload) vs fresh API data
        $this->compareStagingData($easyOrder, $apiData, $result);

        // 4. Compare imported order data vs API data
        $this->compareImportedOrder($easyOrder, $apiData, $result);

        // Determine final status
        if (empty($result['mismatches'])) {
            $result['status'] = 'matched';
        } else {
            $result['status'] = 'mismatched';
        }

        return $result;
    }

    /**
     * Compare the staging record's stored data with fresh API data.
     */
    private function compareStagingData(EasyOrder $easyOrder, array $apiData, array &$result): void
    {
        // Compare core financial fields
        $fieldsToCompare = [
            'cost' => ['label' => 'Product Cost', 'staging' => (float) $easyOrder->cost],
            'shipping_cost' => ['label' => 'Shipping Cost', 'staging' => (float) $easyOrder->shipping_cost],
            'total_cost' => ['label' => 'Total Cost', 'staging' => (float) $easyOrder->total_cost],
        ];

        foreach ($fieldsToCompare as $field => $info) {
            $apiValue = (float) ($apiData[$field] ?? 0);
            $ourValue = $info['staging'];

            if (abs($apiValue - $ourValue) > 0.01) {
                $result['mismatches'][] = [
                    'field' => $info['label'] . ' (Staging)',
                    'easyorders_value' => $apiValue,
                    'our_value' => $ourValue,
                ];
            }
        }

        // Compare customer info
        $customerFields = [
            'full_name' => 'Customer Name',
            'phone' => 'Phone',
            'government' => 'Government',
        ];

        foreach ($customerFields as $field => $label) {
            $apiValue = trim((string) ($apiData[$field] ?? ''));
            $ourValue = trim((string) ($easyOrder->{$field} ?? ''));

            if ($apiValue !== $ourValue && !($apiValue === '' && $ourValue === '')) {
                $result['mismatches'][] = [
                    'field' => $label . ' (Staging)',
                    'easyorders_value' => $apiValue ?: '(empty)',
                    'our_value' => $ourValue ?: '(empty)',
                ];
            }
        }

        // Compare cart items count with stored payload
        $storedPayload = $easyOrder->raw_payload ?? [];
        $storedCartCount = count($storedPayload['cart_items'] ?? []);
        $apiCartCount = count($apiData['cart_items'] ?? []);

        if ($storedCartCount !== $apiCartCount) {
            $result['mismatches'][] = [
                'field' => 'Cart Items Count (Staging Payload)',
                'easyorders_value' => $apiCartCount,
                'our_value' => $storedCartCount,
            ];
        }

        // Compare individual cart items between API and stored payload
        $this->compareCartItems($storedPayload, $apiData, $result, 'Staging Payload');
    }

    /**
     * Compare the imported order in our system vs fresh API data.
     */
    private function compareImportedOrder(EasyOrder $easyOrder, array $apiData, array &$result): void
    {
        $order = Order::with('details')->find($easyOrder->imported_order_id);

        if (!$order) {
            $result['mismatches'][] = [
                'field' => 'Imported Order',
                'easyorders_value' => 'Order exists on EasyOrders',
                'our_value' => 'Imported order #' . $easyOrder->imported_order_id . ' not found in orders table',
            ];
            return;
        }

        // Compare shipping cost
        $apiShipping = (float) ($apiData['shipping_cost'] ?? 0);
        $ourShipping = (float) $order->shipping_cost;

        if (abs($apiShipping - $ourShipping) > 0.01) {
            $result['mismatches'][] = [
                'field' => 'Shipping Cost (Imported Order)',
                'easyorders_value' => $apiShipping,
                'our_value' => $ourShipping,
            ];
        }

        // Compare line items count
        $apiCartItems = $apiData['cart_items'] ?? [];
        $orderDetails = $order->details ?? collect();

        // Build expected line items from API cart_items using our SKU matching logic
        $expectedProducts = $this->resolveApiCartItemsToProducts($apiCartItems);
        $actualDetails = $orderDetails->toArray();

        // Compare number of line items
        if (count($expectedProducts) !== count($actualDetails)) {
            $result['mismatches'][] = [
                'field' => 'Line Items Count (Imported Order)',
                'easyorders_value' => count($expectedProducts) . ' (resolved from ' . count($apiCartItems) . ' cart items)',
                'our_value' => count($actualDetails),
            ];
        }

        // Compare individual line item quantities
        // Group expected by product_id for comparison
        $expectedByProduct = [];
        foreach ($expectedProducts as $ep) {
            $pid = $ep['product_id'];
            if (!isset($expectedByProduct[$pid])) {
                $expectedByProduct[$pid] = ['product_id' => $pid, 'quantity' => 0, 'name' => $ep['name'] ?? ''];
            }
            $expectedByProduct[$pid]['quantity'] += $ep['quantity'];
        }

        $actualByProduct = [];
        foreach ($actualDetails as $detail) {
            $pid = $detail['product_id'];
            if (!isset($actualByProduct[$pid])) {
                $actualByProduct[$pid] = ['product_id' => $pid, 'quantity' => 0];
            }
            $actualByProduct[$pid]['quantity'] += (int) $detail['qty'];
        }

        // Find quantity mismatches
        foreach ($expectedByProduct as $pid => $expected) {
            $actual = $actualByProduct[$pid] ?? null;
            if (!$actual) {
                $result['mismatches'][] = [
                    'field' => 'Missing Product in Order (ID: ' . $pid . ')',
                    'easyorders_value' => 'Expected qty: ' . $expected['quantity'] . ' (' . $expected['name'] . ')',
                    'our_value' => 'Not found in order details',
                ];
            } elseif ($actual['quantity'] !== $expected['quantity']) {
                $result['mismatches'][] = [
                    'field' => 'Product Quantity (ID: ' . $pid . ')',
                    'easyorders_value' => $expected['quantity'],
                    'our_value' => $actual['quantity'],
                ];
            }
        }

        // Check for extra products in our order that aren't in EasyOrders
        foreach ($actualByProduct as $pid => $actual) {
            if (!isset($expectedByProduct[$pid])) {
                $result['mismatches'][] = [
                    'field' => 'Extra Product in Order (ID: ' . $pid . ')',
                    'easyorders_value' => 'Not in EasyOrders cart',
                    'our_value' => 'Qty: ' . $actual['quantity'],
                ];
            }
        }
    }

    /**
     * Compare individual cart items between two payloads.
     *
     * Supports two matching strategies:
     * 1. By cart item `id` (when stored items have IDs - full webhook payloads)
     * 2. By `product.sku` (when stored items lack IDs - minimal/Excel payloads)
     *
     * Only compares fields that actually exist in the stored payload.
     */
    private function compareCartItems(array $storedPayload, array $apiData, array &$result, string $context): void
    {
        $storedItems = $storedPayload['cart_items'] ?? [];
        $apiItems = $apiData['cart_items'] ?? [];

        if (empty($apiItems) || empty($storedItems)) {
            return;
        }

        // Determine if stored items have IDs (full payload) or not (minimal payload)
        $storedHaveIds = false;
        foreach ($storedItems as $item) {
            if (!empty($item['id'])) {
                $storedHaveIds = true;
                break;
            }
        }

        if ($storedHaveIds) {
            $this->compareCartItemsById($storedItems, $apiItems, $result, $context);
        } else {
            $this->compareCartItemsBySku($storedItems, $apiItems, $result, $context);
        }
    }

    /**
     * Compare cart items by their unique ID (full webhook payloads).
     */
    private function compareCartItemsById(array $storedItems, array $apiItems, array &$result, string $context): void
    {
        $storedById = [];
        foreach ($storedItems as $item) {
            $id = $item['id'] ?? null;
            if ($id) {
                $storedById[$id] = $item;
            }
        }

        foreach ($apiItems as $apiItem) {
            $itemId = $apiItem['id'] ?? null;
            if (!$itemId) {
                continue;
            }

            $storedItem = $storedById[$itemId] ?? null;
            if (!$storedItem) {
                $productName = $apiItem['product']['name'] ?? 'Unknown';
                $result['mismatches'][] = [
                    'field' => "Cart Item Missing in {$context} ({$productName})",
                    'easyorders_value' => 'Exists (qty: ' . ($apiItem['quantity'] ?? 0) . ', price: ' . ($apiItem['price'] ?? 0) . ')',
                    'our_value' => 'Not in stored payload',
                ];
                continue;
            }

            $this->compareCartItemFields($apiItem, $storedItem, $result, $context);
        }
    }

    /**
     * Compare cart items by product SKU (minimal payloads that lack item IDs).
     *
     * Matches API cart items to stored items using product.sku. Only compares
     * fields that actually exist in the stored payload (price, quantity, etc.).
     */
    private function compareCartItemsBySku(array $storedItems, array $apiItems, array &$result, string $context): void
    {
        // Index stored items by product SKU
        $storedBySku = [];
        foreach ($storedItems as $item) {
            $sku = $item['product']['sku'] ?? null;
            if ($sku) {
                $storedBySku[$sku] = $item;
            }
        }

        $matchedSkus = [];

        foreach ($apiItems as $apiItem) {
            $apiSku = $apiItem['product']['sku'] ?? null;
            if (!$apiSku) {
                continue;
            }

            $storedItem = $storedBySku[$apiSku] ?? null;
            if (!$storedItem) {
                // SKU not found in stored payload at all
                $productName = $apiItem['product']['name'] ?? 'Unknown';
                $result['mismatches'][] = [
                    'field' => "Cart Item SKU Missing in {$context} ({$productName})",
                    'easyorders_value' => "SKU: {$apiSku}, qty: " . ($apiItem['quantity'] ?? 0) . ', price: ' . ($apiItem['price'] ?? 0),
                    'our_value' => 'SKU not in stored payload',
                ];
                continue;
            }

            $matchedSkus[] = $apiSku;

            // Only compare fields that exist in the stored item
            $this->compareCartItemFields($apiItem, $storedItem, $result, $context);
        }

        // Check for stored items that aren't in the API response
        foreach ($storedBySku as $sku => $storedItem) {
            if (!in_array($sku, $matchedSkus, true)) {
                $result['mismatches'][] = [
                    'field' => "Extra Cart Item in {$context} (SKU: {$sku})",
                    'easyorders_value' => 'Not in EasyOrders',
                    'our_value' => "SKU: {$sku}",
                ];
            }
        }
    }

    /**
     * Compare individual fields between an API cart item and a stored cart item.
     * Only compares fields that actually exist in the stored item.
     */
    private function compareCartItemFields(array $apiItem, array $storedItem, array &$result, string $context): void
    {
        $productName = $apiItem['product']['name'] ?? $storedItem['product']['name'] ?? 'Unknown';

        // Compare price (only if stored item has a price field)
        if (array_key_exists('price', $storedItem)) {
            $apiPrice = (float) ($apiItem['price'] ?? 0);
            $storedPrice = (float) ($storedItem['price'] ?? 0);
            if (abs($apiPrice - $storedPrice) > 0.01) {
                $result['mismatches'][] = [
                    'field' => "Cart Item Price ({$productName}) in {$context}",
                    'easyorders_value' => $apiPrice,
                    'our_value' => $storedPrice,
                ];
            }
        }

        // Compare quantity (only if stored item has a quantity field)
        if (array_key_exists('quantity', $storedItem)) {
            $apiQty = (int) ($apiItem['quantity'] ?? 0);
            $storedQty = (int) ($storedItem['quantity'] ?? 0);
            if ($apiQty !== $storedQty) {
                $result['mismatches'][] = [
                    'field' => "Cart Item Quantity ({$productName}) in {$context}",
                    'easyorders_value' => $apiQty,
                    'our_value' => $storedQty,
                ];
            }
        }
    }

    /**
     * Resolve API cart items to local products using SKU matching.
     */
    private function resolveApiCartItemsToProducts(array $cartItems): array
    {
        $resolved = [];

        foreach ($cartItems as $cartItem) {
            $sku = $cartItem['product']['sku'] ?? null;
            $cartQty = max(1, (int) ($cartItem['quantity'] ?? 1));

            if (!$sku) {
                continue;
            }

            $parsed = $this->easyOrdersService->parseSkuString($sku);
            $products = $this->easyOrdersService->findProductsBySku($parsed);

            foreach ($products as $entry) {
                $product = $entry['product'];
                $qty = $entry['quantity'] * $cartQty;
                $resolved[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'quantity' => $qty,
                ];
            }
        }

        return $resolved;
    }
}
