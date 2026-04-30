<?php

namespace App\Http\Controllers\RestAPI\v1;

use App\Http\Controllers\Controller;
use App\Models\AuthenticityCode;
use App\Models\AuthenticityCounterfeitReport;
use App\Models\AuthenticityScanLog;
use App\Services\AuthenticityAbuseDetectionService;
use App\Services\AuthenticityCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthenticityVerifyController extends Controller
{
    public function __construct(
        private readonly AuthenticityAbuseDetectionService $abuseDetection,
        private readonly AuthenticityCodeService $codeService,
    ) {
    }

    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'device_id' => ['nullable', 'string', 'max:255'],
        ]);

        $ip = $request->ip();
        $userId = $request->user()?->id;
        $deviceId = $request->input('device_id');
        $codeEntered = $this->codeService->normalizeCode($request->input('code'));

        // Enforce "digits only, formatted 1234-5678-9012" after normalization.
        if (!preg_match('/^\d{4}-\d{4}-\d{4}$/', $codeEntered)) {
            $this->abuseDetection->recordInvalidAttempt($userId, $ip, $deviceId);
            $this->logScan(null, $codeEntered, $userId, 'invalid', $ip, $request, $deviceId);

            return response()->json([
                'authentic' => false,
                'message' => translate('invalid_code'),
            ], 422);
        }

        // Check if blocked before rate limit (blocked = harder block)
        if ($this->abuseDetection->isBlocked($userId, $ip, $deviceId)) {
            $this->logScan(null, $codeEntered, $userId, 'blocked', $ip, $request, $deviceId);
            return response()->json(['message' => translate('account_temporarily_blocked')], 403);
        }

        $code = AuthenticityCode::where('code', $codeEntered)->first();

        if (!$code) {
            $this->abuseDetection->recordInvalidAttempt($userId, $ip, $deviceId);
            $this->logScan(null, $codeEntered, $userId, 'invalid', $ip, $request, $deviceId);

            return response()->json([
                'authentic' => false,
                'message' => translate('invalid_code'),
            ], 404);
        }

        if ($code->status === 'used') {
            $this->logScan($code->id, $codeEntered, $userId, 'valid_rescan', $ip, $request, $deviceId);

            return response()->json([
                'authentic' => true,
                'first_scan' => false,
                'first_scanned_at' => $code->first_scanned_at?->toDateTimeString(),
                'message' => translate('code_was_previously_verified'),
                'can_report_counterfeit' => true,
            ]);
        }

        // First scan — mark used in a transaction to prevent race conditions
        DB::transaction(function () use ($code, $userId, $ip) {
            $fresh = AuthenticityCode::lockForUpdate()->find($code->id);
            if ($fresh->status === 'unused') {
                $fresh->status = 'used';
                $fresh->first_scanned_at = now();
                $fresh->first_scanned_by_user_id = $userId;
                $fresh->first_scanned_ip = $ip;
                $fresh->save();

                // Refresh the local instance
                $code->status = 'used';
                $code->first_scanned_at = $fresh->first_scanned_at;
            }
        });

        $this->logScan($code->id, $codeEntered, $userId, 'valid_first', $ip, $request, $deviceId);

        return response()->json([
            'authentic' => true,
            'first_scan' => true,
            'message' => translate('product_is_authentic'),
        ]);
    }

    public function reportCounterfeit(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $userId = $request->user()->id;
        $codeEntered = $this->codeService->normalizeCode($request->input('code'));

        if (!preg_match('/^\d{4}-\d{4}-\d{4}$/', $codeEntered)) {
            return response()->json([
                'message' => translate('invalid_code'),
            ], 422);
        }

        $code = AuthenticityCode::where('code', $codeEntered)->first();

        if (!$code) {
            return response()->json([
                'message' => translate('invalid_code'),
            ], 404);
        }

        if ($code->status !== 'used') {
            return response()->json([
                'message' => translate('can_only_report_scanned_codes'),
            ], 422);
        }

        // Prevent duplicate reports from same user for same code
        $existing = AuthenticityCounterfeitReport::where('code_id', $code->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => translate('already_reported_this_code'),
            ]);
        }

        AuthenticityCounterfeitReport::create([
            'code_id' => $code->id,
            'user_id' => $userId,
            'notes' => $request->input('notes'),
            'reported_at' => now(),
        ]);

        return response()->json([
            'message' => translate('counterfeit_report_submitted'),
        ]);
    }

    private function logScan(
        ?int $codeId,
        string $codeEntered,
        ?int $userId,
        string $result,
        string $ip,
        Request $request,
        ?string $deviceId
    ): void {
        AuthenticityScanLog::create([
            'code_id' => $codeId,
            'code_entered' => $codeEntered,
            'user_id' => $userId,
            'result' => $result,
            'ip_address' => $ip,
            'user_agent' => substr($request->userAgent() ?? '', 0, 500),
            'device_id' => $deviceId,
        ]);
    }
}
