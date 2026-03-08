<?php

namespace App\Http\Controllers\Admin\Authenticity;

use App\Http\Controllers\BaseController;
use App\Models\AuthenticityScanLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AuthenticityAuditLogController extends BaseController
{
    public function index(?Request $request, ?string $type = null): View
    {
        $result = $request?->get('result');
        $userId = $request?->get('user_id');
        $search = $request?->get('search');

        $query = AuthenticityScanLog::with(['code', 'user'])
            ->when($result, fn($q) => $q->where('result', $result))
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->when($search, fn($q) => $q->where('code_entered', 'like', "%{$search}%")
                ->orWhere('ip_address', 'like', "%{$search}%"))
            ->latest('created_at');

        $logs = $query->paginate(50)->withQueryString();

        $results = ['valid_first', 'valid_rescan', 'invalid', 'rate_limited', 'blocked'];

        return view('admin-views.authenticity.audit-logs', compact('logs', 'results', 'result', 'search'));
    }
}
