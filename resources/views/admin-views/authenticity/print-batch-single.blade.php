<!DOCTYPE html>
<html lang="ar" dir="ltr">
<head>
    <meta charset="UTF-8">
    <title>{{ $batch->batch_number }} — Single Cards</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        @page {
            size: 50mm 30mm;
            margin: 0;
        }

        html, body {
            width: 50mm;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
        }

        .card {
            width: 50mm;
            height: 30mm;
            overflow: hidden;
            page-break-inside: avoid;
            position: relative;
        }

        .card-header {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4mm;
            border-bottom: 0.3pt solid #888;
            text-align: center;
            line-height: 4mm;
            font-size: 4pt;
            letter-spacing: 0.5pt;
            color: #333;
            text-transform: uppercase;
            overflow: hidden;
        }

        .card-code {
            position: absolute;
            top: 4mm;
            left: 0;
            right: 0;
            height: 7mm;
            text-align: center;
            line-height: 7mm;
            font-family: 'Courier New', Courier, monospace;
            font-size: 8.5pt;
            font-weight: bold;
            letter-spacing: 1pt;
            color: #000;
            overflow: hidden;
        }

        .card-barcode {
            position: absolute;
            top: 11mm;
            left: 0;
            right: 0;
            height: 8mm;
            text-align: center;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-barcode img {
            width: 46mm;
            height: 8mm;
            display: block;
            margin: 0 auto;
            object-fit: contain;
        }

        .card-footer {
            position: absolute;
            top: 19mm;
            left: 0;
            right: 0;
            height: 11mm;
            border-top: 0.3pt solid #aaa;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 0.8mm;
            box-sizing: border-box;
        }

        .warning-text {
            font-size: 9pt;
            font-weight: 700;
            color: #111;
            font-family: 'DejaVu Sans', sans-serif;
            direction: rtl;
            unicode-bidi: plaintext;
            text-align: center;
            line-height: 1.1;
            display: block;
            width: 100%;
        }
    </style>
</head>
<body>

@foreach($codes as $code)
    <div class="card">
        <div class="card-header">للتحقق من المنتج </div>
        <div class="card-code">{{ $code->code }}</div>
        <div class="card-barcode">
            <img
                src="data:image/png;base64,{{ DNS1D::getBarcodePNG($code->code, 'C128', 1.3, 34, [0,0,0], true) }}"
                alt="barcode"
            />
        </div>
        <div class="card-footer">
            <span class="warning-text">لحمايتك من أي تلاعب أو تقليد، الكود صالح 4 ساعات فقط من أول استخدام</span>
        </div>
    </div>
    @if(!$loop->last)
        <pagebreak />
    @endif
@endforeach

</body>
</html>
