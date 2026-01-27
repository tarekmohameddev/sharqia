<?php

namespace App\Http\Controllers\Admin\EasyOrders;

use App\Http\Controllers\BaseController;
use App\Models\EasyOrder;
use App\Models\Order;
use App\Services\EasyOrdersService;
use App\Imports\EasyOrdersExcelImport;
use App\Exports\EasyOrdersImportReportExport;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;

class EasyOrdersExcelImportController extends BaseController
{
    public function __construct(
        private readonly EasyOrdersService $easyOrdersService,
    ) {
    }

    public function index(?Request $request = null, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse|JsonResponse
    {
        return view('admin-views.easy-orders.excel-import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $file = $request->file('file');
            
            // Import and process the file
            $import = new EasyOrdersExcelImport($this->easyOrdersService);
            Excel::import($import, $file);
            
            $results = $import->getResults();
            
            // Generate report
            $reportFileName = 'easyorders_import_report_' . now()->format('Y-m-d_His') . '.xlsx';
            
            return Excel::download(
                new EasyOrdersImportReportExport($results),
                $reportFileName
            );
            
        } catch (\Throwable $e) {
            Log::error('EasyOrders Excel Import Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            ToastMagic::error('Import failed: ' . $e->getMessage());
            return back();
        }
    }
}
