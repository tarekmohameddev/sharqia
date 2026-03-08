<?php

namespace Tests\Unit;

use App\Services\AuthenticityAbuseDetectionService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuthenticityAbuseDetectionServiceTest extends TestCase
{
    private AuthenticityAbuseDetectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->service = new AuthenticityAbuseDetectionService();
    }

    public function test_fresh_user_is_not_blocked(): void
    {
        $this->assertFalse($this->service->isBlocked(1, '127.0.0.1', null));
    }

    public function test_user_blocked_after_threshold_invalid_attempts(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $this->service->recordInvalidAttempt(1, '10.0.0.1', null);
        }

        $this->assertTrue($this->service->isBlocked(1, '10.0.0.2', null));
    }

    public function test_ip_blocked_after_threshold(): void
    {
        $ip = '192.168.1.100';
        for ($i = 0; $i < 100; $i++) {
            $this->service->recordInvalidAttempt(null, $ip, null);
        }

        $this->assertTrue($this->service->isBlocked(null, $ip, null));
    }

    public function test_device_blocked_after_threshold(): void
    {
        $deviceId = 'device-abc-123';
        for ($i = 0; $i < 30; $i++) {
            $this->service->recordInvalidAttempt(null, '10.0.0.1', $deviceId);
        }

        $this->assertTrue($this->service->isBlocked(null, '10.0.0.2', $deviceId));
    }

    public function test_unblock_user_clears_block(): void
    {
        // Trigger a block
        for ($i = 0; $i < 50; $i++) {
            $this->service->recordInvalidAttempt(42, '10.0.0.1', null);
        }
        $this->assertTrue($this->service->isBlocked(42, '10.0.0.99', null));

        $this->service->unblockUser(42);

        $this->assertFalse($this->service->isBlocked(42, '10.0.0.99', null));
    }

    public function test_below_threshold_does_not_block(): void
    {
        for ($i = 0; $i < 49; $i++) {
            $this->service->recordInvalidAttempt(5, '10.0.0.1', null);
        }

        $this->assertFalse($this->service->isBlocked(5, '10.0.0.2', null));
    }
}
