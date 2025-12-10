<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Admin;
use App\Enums\ExportFileNames\Admin\Report;
use App\Exports\EmployeeOrderReportExport;
use Carbon\Carbon;
use Illuminate\Contracts\View\View as ViewResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeOrderReportController extends Controller
{
    public function index(Request $request): ViewResponse
    {
        $date_type = $request->input('date_type', 'this_year');
        $from = $request->input('from');
        $to = $request->input('to');

        $query = $this->baseQuery();
        $query = $this->dateWiseCommonFilter($query, $date_type, $from, $to);

        $stats = $query
            ->select(
                'created_by_admin_id',
                DB::raw('COUNT(*) as total_orders')
            )
            ->groupBy('created_by_admin_id')
            ->with('createdByAdmin.role')
            ->get();

        $totalOrders = $stats->sum('total_orders');

        return view('admin-views.report.employee-order-report', compact(
            'stats',
            'totalOrders',
            'date_type',
            'from',
            'to'
        ));
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $date_type = $request->input('date_type', 'this_year');
        $from = $request->input('from');
        $to = $request->input('to');

        $query = $this->baseQuery();
        $query = $this->dateWiseCommonFilter($query, $date_type, $from, $to);

        $stats = $query
            ->select(
                'created_by_admin_id',
                DB::raw('COUNT(*) as total_orders')
            )
            ->groupBy('created_by_admin_id')
            ->with('createdByAdmin.role')
            ->get();

        $data = [
            'stats' => $stats,
            'date_type' => $date_type,
            'from' => $from,
            'to' => $to,
        ];

        return Excel::download(new EmployeeOrderReportExport($data), Report::ORDER_REPORT_LIST);
    }

    protected function baseQuery()
    {
        return Order::whereNotNull('created_by_admin_id')
            ->where('order_type', 'POS');
    }

    protected function dateWiseCommonFilter($query, string $date_type, ?string $from, ?string $to)
    {
        return $query->when(($date_type == 'this_year'), function ($query) {
                return $query->whereYear('created_at', date('Y'));
            })
            ->when(($date_type == 'this_month'), function ($query) {
                return $query->whereMonth('created_at', date('m'))
                    ->whereYear('created_at', date('Y'));
            })
            ->when(($date_type == 'this_week'), function ($query) {
                return $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
            })
            ->when(($date_type == 'today'), function ($query) {
                return $query->whereBetween('created_at', [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()]);
            })
            ->when(($date_type == 'custom_date' && !is_null($from) && !is_null($to)), function ($query) use ($from, $to) {
                return $query->whereDate('created_at', '>=', $from)
                    ->whereDate('created_at', '<=', $to);
            });
    }
}


