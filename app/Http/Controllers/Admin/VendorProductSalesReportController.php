<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Utils\Helpers;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\VendorProductSalesExport;

class VendorProductSalesReportController extends Controller
{
    public function index(Request $request)
    {
        $search = $request['search'];
        $from = $request['from'];
        $to = $request['to'];
        $sellerId = $request['seller_id'] ?? 'all';
        $dateType = $request['date_type'] ?? 'this_year';

        $queryParam = [
            'search' => $search,
            'seller_id' => $sellerId,
            'date_type' => $dateType,
            'from' => $from,
            'to' => $to,
        ];

        $sellers = Seller::where(['status' => 'approved'])->get();

        $productQuery = Product::with(['reviews'])
            ->leftJoin('order_details as od', 'od.product_id', '=', 'products.id')
            ->leftJoin('orders as o', 'o.id', '=', 'od.order_id')
            ->when($sellerId && $sellerId != 'all', function ($query) use ($sellerId) {
                $query->where('o.seller_id', $sellerId);
            })
            ->where(function ($q) use ($search) {
                if ($search) {
                    $q->where('products.name', 'like', "%{$search}%");
                }
            })
            ->select(
                'products.*',
                DB::raw('SUM(COALESCE(od.qty,0) * COALESCE(od.price,0)) as total_sold_amount'),
                DB::raw('SUM(COALESCE(od.qty,0)) as product_quantity'),
                DB::raw('SUM(COALESCE(od.discount,0)) as total_discount')
            )
            ->groupBy('products.id');

            

        // Apply date filter on orders updated_at (delivered timeline)
        $productQuery = $this->date_wise_common_filter_for_orders($productQuery, $dateType, $from, $to);

        $products = $productQuery
            ->latest('products.created_at')
            ->paginate(Helpers::pagination_limit())
            ->appends($queryParam);

        $totalProductSale = 0;
        $totalProductSaleAmount = 0;
        $totalDiscountGiven = 0;

        foreach ($products as $product) {
            $totalProductSale += (int) ($product->product_quantity ?? 0);
            $totalProductSaleAmount += (float) ($product->total_sold_amount ?? 0);
            $totalDiscountGiven += (float) ($product->total_discount ?? 0);
        }

        return view('admin-views.report.vendor-product-sales', compact(
            'sellers',
            'products',
            'totalProductSale',
            'totalProductSaleAmount',
            'totalDiscountGiven',
            'search',
            'dateType',
            'from',
            'to',
            'sellerId'
        ));
    }

    public function date_wise_common_filter_for_orders($query, $dateType, $from, $to)
    {
        return $query->when(($dateType == 'this_year'), function ($query) {
                return $query->whereYear('o.updated_at', date('Y'));
            })
            ->when(($dateType == 'this_month'), function ($query) {
                return $query->whereMonth('o.updated_at', date('m'))
                    ->whereYear('o.updated_at', date('Y'));
            })
            ->when(($dateType == 'this_week'), function ($query) {
                return $query->whereBetween('o.updated_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
            })
            ->when(($dateType == 'today'), function ($query) {
                return $query->whereBetween('o.updated_at', [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()]);
            })
            ->when(($dateType == 'custom_date' && !is_null($from) && !is_null($to)), function ($query) use ($from, $to) {
                return $query->whereDate('o.updated_at', '>=', $from)
                    ->whereDate('o.updated_at', '<=', $to);
            });
    }

    public function exportExcel(Request $request)
    {
        $search = $request['search'];
        $from = $request['from'];
        $to = $request['to'];
        $sellerId = $request['seller_id'] ?? 'all';
        $dateType = $request['date_type'] ?? 'this_year';

        $productQuery = Product::with(['reviews'])
            ->leftJoin('order_details as od', 'od.product_id', '=', 'products.id')
            ->leftJoin('orders as o', 'o.id', '=', 'od.order_id')
            ->when($sellerId && $sellerId != 'all', function ($query) use ($sellerId) {
                $query->where('o.seller_id', $sellerId);
            })
            ->where(function ($q) use ($search) {
                if ($search) {
                    $q->where('products.name', 'like', "%{$search}%");
                }
            })
            ->where('o.is_printed', 1)
            ->select(
                'products.*',
                DB::raw('SUM(COALESCE(od.qty,0) * COALESCE(od.price,0)) as total_sold_amount'),
                DB::raw('SUM(COALESCE(od.qty,0)) as product_quantity'),
                DB::raw('SUM(COALESCE(od.discount,0)) as total_discount')
            )
            ->groupBy('products.id');

        $products = $this->date_wise_common_filter_for_orders($productQuery, $dateType, $from, $to)
            ->latest('products.created_at')
            ->get();

        $vendor = $sellerId !== 'all' ? Seller::with('shop')->find($sellerId) : 'all';

        $data = [
            'products' => $products,
            'search' => $search,
            'vendor' => $vendor,
            'date_type' => $dateType,
            'from' => $from,
            'to' => $to,
        ];

        return Excel::download(new VendorProductSalesExport($data), 'Vendor-Product-Sales.xlsx');
    }
}


