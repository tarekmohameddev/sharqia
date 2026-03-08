<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scratch Cards — {{ $batch->batch_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            background: #fff;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 8mm;
            page-break-after: always;
        }

        .page:last-child {
            page-break-after: avoid;
        }

        .grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }

        .grid-row {
            display: table-row;
        }

        .card-cell {
            display: table-cell;
            width: 25%;
            padding: 3mm;
        }

        .scratch-card {
            border: 2px solid #333;
            border-radius: 6px;
            overflow: hidden;
            height: 38mm;
            display: flex;
            flex-direction: column;
            background: #fff;
        }

        .card-header-strip {
            background: #1a1a2e;
            color: #fff;
            text-align: center;
            padding: 2mm 1mm;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .card-body-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2mm;
        }

        .scratch-area {
            background: repeating-linear-gradient(
                45deg,
                #e0e0e0,
                #e0e0e0 2px,
                #c8c8c8 2px,
                #c8c8c8 4px
            );
            border: 1px dashed #999;
            border-radius: 3px;
            width: 100%;
            padding: 3mm 2mm;
            text-align: center;
            font-size: 7px;
            color: #555;
            margin-bottom: 2mm;
            position: relative;
        }

        .hidden-code {
            font-family: 'Courier New', monospace;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 2px;
            color: #1a1a2e;
        }

        .barcode-area {
            width: 100%;
            text-align: center;
            overflow: hidden;
        }

        .barcode-area svg {
            max-width: 100%;
            height: 12mm;
        }

        .card-footer-strip {
            background: #f5f5f5;
            text-align: center;
            padding: 1mm;
            font-size: 6px;
            color: #666;
            border-top: 1px solid #ddd;
        }

        @page {
            margin: 0;
            size: A4 portrait;
        }

        @media print {
            body { margin: 0; }
            .page { page-break-after: always; }
        }
    </style>
</head>
<body>

@foreach($pages as $pageIndex => $pageCodes)
    <div class="page">
        <div class="grid">
            @foreach($pageCodes->chunk(4) as $rowCodes)
                <div class="grid-row">
                    @foreach($rowCodes as $code)
                        <div class="card-cell">
                            <div class="scratch-card">
                                <div class="card-header-strip">✓ AUTHENTICITY CARD</div>
                                <div class="card-body-area">
                                    <div class="scratch-area">
                                        <div style="font-size:6px; margin-bottom:1mm;">▼ SCRATCH HERE ▼</div>
                                        <div class="hidden-code">{{ $code->code }}</div>
                                    </div>
                                    <div class="barcode-area">
                                        {!! DNS1D::getBarcodeHTML($code->code, 'C128', 1.2, 28, '#000000', false) !!}
                                    </div>
                                </div>
                                <div class="card-footer-strip">
                                    {{ $batch->batch_number }} &bull; Scan to verify authenticity
                                </div>
                            </div>
                        </div>
                    @endforeach
                    {{-- Fill empty cells if row is not complete --}}
                    @for($i = count($rowCodes); $i < 4; $i++)
                        <div class="card-cell"></div>
                    @endfor
                </div>
            @endforeach
        </div>
    </div>
@endforeach

</body>
</html>
