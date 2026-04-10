<?php

namespace App\Http\Controllers\Admin\Authenticity;

use App\Http\Controllers\BaseController;
use App\Models\AuthenticityCode;
use App\Models\AuthenticityCodeBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class AuthenticityBatchExportController extends BaseController
{
    private const CARDS_PER_ROW = 6;
    private const ROWS_PER_PAGE = 7;
    private const CARDS_PER_PAGE = 42;

    // 320mm x 234mm in PostScript points (1mm = 2.8346pt)
    private const PAPER_WIDTH_PT  = 907.09;
    private const PAPER_HEIGHT_PT = 663.31;

    public function index(?Request $request, ?string $type = null): RedirectResponse
    {
        return redirect()->route('admin.authenticity.batches.index');
    }

    public function printPreview(int $batchId): \Illuminate\Contracts\View\View
    {
        $batch = AuthenticityCodeBatch::findOrFail($batchId);

        $codes = $batch->codes()->orderBy('id')->get();

        if ($codes->isEmpty()) {
            abort(404, 'No codes in this batch');
        }

        $pages = $codes->chunk(self::CARDS_PER_PAGE);

        return view('admin-views.authenticity.print-batch', compact('batch', 'pages', 'codes'));
    }

    public function exportPdf(int $batchId): Response
    {
        $batch = AuthenticityCodeBatch::findOrFail($batchId);

        $codes = $batch->codes()->orderBy('id')->get();

        if ($codes->isEmpty()) {
            abort(404, 'No codes in this batch');
        }

        $pages = $codes->chunk(self::CARDS_PER_PAGE);

        $html = view('admin-views.authenticity.print-batch', compact('batch', 'pages', 'codes'))->render();

        $pdf = app('dompdf.wrapper');
        $pdf->loadHTML($html);
        $pdf->setPaper([0, 0, self::PAPER_WIDTH_PT, self::PAPER_HEIGHT_PT]);
        $pdf->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

        $filename = "scratch-cards-{$batch->batch_number}.pdf";

        return $pdf->download($filename);
    }

    public function exportPdfSingle(int $batchId): Response
    {
        $batch = AuthenticityCodeBatch::findOrFail($batchId);
        $codes  = $batch->codes()->orderBy('id')->get();

        if ($codes->isEmpty()) {
            abort(404, 'No codes in this batch');
        }

        // mPDF has much better RTL/Arabic support than Dompdf (letter shaping + bidi).
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => [50, 30], // mm
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'default_font' => 'dejavusans',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);

        $warningText = 'لحمايتك من أي تلاعب أو تقليد، الكود صالح 4 ساعات فقط من أول استخدام';

        foreach ($codes as $index => $code) {
            if ($index > 0) {
                $mpdf->AddPage();
            }

            $barcodeBase64 = \DNS1D::getBarcodePNG($code->code, 'C128', 1.3, 34, [0, 0, 0], true);
            $barcodeSrc = 'data:image/png;base64,' . $barcodeBase64;

            // Use fixed-position layout to guarantee no pagination spill.
            // Page is exactly 50×30mm with 0 margins.
            $cardHtml = '
                <div style="position:relative; width:50mm; height:30mm; overflow:hidden; font-family:DejaVu Sans, Arial, Helvetica, sans-serif;">
                    <div style="position:absolute; left:0; right:0; top:4mm; height:7mm; line-height:7mm; text-align:center; font-family:\'Courier New\', Courier, monospace; font-size:8.5pt; font-weight:bold; letter-spacing:1pt; color:#000; overflow:hidden;">
                        ' . e($code->code) . '
                    </div>
                    <div style="position:absolute; left:0; right:0; top:11mm; height:13mm; text-align:center; overflow:hidden;">
                        <img src="' . e($barcodeSrc) . '" style="width:48mm; height:11mm; display:block; margin:1mm auto 0;" alt="barcode" />
                    </div>
                    <div style="position:absolute; left:0; right:0; top:24mm; height:6mm; line-height:6mm; text-align:center; font-size:3pt; color:#333; border-top:0.3pt solid #aaa; direction:rtl; unicode-bidi:plaintext; white-space:nowrap; overflow:hidden;">
                        ' . e($warningText) . '
                    </div>
                </div>
            ';

            // Render directly onto the current page (50×30mm, 0 margins).
            // Using WriteHTML avoids the extra blank page sometimes produced by WriteFixedPosHTML.
            $mpdf->WriteHTML($cardHtml);
        }

        $filename = "scratch-cards-single-{$batch->batch_number}.pdf";
        $pdfBinary = $mpdf->Output($filename, Destination::STRING_RETURN);

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportZip(int $batchId): StreamedResponse
    {
        $batch = AuthenticityCodeBatch::findOrFail($batchId);
        $codes  = $batch->codes()->orderBy('id')->get();

        if ($codes->isEmpty()) {
            abort(404, 'No codes in this batch');
        }

        $zipPath   = tempnam(sys_get_temp_dir(), 'auth_zip_') . '.zip';

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($codes as $code) {
            $barcodeSvg = \DNS1D::getBarcodeSVG($code->code, 'C128', 2, 108, 'black', false, true);
            $cardSvg  = $this->buildCardSvg($code->code, $batch->batch_number, $barcodeSvg);
            $filename = $code->code . '.svg';
            $zip->addFromString($filename, $cardSvg);
        }

        $zip->close();

        $downloadName = "cards-{$batch->batch_number}.zip";

        return response()->streamDownload(static function () use ($zipPath): void {
            readfile($zipPath);
            @unlink($zipPath);
        }, $downloadName, ['Content-Type' => 'application/zip']);
    }

    public function exportCsv(int $batchId): Response
    {
        $batch = AuthenticityCodeBatch::findOrFail($batchId);

        $codes = $batch->codes()->orderBy('id')->get();

        $csvLines = ['Code,Status,First Scanned At'];
        foreach ($codes as $code) {
            $csvLines[] = implode(',', [
                $code->code,
                $code->status,
                $code->first_scanned_at?->toDateTimeString() ?? '',
            ]);
        }

        $csv = implode("\n", $csvLines);
        $filename = "batch-{$batch->batch_number}-codes.csv";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Build a single 50×30mm card as a standalone SVG string.
     * The card SVG can be opened in Inkscape, Illustrator, or any laser-cutter software.
     */
    private function buildCardSvg(string $code, string $batchNumber, string $barcodeSvgFull): string
    {
        // Extract the viewBox from the barcode SVG, or derive it from width/height attributes
        if (preg_match('/viewBox=["\']([^"\']+)["\']/', $barcodeSvgFull, $vbMatch)) {
            $barcodeViewBox = $vbMatch[1];
        } else {
            preg_match('/width=["\'](\d+(?:\.\d+)?)["\']/', $barcodeSvgFull, $wMatch);
            preg_match('/height=["\'](\d+(?:\.\d+)?)["\']/', $barcodeSvgFull, $hMatch);
            $barcodeViewBox = '0 0 ' . ($wMatch[1] ?? '200') . ' ' . ($hMatch[1] ?? '40');
        }

        // Extract inner bar elements (everything between the <svg> wrapper tags)
        preg_match('/<svg[^>]*>(.*?)<\/svg>/s', $barcodeSvgFull, $innerMatch);
        $barcodeInner = $innerMatch[1] ?? '';

        $safeCode    = htmlspecialchars($code, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $safeBatch   = htmlspecialchars($batchNumber, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $warningText = htmlspecialchars(
            'لحمايتك من أي تلاعب أو تقليد، الكود صالح 4 ساعات فقط من أول استخدام',
            ENT_XML1 | ENT_COMPAT,
            'UTF-8'
        );

        // viewBox: 500 × 300 units → 1 unit = 0.1 mm
        // Zones: header 0-50 | code 50-120 | barcode 120-228 | footer 228-300
        return <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="50mm" height="30mm" viewBox="0 0 500 300">
  <rect x="0.5" y="0.5" width="499" height="299" fill="white" stroke="black" stroke-width="1"/>
  <line x1="0" y1="50" x2="500" y2="50" stroke="#888" stroke-width="0.5"/>
  <text x="250" y="25" text-anchor="middle" dominant-baseline="middle"
        font-family="Arial,Helvetica,sans-serif" font-size="12" fill="#333" letter-spacing="2">الشرقية بيور</text>
  <text x="250" y="87" text-anchor="middle" dominant-baseline="middle"
        font-family="'Courier New',Courier,monospace" font-size="22" font-weight="bold" fill="#000">{$safeCode}</text>
  <svg x="20" y="118" width="460" height="108" viewBox="{$barcodeViewBox}" preserveAspectRatio="none">
    {$barcodeInner}
  </svg>
  <line x1="0" y1="228" x2="500" y2="228" stroke="#aaa" stroke-width="0.5"/>
  <text x="250" y="276" text-anchor="middle" dominant-baseline="middle"
        font-family="Arial,Helvetica,sans-serif" font-size="9" fill="#333" direction="rtl">{$warningText}</text>
</svg>
SVG;
    }
}
