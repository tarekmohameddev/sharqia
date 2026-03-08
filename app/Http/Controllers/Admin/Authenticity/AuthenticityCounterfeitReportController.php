<?php

namespace App\Http\Controllers\Admin\Authenticity;

use App\Http\Controllers\BaseController;
use App\Models\AuthenticityCounterfeitReport;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AuthenticityCounterfeitReportController extends BaseController
{
    public function index(?Request $request, ?string $type = null): View
    {
        $filter = $request?->get('filter') ?? 'all';

        $query = AuthenticityCounterfeitReport::with(['code', 'user'])
            ->when($filter === 'pending', fn($q) => $q->whereNull('admin_reviewed_at'))
            ->when($filter === 'reviewed', fn($q) => $q->whereNotNull('admin_reviewed_at'))
            ->latest('reported_at');

        $reports = $query->paginate(20)->withQueryString();

        return view('admin-views.authenticity.counterfeit-reports', compact('reports', 'filter'));
    }

    public function review(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $report = AuthenticityCounterfeitReport::findOrFail($id);
        $report->admin_reviewed_at = now();
        $report->admin_notes = $request->input('admin_notes');
        $report->save();

        ToastMagic::success(translate('report_marked_as_reviewed'));
        return back();
    }
}
