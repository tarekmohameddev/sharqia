<?php

namespace App\Imports;

use App\Models\EasyOrder;
use App\Models\Order;
use App\Services\EasyOrdersService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class EasyOrdersExcelImport implements ToCollection, WithHeadingRow
{
    private array $results = [];

    public function __construct(
        private readonly EasyOrdersService $easyOrdersService,
    ) {
    }

    public function collection(Collection $rows)
    {
        // Log first row to debug column names
        if ($rows->isNotEmpty()) {
            Log::info('EasyOrders Excel Import - First Row Keys', [
                'keys' => array_keys($rows->first()->toArray()),
                'sample_data' => $rows->first()->toArray(),
            ]);
        }

        // Group rows by Order ID (since each order can have multiple product rows)
        $groupedOrders = $rows->groupBy('order_id');

        foreach ($groupedOrders as $orderId => $orderRows) {
            try {
                // Skip if Order ID is empty
                if (empty($orderId)) {
                    Log::warning('EasyOrders Excel Import - Empty Order ID', [
                        'row_data' => $orderRows->first()->toArray(),
                    ]);
                    continue;
                }

                // Get the first row for order-level data
                $firstRow = $orderRows->first();
                
                Log::info('EasyOrders Excel Import - Processing Order', [
                    'order_id' => $orderId,
                    'customer' => $firstRow['fullname'] ?? 'unknown',
                ]);

                // Check if order was successfully imported (has imported_order_id and status is 'imported')
                $existingEasyOrder = EasyOrder::where('easyorders_id', $orderId)->first();
                
                if ($existingEasyOrder && $existingEasyOrder->status === 'imported' && $existingEasyOrder->imported_order_id) {
                    // Order already successfully imported - skip it
                    Log::info('EasyOrders Excel Import - Order Already Imported', [
                        'order_id' => $orderId,
                        'imported_order_id' => $existingEasyOrder->imported_order_id,
                    ]);
                    
                    $this->results[] = [
                        'order_id' => $orderId,
                        'customer_name' => $firstRow['fullname'] ?? '',
                        'phone' => $firstRow['phone'] ?? '',
                        'city' => $firstRow['city'] ?? '',
                        'total_cost' => $firstRow['total_cost'] ?? 0,
                        'status' => 'Skipped',
                        'reason' => 'Already imported (Order #' . $existingEasyOrder->imported_order_id . ')',
                        'imported_order_id' => $existingEasyOrder->imported_order_id,
                    ];
                    continue;
                }
                
                // If exists but failed or rejected, we'll delete and recreate it
                if ($existingEasyOrder) {
                    Log::info('EasyOrders Excel Import - Deleting existing failed/rejected order', [
                        'order_id' => $orderId,
                        'old_status' => $existingEasyOrder->status,
                    ]);
                    $existingEasyOrder->delete();
                }

                // Parse SKU string from all rows of this order
                $skuParts = [];
                foreach ($orderRows as $row) {
                    $sku = $row['sku'] ?? '';
                    $quantity = $row['quantity'] ?? 1;
                    
                    // Parse SKU format like "222233(5)" or just "222233"
                    if (preg_match('/^([A-Za-z0-9_-]+)\((\d+)\)$/u', $sku, $matches)) {
                        $skuParts[] = $matches[1] . '(' . $matches[2] . ')';
                    } elseif (!empty($sku)) {
                        $skuParts[] = $sku . '(' . $quantity . ')';
                    }
                }
                
                $skuString = implode('+', array_filter($skuParts));

                // Create raw payload from first row data
                $rawPayload = [
                    'id' => $orderId,
                    'full_name' => $firstRow['fullname'] ?? '',
                    'phone' => $firstRow['phone'] ?? '',
                    'government' => $firstRow['city'] ?? '',
                    'address' => $firstRow['address'] ?? '',
                    'cost' => $firstRow['product_cost'] ?? 0,
                    'shipping_cost' => $firstRow['shipping_cost'] ?? 0,
                    'total_cost' => $firstRow['total_cost'] ?? 0,
                    'status' => $firstRow['status'] ?? 'pending',
                    'cart_items' => [[
                        'product' => [
                            'sku' => $skuString,
                        ],
                    ]],
                    'metadata' => [
                        'note' => $firstRow['note'] ?? '',
                        'alt_phone' => $firstRow['alt_phone'] ?? '',
                    ],
                ];

                // Create staging record
                $easyOrder = EasyOrder::create([
                    'easyorders_id' => $orderId,
                    'raw_payload' => $rawPayload,
                    'full_name' => $firstRow['fullname'] ?? '',
                    'phone' => $firstRow['phone'] ?? '',
                    'government' => $firstRow['city'] ?? '',
                    'address' => $firstRow['address'] ?? '',
                    'sku_string' => $skuString,
                    'cost' => $firstRow['product_cost'] ?? 0,
                    'shipping_cost' => $firstRow['shipping_cost'] ?? 0,
                    'total_cost' => $firstRow['total_cost'] ?? 0,
                    'status' => 'pending',
                ]);

                // Try to import the order
                try {
                    $order = $this->easyOrdersService->importOrder($easyOrder);
                    
                    $this->results[] = [
                        'order_id' => $orderId,
                        'customer_name' => $firstRow['fullname'] ?? '',
                        'phone' => $firstRow['phone'] ?? '',
                        'city' => $firstRow['city'] ?? '',
                        'total_cost' => $firstRow['total_cost'] ?? 0,
                        'status' => 'Imported',
                        'reason' => 'Successfully imported',
                        'imported_order_id' => $order->id,
                    ];
                } catch (\Throwable $e) {
                    // Import failed - update staging record
                    $easyOrder->status = 'failed';
                    $easyOrder->import_error = $e->getMessage();
                    $easyOrder->save();

                    $this->results[] = [
                        'order_id' => $orderId,
                        'customer_name' => $firstRow['fullname'] ?? '',
                        'phone' => $firstRow['phone'] ?? '',
                        'city' => $firstRow['city'] ?? '',
                        'total_cost' => $firstRow['total_cost'] ?? 0,
                        'status' => 'Failed',
                        'reason' => $e->getMessage(),
                        'imported_order_id' => null,
                    ];

                    Log::error('EasyOrders Excel Import - Order Import Failed', [
                        'order_id' => $orderId,
                        'error' => $e->getMessage(),
                    ]);
                }

            } catch (\Throwable $e) {
                Log::error('EasyOrders Excel Import - Row Processing Failed', [
                    'order_id' => $orderId ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);

                $this->results[] = [
                    'order_id' => $orderId ?? 'unknown',
                    'customer_name' => '',
                    'phone' => '',
                    'city' => '',
                    'total_cost' => 0,
                    'status' => 'Failed',
                    'reason' => 'Processing error: ' . $e->getMessage(),
                    'imported_order_id' => null,
                ];
            }
        }
    }

    public function getResults(): array
    {
        return $this->results;
    }
}
