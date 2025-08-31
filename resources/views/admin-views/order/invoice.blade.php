@php
    use Illuminate\Support\Facades\Session;
    $currencyCode = getCurrencyCode(type: 'default');
    $direction = Session::get('direction');
    $lang = getDefaultLanguage();
    
    // Define variables we need throughout the template
    $shippingAddress = $order['shipping_address_data'] ?? null;
    $orderTotalPriceSummary = \App\Utils\OrderManager::getOrderTotalPriceSummary(order: $order);
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl"
      style="text-align: right;"
      xmlns="http://www.w3.org/1999/html">
    <head>
        <meta charset="UTF-8">
        <title>فاتورة للمبيع</title>
        <meta http-equiv="Content-Type" content="text/html;"/>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@200;300;400;500;600;700;800&display=swap');

            * {
                margin: 0;
                padding: 0;
                line-height: 1.6;
                font-family: "Cairo", sans-serif;
                color: #000;
                direction: rtl;
            }

            body {
                font-size: 14px;
                font-family: "Cairo", sans-serif;
                direction: rtl;
                text-align: right;
            }

            .invoice-container {
                width: 595px;
                margin: 0 auto;
                padding: 20px;
                background: white;
            }

            .header-section {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 20px;
                border-bottom: 2px solid #ddd;
                padding-bottom: 15px;
            }

            .company-info {
                text-align: right;
            }

            .company-logo {
                width: 80px;
                height: 80px;
                object-fit: contain;
                margin-bottom: 10px;
            }

            .company-name {
                font-size: 18px;
                font-weight: bold;
                color: #000;
                margin-bottom: 5px;
            }

            .hotline {
                font-size: 14px;
                font-weight: bold;
                color: #000;
                margin-bottom: 10px;
            }

            .invoice-info {
                text-align: left;
                padding-top: 40px;
            }

            .invoice-title {
                font-size: 16px;
                font-weight: bold;
                margin-bottom: 10px;
            }

            .invoice-details table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }

            .invoice-details td {
                padding: 8px;
                border: 1px solid #000;
                font-size: 12px;
                text-align: center;
            }

            .invoice-details .label {
                background-color: #f0f0f0;
                font-weight: bold;
            }

            .customer-section {
                margin-bottom: 20px;
            }

            .customer-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 10px;
            }

            .customer-info, .address-info {
                width: 48%;
                padding: 10px;
                border: 1px solid #ddd;
                font-size: 12px;
            }

            .section-title {
                font-weight: bold;
                font-size: 14px;
                margin-bottom: 5px;
                border-bottom: 1px solid #ddd;
                padding-bottom: 3px;
            }

            .order-items {
                margin-bottom: 20px;
            }

            .items-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }

            .items-table th,
            .items-table td {
                padding: 10px;
                border: 1px solid #000;
                text-align: center;
                font-size: 12px;
            }

            .items-table th {
                background-color: #f0f0f0;
                font-weight: bold;
            }

            .totals-section {
                margin-top: 20px;
            }

            .totals-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 5px;
                font-size: 14px;
            }

            .totals-row.final-total {
                font-weight: bold;
                font-size: 16px;
                border-top: 2px solid #000;
                padding-top: 10px;
            }

            .payment-info {
                margin-top: 20px;
                display: flex;
                justify-content: space-between;
            }

            .payment-details, .shipping-costs {
                width: 48%;
                font-size: 12px;
            }

            .footer-note {
                text-align: center;
                margin-top: 30px;
                font-size: 12px;
                color: #666;
            }

            .text-right {
                text-align: right;
            }

            .text-left {
                text-align: left;
            }

            .text-center {
                text-align: center;
            }

            .font-bold {
                font-weight: bold;
            }

            .address-section {
                background-color: #f9f9f9;
                padding: 10px;
                margin: 10px 0;
                border: 1px solid #ddd;
            }
        </style>
    </head>

    <body>
        <div class="invoice-container">
            <!-- Header Section -->
            <div class="header-section" style="align-items: center; padding-bottom: 6px; margin-bottom: 10px;">
                <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                    <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                        @if(isset($invoiceSettings['invoice_logo_status']) && $invoiceSettings['invoice_logo_status'] == 1)
                            @if(isset($invoiceSettings['invoice_logo_type']) && $invoiceSettings['invoice_logo_type'] == 'default')
                                <img class="company-logo" style="width: 40px; height: 40px; margin-bottom: 0;"
                                     src="{{ getStorageImages(path: getWebConfig(name: 'company_web_logo_png'), type:'backend-logo') }}"
                                     alt="شركة ذهب للألبان">
                            @elseif(isset($invoiceSettings['invoice_logo_type']) && $invoiceSettings['invoice_logo_type'] == 'custom' && isset($invoiceSettings['image']))
                                <img class="company-logo" style="width: 40px; height: 40px; margin-bottom: 0;"
                                     src="{{ getStorageImages(path: imagePathProcessing(imageData:  $invoiceSettings['image'], path:'company'), type: 'backend-logo') }}"
                                     alt="شركة ذهب للألبان">
                            @endif
                        @endif
            @php 
            echo json_encode($order);
            @endphp
                        <div class="company-line" style="display: flex; align-items: center; gap: 10px; white-space: nowrap;">
                            @if($order['seller_is']!='admin' && isset($order['seller']) && $order['seller']->shop)
                                <span class="company-name" style="margin: 0;">{{ $order['seller']->shop->name ?? 'اسم المتجر' }}</span>
                            @else
                                <span class="company-name" style="margin: 0;">{{ getWebConfig('company_name') ?? 'شركة ذهب للألبان' }}</span>
                            @endif
                            <span class="hotline" style="margin: 0; font-weight: normal;">الخط الساخن: {{ getWebConfig('company_phone') ?? '01270005957' }}</span>
                        </div>
                    </div>

                    <div class="invoice-title" style="margin: 0;">فاتورة للمبيع</div>
                </div>
            </div>

            <!-- Invoice Details Table -->
            <div class="invoice-details">
                <table>
                    <tr>
                        <td class="label">التاريخ</td>
                        <td>{{ date('Y/m/d', strtotime($order['created_at'])) }}</td>
                        <td class="label">رقم الطلب</td>
                        <td>{{ $order->id }}</td>
                    </tr>
                    <tr>
                        <td class="label">المحافظة</td>
                        <td>{{ $shippingAddress->city ?? 'غير محدد' }}</td>
                        <td class="label">العنوان</td>
                        <td style="font-size: 10px;">
                            @if($shippingAddress)
                                {{ $shippingAddress->contact_person_name ?? '' }}
                                @if($shippingAddress->address)
                                    - {{ $shippingAddress->address }}
                            @endif
                                @if($shippingAddress->zip)
                                    - {{ $shippingAddress->zip }}
                        @endif
                            @else
                                عنوان غير محدد
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="label">موبيل للعميل</td>
                        <td colspan="3">{{ $shippingAddress->phone ?? ($order->customer->phone ?? 'غير محدد') }}</td>
                    </tr>
                </table>
            </div>

                        <!-- Order Details Section -->
            <div class="customer-section">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 100%; padding: 10px; border: 1px solid #000; vertical-align: top;">
                            <div class="section-title">بيانات الأوردر</div>
                            <div style="font-size: 12px; line-height: 1.6;">
                                @foreach($order->details as $key=>$details)
                                    @php($productDetails = $details?->product ?? json_decode($details->product_details))
                                    <div style="margin-bottom: 8px; display: inline-block; width: 48%; margin-left: 2%;">
                                        <strong>{{ $productDetails->name ?? 'منتج' }}</strong><br>
                                        <span>سعر: {{ $details['price'] }} - الكمية: {{ $details->qty }}</span><br>
                                        <span>السعر: {{ $details['price'] * $details->qty }} ج</span>
                                    </div>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Totals Section - Single Row with Columns -->
            <div style="margin-top: 20px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 20%; padding: 10px; border: 1px solid #000; text-align: center; background-color: #f0f0f0;">
                            <div style="font-size: 12px; font-weight: bold;">مصاريف الشحن</div>
                            <div style="font-size: 14px; margin-top: 5px;">ج {{ $orderTotalPriceSummary['shippingTotal'] ?? 0 }}</div>
                                            </td>
                        <td style="width: 20%; padding: 10px; border: 1px solid #000; text-align: center; background-color: #f0f0f0;">
                            <div style="font-size: 12px; font-weight: bold;">الخصم الإضافي</div>
                            <div style="font-size: 14px; margin-top: 5px; color: #d63384;">ج {{ $orderTotalPriceSummary['extraDiscount'] ?? 0 }}</div>
                                            </td>
                        <td style="width: 20%; padding: 10px; border: 1px solid #000; text-align: center; background-color: #f0f0f0;">
                            <div style="font-size: 12px; font-weight: bold;">إجمالي الإضافي</div>
                            <div style="font-size: 14px; margin-top: 5px;">ج {{ ($orderTotalPriceSummary['itemPrice'] + $orderTotalPriceSummary['shippingTotal']) ?? 0 }}</div>
                                                </td>
                        <td style="width: 20%; padding: 10px; border: 1px solid #000; text-align: center; background-color: #f0f0f0;">
                            <div style="font-size: 12px; font-weight: bold;">إجمالي الخصم</div>
                            <div style="font-size: 14px; margin-top: 5px; color: #d63384;">ج {{ ($orderTotalPriceSummary['itemDiscount'] + $orderTotalPriceSummary['couponDiscount'] + $orderTotalPriceSummary['extraDiscount']) ?? 0 }}</div>
                                                </td>
                        <td style="width: 20%; padding: 10px; border: 1px solid #000; text-align: center; background-color: #e6f3ff;">
                            <div style="font-size: 12px; font-weight: bold;">صافي قيمة الأوردر</div>
                            <div style="font-size: 16px; font-weight: bold; margin-top: 5px; color: #0066cc;">ج {{ $orderTotalPriceSummary['totalAmount'] ?? 0 }}</div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </body>
</html>