<?php

namespace App\Http\Controllers\Admin\Authenticity;

use App\Http\Controllers\BaseController;
use App\Models\AuthenticityCode;
use App\Models\AuthenticityCodeBatch;
use App\Services\AuthenticityCodeService;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AuthenticityBatchController extends BaseController
{
    public function __construct(
        private readonly AuthenticityCodeService $codeService,
    ) {
    }

    public function index(?Request $request, ?string $type = null): View
    {
        $batches = AuthenticityCodeBatch::withCount([
            'codes',
            'codes as used_count' => fn($q) => $q->where('status', 'used'),
            'codes as unused_count' => fn($q) => $q->where('status', 'unused'),
        ])->latest()->paginate(20);

        return view('admin-views.authenticity.batches.index', compact('batches'));
    }

    public function create(): View
    {
        return view('admin-views.authenticity.batches.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        try {
            $batch = $this->codeService->generateBatch((int) $request->input('count'));
            ToastMagic::success(translate('batch_generated_successfully'));
            return redirect()->route('admin.authenticity.batches.show', $batch->id);
        } catch (\Exception $e) {
            ToastMagic::error($e->getMessage());
            return back()->withInput();
        }
    }

    public function show(int $id): View
    {
        $batch = AuthenticityCodeBatch::findOrFail($id);

        $usedCount = $batch->codes()->where('status', 'used')->count();
        $unusedCount = $batch->codes()->where('status', 'unused')->count();

        $codes = $batch->codes()->with('firstScannedBy')->latest()->paginate(50);

        return view('admin-views.authenticity.batches.show', compact('batch', 'usedCount', 'unusedCount', 'codes'));
    }
}
