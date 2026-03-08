<?php

namespace App\Http\Controllers\Admin\Authenticity;

use App\Http\Controllers\BaseController;
use App\Models\AuthenticityCode;
use App\Models\AuthenticityCodeBatch;
use App\Models\AuthenticityCounterfeitReport;
use App\Models\AuthenticityScanLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AuthenticityDashboardController extends BaseController
{
    public function index(?Request $request, ?string $type = null): View
    {
        $stats = [
            'total_batches' => AuthenticityCodeBatch::count(),
            'total_codes' => AuthenticityCode::count(),
            'used_codes' => AuthenticityCode::where('status', 'used')->count(),
            'unused_codes' => AuthenticityCode::where('status', 'unused')->count(),
            'total_scans' => AuthenticityScanLog::count(),
            'valid_scans' => AuthenticityScanLog::whereIn('result', ['valid_first', 'valid_rescan'])->count(),
            'invalid_scans' => AuthenticityScanLog::where('result', 'invalid')->count(),
            'blocked_attempts' => AuthenticityScanLog::whereIn('result', ['rate_limited', 'blocked'])->count(),
            'pending_reports' => AuthenticityCounterfeitReport::whereNull('admin_reviewed_at')->count(),
            'total_reports' => AuthenticityCounterfeitReport::count(),
        ];

        $recentScans = AuthenticityScanLog::with(['code', 'user'])
            ->latest('created_at')
            ->limit(10)
            ->get();

        $recentBatches = AuthenticityCodeBatch::latest()->limit(5)->get();

        return view('admin-views.authenticity.index', compact('stats', 'recentScans', 'recentBatches'));
    }
}
