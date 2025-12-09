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
        $dateField = $request['date_field'] ?? 'updated_at';

        $queryParam = [
            'search' => $search,
            'seller_id' => $sellerId,
            'date_type' => $dateType,
            'from' => $from,
            'to' => $to,
            'date_field' => $dateField,
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

            

        // Apply date filter on orders (using selected date_field)
        $productQuery = $this->date_wise_common_filter_for_orders($productQuery, $dateType, $from, $to, $dateField);

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

        // Component-based custom total - same pattern as Orders List
        $includeProducts = $request->get('include_products', '1') === '1';
        $includeShipping = $request->get('include_shipping', '1') === '1';
        $includeDiscounts = $request->get('include_discounts', '1') === '1';
        $includeDelivery = $request->get('include_delivery', '1') === '1';
        
        $orderQuery = \App\Models\Order::query()
            ->when($sellerId && $sellerId != 'all', function ($query) use ($sellerId) {
                $query->where('seller_id', $sellerId);
            })
            ->when(($dateType == 'this_year'), function ($query) use ($dateField) {
                return $query->whereYear($dateField, date('Y'));
            })
            ->when(($dateType == 'this_month'), function ($query) use ($dateField) {
                return $query->whereMonth($dateField, date('m'))
                    ->whereYear($dateField, date('Y'));
            })
            ->when(($dateType == 'this_week'), function ($query) use ($dateField) {
                return $query->whereBetween($dateField, [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
            })
            ->when(($dateType == 'today'), function ($query) use ($dateField) {
                return $query->whereBetween($dateField, [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()]);
            })
            ->when(($dateType == 'custom_date' && !is_null($from) && !is_null($to)), function ($query) use ($dateField, $from, $to) {
                return $query->whereDate($dateField, '>=', $from)
                    ->whereDate($dateField, '<=', $to);
            });

        // Calculate components
        $orders = $orderQuery->with('orderDetails')->get();
        $componentsProducts = 0;
        $componentsShipping = 0;
        $componentsDiscounts = 0;
        $componentsDelivery = 0;

        foreach ($orders as $order) {
            if ($includeProducts) {
                $componentsProducts += $order->orderDetails->sum(function($detail) {
                    return $detail->qty * $detail->price;
                });
            }
            if ($includeShipping) $componentsShipping += $order->shipping_cost ?? 0;
            if ($includeDiscounts) $componentsDiscounts += ($order->discount_amount ?? 0) + ($order->extra_discount ?? 0);
            if ($includeDelivery) $componentsDelivery += $order->deliveryman_charge ?? 0;
        }
        
        $customTotal = $componentsProducts + $componentsShipping - $componentsDiscounts + $componentsDelivery;

        return view('admin-views.report.vendor-product-sales', compact(
            'sellers',
            'products',
            'totalProductSale',
            'totalProductSaleAmount',
            'totalDiscountGiven',
            'customTotal',
            'search',
            'dateType',
            'from',
            'to',
            'sellerId',
            'includeProducts',
            'includeShipping',
            'includeDiscounts',
            'includeDelivery',
            'dateField',
        ));
    }

    public function date_wise_common_filter_for_orders($query, $dateType, $from, $to, $dateField = 'updated_at')
    {
        $column = 'o.' . $dateField;
        
        return $query->when(($dateType == 'this_year'), function ($query) use ($column) {
                return $query->whereYear($column, date('Y'));
            })
            ->when(($dateType == 'this_month'), function ($query) use ($column) {
                return $query->whereMonth($column, date('m'))
                    ->whereYear($column, date('Y'));
            })
            ->when(($dateType == 'this_week'), function ($query) use ($column) {
                return $query->whereBetween($column, [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
            })
            ->when(($dateType == 'today'), function ($query) use ($column) {
                return $query->whereBetween($column, [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()]);
            })
            ->when(($dateType == 'custom_date' && !is_null($from) && !is_null($to)), function ($query) use ($column, $from, $to) {
                return $query->whereDate($column, '>=', $from)
                    ->whereDate($column, '<=', $to);
            });
    }

    public function exportExcel(Request $request)
    {
        $search = $request['search'];
        $from = $request['from'];
        $to = $request['to'];
        $sellerId = $request['seller_id'] ?? 'all';
        $dateType = $request['date_type'] ?? 'this_year';
        $dateField = $request['date_field'] ?? 'updated_at';

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

        $products = $this->date_wise_common_filter_for_orders($productQuery, $dateType, $from, $to, $dateField)
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


