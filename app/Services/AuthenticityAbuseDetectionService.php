<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class AuthenticityAbuseDetectionService
{
    private const USER_INVALID_THRESHOLD = 50;
    private const IP_INVALID_THRESHOLD = 100;
    private const DEVICE_INVALID_THRESHOLD = 30;
    private const WINDOW_MINUTES = 60;
    private const BLOCK_DURATION_MINUTES = 1440; // 24 hours

    public function isBlocked(
        ?int $userId,
        string $ip,
        ?string $deviceId
    ): bool {
        if ($userId && Cache::has("authenticity_block_user:{$userId}")) {
            return true;
        }

        if (Cache::has("authenticity_block_ip:{$ip}")) {
            return true;
        }

        if ($deviceId && Cache::has("authenticity_block_device:{$deviceId}")) {
            return true;
        }

        return false;
    }

    public function recordInvalidAttempt(
        ?int $userId,
        string $ip,
        ?string $deviceId
    ): void {
        $window = self::WINDOW_MINUTES * 60;

        if ($userId) {
            $userKey = "authenticity_invalid_user:{$userId}";
            $userCount = (int) Cache::get($userKey, 0) + 1;
            Cache::put($userKey, $userCount, $window);

            if ($userCount >= self::USER_INVALID_THRESHOLD) {
                Cache::put(
                    "authenticity_block_user:{$userId}",
                    true,
                    self::BLOCK_DURATION_MINUTES * 60
                );
            }
        }

        $ipKey = "authenticity_invalid_ip:{$ip}";
        $ipCount = (int) Cache::get($ipKey, 0) + 1;
        Cache::put($ipKey, $ipCount, $window);

        if ($ipCount >= self::IP_INVALID_THRESHOLD) {
            Cache::put(
                "authenticity_block_ip:{$ip}",
                true,
                self::BLOCK_DURATION_MINUTES * 60
            );
        }

        if ($deviceId) {
            $deviceKey = "authenticity_invalid_device:{$deviceId}";
            $deviceCount = (int) Cache::get($deviceKey, 0) + 1;
            Cache::put($deviceKey, $deviceCount, $window);

            if ($deviceCount >= self::DEVICE_INVALID_THRESHOLD) {
                Cache::put(
                    "authenticity_block_device:{$deviceId}",
                    true,
                    self::BLOCK_DURATION_MINUTES * 60
                );
            }
        }
    }

    public function unblockUser(int $userId): void
    {
        Cache::forget("authenticity_block_user:{$userId}");
        Cache::forget("authenticity_invalid_user:{$userId}");
    }

    public function unblockIp(string $ip): void
    {
        Cache::forget("authenticity_block_ip:{$ip}");
        Cache::forget("authenticity_invalid_ip:{$ip}");
    }
}
