<?php

namespace App\Http\Controllers\Admin\EasyOrders;

use App\Exports\EasyOrdersSkuReportExport;
use App\Http\Controllers\BaseController;
use App\Models\BusinessSetting;
use App\Models\Product;
use App\Services\EasyOrdersApiService;
use App\Services\EasyOrdersService;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EasyOrdersSkuValidationController extends BaseController
{
    private const SESSION_RESULTS_KEY = 'easyorders_sku_validation_results';

    public function __construct(
        private readonly EasyOrdersApiService $easyOrdersApiService,
        private readonly EasyOrdersService $easyOrdersService,
    ) {
    }

    public function index(?Request $request = null, string $type = null): View|RedirectResponse
    {
        $apiKey = $this->easyOrdersApiService->getApiKey();
        $results = session(self::SESSION_RESULTS_KEY);

        return view('admin-views.easy-orders.sku-validation', [
            'apiKey' => $apiKey,
            'results' => $results,
        ]);
    }

    public function runValidation(Request $request): View|RedirectResponse
    {
        try {
            $products = $this->easyOrdersApiService->getAllProducts();
        } catch (\Throwable $e) {
            Log::error('EasyOrders SKU validation: API error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            ToastMagic::error($e->getMessage());
            return redirect()->route('admin.business-settings.easyorders.sku-validation.index');
        }

        $allCodes = [];
        foreach ($products as $product) {
            $compoundSku = $product['sku'] ?? '';
            $parsed = $this->easyOrdersService->parseSkuString($compoundSku);
            if (empty($parsed) && $compoundSku !== null && $compoundSku !== '') {
                $allCodes[] = $compoundSku;
            }
            foreach ($parsed as $item) {
                $code = $item['code'] ?? '';
                if ($code !== '') {
                    $allCodes[] = $code;
                }
            }
        }

        $uniqueCodes = array_values(array_unique(array_filter($allCodes)));
        $localProducts = Product::whereIn('code', $uniqueCodes)->get()->keyBy('code');

        $matchedCount = 0;
        $unmatchedCount = 0;
        $reportRows = [];

        foreach ($products as $product) {
            $compoundSku = $product['sku'] ?? '';
            $parsed = $this->easyOrdersService->parseSkuString($compoundSku);

            if (empty($parsed) && $compoundSku !== null && $compoundSku !== '') {
                $reportRows[] = [
                    'sku_code' => $compoundSku,
                    'easyorders_product_name' => $product['name'] ?? '',
                    'easyorders_product_id' => $product['id'] ?? '',
                    'compound_sku' => $compoundSku,
                    'status' => 'Unmatched',
                    'local_product_name' => '',
                ];
                $unmatchedCount++;
                continue;
            }

            foreach ($parsed as $item) {
                $code = $item['code'] ?? '';
                if ($code === '') {
                    continue;
                }
                $localProduct = $localProducts->get($code);
                $matched = $localProduct !== null;
                if ($matched) {
                    $matchedCount++;
                } else {
                    $unmatchedCount++;
                    Log::warning('EasyOrders SKU validation: unmatched SKU', [
                        'sku_code' => $code,
                        'easyorders_product_id' => $product['id'] ?? '',
                        'easyorders_product_name' => $product['name'] ?? '',
                    ]);
                }
                $reportRows[] = [
                    'sku_code' => $code,
                    'easyorders_product_name' => $product['name'] ?? '',
                    'easyorders_product_id' => $product['id'] ?? '',
                    'compound_sku' => $compoundSku,
                    'status' => $matched ? 'Matched' : 'Unmatched',
                    'local_product_name' => $matched ? ($localProduct->name ?? '') : '',
                ];
            }
        }

        $summary = [
            'total_easyorders_products' => count($products),
            'total_unique_skus' => count($uniqueCodes),
            'matched_count' => $matchedCount,
            'unmatched_count' => $unmatchedCount,
            'total_rows' => count($reportRows),
        ];

        session([self::SESSION_RESULTS_KEY => [
            'summary' => $summary,
            'rows' => $reportRows,
        ]]);

        $apiKey = $this->easyOrdersApiService->getApiKey();

        ToastMagic::success(translate('SKU_validation_completed'));

        return view('admin-views.easy-orders.sku-validation', [
            'apiKey' => $apiKey,
            'results' => session(self::SESSION_RESULTS_KEY),
        ]);
    }

    public function downloadReport(): BinaryFileResponse|RedirectResponse
    {
        $data = session(self::SESSION_RESULTS_KEY);
        if (!$data || empty($data['rows'])) {
            ToastMagic::warning(translate('No_validation_results_to_download'));
            return redirect()->route('admin.business-settings.easyorders.sku-validation.index');
        }

        $fileName = 'easyorders_sku_validation_report_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new EasyOrdersSkuReportExport($data['rows']),
            $fileName
        );
    }

    public function saveApiKey(Request $request): RedirectResponse
    {
        $request->validate([
            'api_key' => 'nullable|string|max:500',
        ]);

        $value = $request->input('api_key', '');
        BusinessSetting::updateOrInsert(
            ['type' => 'easyorders_api_key'],
            [
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        cacheRemoveByType('business_settings');

        ToastMagic::success(translate('API_key_saved'));
        return redirect()->route('admin.business-settings.easyorders.sku-validation.index');
    }
}
