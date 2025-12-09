<html>
<table>
    <thead>
    <tr>
        <th>{{translate('vendor_products_sales')}}</th>
    </tr>
    <tr>
        <th>{{ translate('filter_Criteria') .' '.'-'}}</th>
        <th></th>
        <th>
            {{translate('search_Bar_Content').' '.'-'.' '. ($data['search'] ?? 'N/A')}}
            <br>
            {{translate('vendor').' '.'-'.' '.ucwords($data['vendor'] != 'all' ? $data['vendor']?->shop?->name : translate('all'))}}
            <br>
            {{translate('date_type').' '.'-'.' '.translate($data['date_type'])}}
            <br>
            @if($data['from'] && $data['to'])
                {{translate('from').' '.'-'.' '.date('d M, Y',strtotime($data['from']))}}
                <br>
                {{translate('to').' '.'-'.' '.date('d M, Y',strtotime($data['to']))}}
                <br>
            @endif
        </th>
    </tr>
    <tr>
        <td>{{translate('SL')}}</td>
        <td>{{translate('product_Name')}}</td>
        <td>{{translate('product_Unit_Price')}}</td>
        <td>{{translate('total_Amount_Sold')}}</td>
        <td>{{translate('total_Quantity_Sold')}}</td>
        <td>{{translate('average_Product_Value')}}</td>
        <td>{{translate('current_Stock_Amount')}}</td>
    </tr>
    @foreach ($data['products'] as $key=>$item)
        <tr>
            <td>{{++$key}}</td>
            <td>{{$item['name']}}</td>
            <td>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $item->unit_price ?? 0 )) }}</td>
            <td>{{ setCurrencySymbol(amount: usdToDefaultCurrency(amount:  $item->total_sold_amount ?? 0)) }}</td>
            <td>{{ (int)($item->product_quantity ?? 0) }}</td>
            <td>
                {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount:
                    ( $item->total_sold_amount ?? 0) /
                    max(1, ($item->product_quantity ?? 1))))
                }}
            </td>
            <td>
                {{ $item->product_type == 'digital' ? ($item->status==1 ? translate('available') : translate('not_available')) : $item->current_stock }}
            </td>
        </tr>
    @endforeach
    </thead>
    </table>
</html>


