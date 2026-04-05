<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $batch->batch_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        @page {
            size: 320mm 234mm;
            margin: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #e8e8e8;
        }

        /* ── Screen preview ── */
        @media screen {
            body {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 16px;
                padding: 24px;
            }

            .page {
                background: #fff;
                box-shadow: 0 4px 16px rgba(0,0,0,.25);
            }
        }

        /* ── Shared page shell ── */
        .page {
            width: 320mm;
            height: 234mm;
            overflow: hidden;
            page-break-after: always;
        }

        .page:last-child {
            page-break-after: avoid;
        }

        /* ── Card grid: 6 cols × 7 rows, 4mm gap ── */
        .card-grid {
            display: grid;
            grid-template-columns: repeat(6, 50mm);
            grid-template-rows: repeat(7, 30mm);
            gap: 4mm;
            width: 320mm;
            height: 234mm;
        }

        /* ── Individual card: exactly 50×30mm ── */
        .scratch-card {
            width: 50mm;
            height: 30mm;
            border: 0.4pt solid #000;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: #fff;
        }

        .card-empty {
            width: 50mm;
            height: 30mm;
        }

        /* Card header */
        .card-header {
            height: 5mm;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 0.3pt solid #888;
            flex-shrink: 0;
        }

        .card-header span {
            font-size: 4.5pt;
            letter-spacing: 0.5pt;
            color: #333;
            text-transform: uppercase;
        }

        /* Code area */
        .card-code {
            height: 7mm;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .card-code span {
            font-family: 'Courier New', Courier, monospace;
            font-size: 8.5pt;
            font-weight: bold;
            letter-spacing: 1pt;
            color: #000;
        }

        /* Barcode */
        .card-barcode {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 0 1mm;
        }

        .card-barcode svg {
            max-width: 46mm;
            height: 10mm;
            display: block;
        }

        /* Footer */
        .card-footer {
            height: 7mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.5mm;
            border-top: 0.3pt solid #aaa;
            flex-shrink: 0;
            padding: 0.5mm 1mm;
        }

        .card-footer .batch-number {
            font-size: 4pt;
            color: #555;
            letter-spacing: 0.3pt;
        }

        .card-footer .warning-text {
            font-size: 3.5pt;
            color: #333;
            text-align: center;
            direction: rtl;
            line-height: 1.3;
        }

        /* ── Print overrides ── */
        @media print {
            body {
                background: none;
                display: block;
                padding: 0;
            }

            .page {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>

@foreach($pages as $pageIndex => $pageCodes)
    <div class="page">
        <div class="card-grid">
            @foreach($pageCodes as $code)
                <div class="scratch-card">
                    <div class="card-header">
                        <span>Authenticity Card</span>
                    </div>
                    <div class="card-code">
                        <span>{{ $code->code }}</span>
                    </div>
                    <div class="card-barcode">
                        {!! DNS1D::getBarcodeHTML($code->code, 'C128', 1.0, 34, '#000000', false) !!}
                    </div>
                    <div class="card-footer">
                        <span class="batch-number">{{ $batch->batch_number }}</span>
                        <span class="warning-text">لحمايتك من أي تلاعب أو تقليد، الكود صالح 4 ساعات فقط من أول استخدام</span>
                    </div>
                </div>
            @endforeach
            {{-- Fill empty cells so the grid stays intact on the last page --}}
            @for($i = count($pageCodes); $i < 42; $i++)
                <div class="card-empty"></div>
            @endfor
        </div>
    </div>
@endforeach

<script>
    window.onload = function () { window.print(); };
</script>
</body>
</html>
