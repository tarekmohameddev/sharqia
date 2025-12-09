<?php

namespace App\Http\Controllers\RestAPI\v1;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\EasyOrder;
use App\Services\EasyOrdersService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EasyOrdersWebhookController extends Controller
{
    public function __construct(
        private readonly EasyOrdersService $easyOrdersService,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $easyordersId = $payload['id'] ?? null;

        if (!$easyordersId) {
            return response()->json(['message' => 'Missing EasyOrders id'], 400);
        }

        $cartItem = $payload['cart_items'][0] ?? null;
        $product = $cartItem['product'] ?? null;

        $easyOrder = EasyOrder::updateOrCreate(
            ['easyorders_id' => $easyordersId],
            [
                'raw_payload' => $payload,
                'full_name' => $payload['full_name'] ?? null,
                'phone' => $payload['phone'] ?? null,
                'government' => $payload['government'] ?? null,
                'address' => $payload['address'] ?? null,
                'sku_string' => $product['sku'] ?? null,
                'cost' => $payload['cost'] ?? 0,
                'shipping_cost' => $payload['shipping_cost'] ?? 0,
                'total_cost' => $payload['total_cost'] ?? 0,
                'status' => $payload['status'] ?? 'pending',
            ]
        );

        // Auto-import if enabled
        $autoImportValue = getWebConfig('easyorders_auto_import');
        
        // Fallback: if getWebConfig returns null, query database directly
        if ($autoImportValue === null) {
            $setting = BusinessSetting::where('type', 'easyorders_auto_import')->first();
            if ($setting) {
                // Try to decode JSON, fallback to raw value
                $decoded = json_decode($setting->value, true);
                $autoImportValue = ($decoded !== null) ? $decoded : $setting->value;
                Log::warning('EasyOrders auto-import: getWebConfig returned null, using direct DB query', [
                    'easyorders_id' => $easyordersId,
                    'db_value' => $setting->value,
                    'decoded_value' => $autoImportValue,
                ]);
            } else {
                Log::warning('EasyOrders auto-import: Setting not found in database', [
                    'easyorders_id' => $easyordersId,
                ]);
            }
        }
        
        // Handle string "1", integer 1, or boolean true
        $autoImport = ($autoImportValue === '1' || $autoImportValue === 1 || $autoImportValue === true);
        
        Log::info('EasyOrders webhook auto-import check', [
            'easyorders_id' => $easyordersId,
            'auto_import_value' => $autoImportValue,
            'auto_import_value_type' => gettype($autoImportValue),
            'auto_import_enabled' => $autoImport,
        ]);
        
        if ($autoImport) {
            try {
                $this->easyOrdersService->importOrder($easyOrder);
                Log::info('EasyOrders auto-import succeeded', [
                    'easyorders_id' => $easyordersId,
                    'imported_order_id' => $easyOrder->imported_order_id,
                ]);
            } catch (\Throwable $e) {
                Log::error('EasyOrders auto-import failed', [
                    'easyorders_id' => $easyordersId,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $easyOrder->status = 'failed';
                $easyOrder->import_error = $e->getMessage();
                $easyOrder->save();
            }
        } else {
            Log::info('EasyOrders auto-import skipped (disabled)', [
                'easyorders_id' => $easyordersId,
            ]);
        }

        return response()->json(['success' => true]);
    }
}



