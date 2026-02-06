<?php

namespace App\Http\Controllers\Admin\EasyOrders;

use App\Exports\EasyOrdersOrderValidationReportExport;
use App\Http\Controllers\BaseController;
use App\Models\EasyOrder;
use App\Services\EasyOrdersApiService;
use App\Services\EasyOrdersOrderValidationService;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EasyOrdersOrderValidationController extends BaseController
{
    private const SESSION_RESULTS_KEY = 'easyorders_order_validation_results';

    private const DEFAULT_PER_PAGE = 10;

    public function __construct(
        private readonly EasyOrdersApiService $apiService,
        private readonly EasyOrdersOrderValidationService $validationService,
    ) {
    }

    /**
     * Display the order validation page.
     */
    public function index(?Request $request = null, string $type = null): View|RedirectResponse
    {
        $apiKey = $this->apiService->getApiKey();

        // Get counts for initial display
        $totalStagingOrders = EasyOrder::count();
        $importedCount = EasyOrder::imported()->count();
        $pendingCount = EasyOrder::pending()->count();
        $failedCount = EasyOrder::failed()->count();

        return view('admin-views.easy-orders.order-validation', [
            'apiKey' => $apiKey,
            'totalStagingOrders' => $totalStagingOrders,
            'importedCount' => $importedCount,
            'pendingCount' => $pendingCount,
            'failedCount' => $failedCount,
        ]);
    }

    /**
     * AJAX endpoint: Process a batch of orders for validation.
     *
     * Accepts: date_from, date_to, page, per_page
     * Returns: JSON with batch results, pagination info, and summary.
     */
    public function runBatch(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'page' => 'required|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
            'max_orders' => 'nullable|integer|min:1',
        ]);

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', self::DEFAULT_PER_PAGE);
        $maxOrders = $request->input('max_orders') ? (int) $request->input('max_orders') : null;

        // Build query
        $query = EasyOrder::query()->orderBy('created_at', 'desc');

        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom . ' 00:00:00');
        }
        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo . ' 23:59:59');
        }

        // Get total count for pagination
        $totalOrders = $query->count();

        // If max_orders is set, cap the total
        $effectiveTotal = $maxOrders ? min($totalOrders, $maxOrders) : $totalOrders;
        $totalPages = (int) ceil($effectiveTotal / $perPage);

        // Calculate offset with max_orders consideration
        $offset = ($page - 1) * $perPage;

        // Check if we've already processed enough orders
        if ($maxOrders && $offset >= $maxOrders) {
            return response()->json([
                'results' => [],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_orders' => $totalOrders,
                    'effective_total' => $effectiveTotal,
                    'total_pages' => $totalPages,
                    'has_more' => false,
                ],
                'completed' => true,
            ]);
        }

        // Limit batch size if max_orders would be exceeded
        $batchLimit = $perPage;
        if ($maxOrders) {
            $remaining = $maxOrders - $offset;
            $batchLimit = min($perPage, $remaining);
        }

        // Fetch batch
        $batch = $query->skip($offset)->take($batchLimit)->get();

        if ($batch->isEmpty()) {
            return response()->json([
                'results' => [],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_orders' => $totalOrders,
                    'effective_total' => $effectiveTotal,
                    'total_pages' => $totalPages,
                    'has_more' => false,
                ],
                'completed' => true,
            ]);
        }

        // Validate each order in the batch
        $results = [];
        foreach ($batch as $easyOrder) {
            try {
                $results[] = $this->validationService->validateOrder($easyOrder);
            } catch (\Throwable $e) {
                Log::error('EasyOrders order validation: unexpected error', [
                    'easyorders_id' => $easyOrder->easyorders_id,
                    'error' => $e->getMessage(),
                ]);
                $results[] = [
                    'easyorders_id' => $easyOrder->easyorders_id,
                    'full_name' => $easyOrder->full_name,
                    'phone' => $easyOrder->phone,
                    'our_staging_status' => $easyOrder->status,
                    'imported_order_id' => $easyOrder->imported_order_id,
                    'created_at' => $easyOrder->created_at?->format('Y-m-d H:i:s'),
                    'status' => 'api_error',
                    'mismatches' => [[
                        'field' => 'Unexpected Error',
                        'easyorders_value' => $e->getMessage(),
                        'our_value' => '-',
                    ]],
                    'api_data' => null,
                ];
            }
        }

        $processedSoFar = $offset + count($results);
        $hasMore = $processedSoFar < $effectiveTotal;

        // Accumulate results in session for download
        $this->appendResultsToSession($results, $page === 1);

        return response()->json([
            'results' => $results,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_orders' => $totalOrders,
                'effective_total' => $effectiveTotal,
                'total_pages' => $totalPages,
                'has_more' => $hasMore,
                'processed_so_far' => $processedSoFar,
            ],
            'completed' => !$hasMore,
        ]);
    }

    /**
     * Download the accumulated validation report as Excel.
     */
    public function downloadReport(): BinaryFileResponse|RedirectResponse
    {
        $data = session(self::SESSION_RESULTS_KEY);
        if (!$data || empty($data['results'])) {
            ToastMagic::warning(translate('No_validation_results_to_download'));
            return redirect()->route('admin.business-settings.easyorders.order-validation.index');
        }

        $fileName = 'easyorders_order_validation_report_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new EasyOrdersOrderValidationReportExport($data['results'], $data['summary'] ?? []),
            $fileName
        );
    }

    /**
     * Clear stored validation results from session.
     */
    public function clearResults(): JsonResponse
    {
        session()->forget(self::SESSION_RESULTS_KEY);

        return response()->json(['success' => true]);
    }

    /**
     * Append batch results to session for later download.
     */
    private function appendResultsToSession(array $batchResults, bool $isFirstBatch): void
    {
        if ($isFirstBatch) {
            // Reset session on first batch
            session([self::SESSION_RESULTS_KEY => [
                'results' => $batchResults,
                'summary' => $this->calculateSummary($batchResults),
            ]]);
        } else {
            $existing = session(self::SESSION_RESULTS_KEY, ['results' => [], 'summary' => []]);
            $allResults = array_merge($existing['results'], $batchResults);
            session([self::SESSION_RESULTS_KEY => [
                'results' => $allResults,
                'summary' => $this->calculateSummary($allResults),
            ]]);
        }
    }

    /**
     * Calculate summary statistics from results.
     */
    private function calculateSummary(array $results): array
    {
        $matched = 0;
        $mismatched = 0;
        $notImported = 0;
        $apiErrors = 0;
        $notFoundOnEasyOrders = 0;

        foreach ($results as $r) {
            switch ($r['status'] ?? '') {
                case 'matched':
                    $matched++;
                    break;
                case 'mismatched':
                    $mismatched++;
                    break;
                case 'not_imported':
                    $notImported++;
                    break;
                case 'api_error':
                    $apiErrors++;
                    break;
                case 'not_found_on_easyorders':
                    $notFoundOnEasyOrders++;
                    break;
            }
        }

        return [
            'total' => count($results),
            'matched' => $matched,
            'mismatched' => $mismatched,
            'not_imported' => $notImported,
            'api_errors' => $apiErrors,
            'not_found_on_easyorders' => $notFoundOnEasyOrders,
        ];
    }
}
