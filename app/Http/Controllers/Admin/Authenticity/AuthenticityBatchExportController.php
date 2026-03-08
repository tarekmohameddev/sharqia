<?php

namespace App\Http\Controllers\Admin\Authenticity;

use App\Http\Controllers\BaseController;
use App\Models\AuthenticityCode;
use App\Models\AuthenticityCodeBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class AuthenticityBatchExportController extends BaseController
{
    private const CARDS_PER_ROW = 4;
    private const ROWS_PER_PAGE = 7;
    private const CARDS_PER_PAGE = 28;

    public function index(?Request $request, ?string $type = null): RedirectResponse
    {
        return redirect()->route('admin.authenticity.batches.index');
    }

    public function exportPdf(int $batchId): Response
    {
        $batch = AuthenticityCodeBatch::findOrFail($batchId);

        $codes = $batch->codes()->orderBy('id')->get();

        if ($codes->isEmpty()) {
            abort(404, 'No codes in this batch');
        }

        // Chunk codes into pages of 28 cards each
        $pages = $codes->chunk(self::CARDS_PER_PAGE);

        $html = view('admin-views.authenticity.print-batch', compact('batch', 'pages', 'codes'))->render();

        $pdf = app('dompdf.wrapper');
        $pdf->loadHTML($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

        $filename = "scratch-cards-{$batch->batch_number}.pdf";

        return $pdf->download($filename);
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
}
