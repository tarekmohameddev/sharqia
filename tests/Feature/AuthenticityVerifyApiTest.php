<?php

namespace Tests\Feature;

use App\Models\AuthenticityCode;
use App\Models\AuthenticityCodeBatch;
use App\Models\AuthenticityCounterfeitReport;
use App\Models\AuthenticityScanLog;
use App\Models\User;
use App\Services\AuthenticityCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuthenticityVerifyApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private AuthenticityCodeBatch $batch;
    private AuthenticityCode $code;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->user = User::factory()->create();

        $service = new AuthenticityCodeService();
        $this->batch = $service->generateBatch(5);
        $this->code = AuthenticityCode::where('batch_id', $this->batch->id)->first();
    }

    private function actingAsCustomer(): static
    {
        return $this->actingAs($this->user, 'api');
    }

    // -------------------------------------------------------------------------
    // Verify endpoint
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->postJson('/api/v1/authenticity/verify', [
            'code' => $this->code->code,
        ]);

        $response->assertStatus(401);
    }

    public function test_valid_first_scan_marks_code_used_and_returns_authentic(): void
    {
        $response = $this->actingAsCustomer()->postJson('/api/v1/authenticity/verify', [
            'code' => $this->code->code,
        ]);

        $response->assertOk()
            ->assertJson([
                'authentic' => true,
                'first_scan' => true,
            ]);

        $this->assertDatabaseHas('authenticity_codes', [
            'id' => $this->code->id,
            'status' => 'used',
        ]);

        $this->assertDatabaseHas('authenticity_scan_logs', [
            'code_id' => $this->code->id,
            'result' => 'valid_first',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_rescan_returns_already_scanned_response(): void
    {
        // First scan
        $this->actingAsCustomer()->postJson('/api/v1/authenticity/verify', [
            'code' => $this->code->code,
        ]);

        // Second scan (re-scan)
        $response = $this->actingAsCustomer()->postJson('/api/v1/authenticity/verify', [
            'code' => $this->code->code,
        ]);

        $response->assertOk()
            ->assertJson([
                'authentic' => true,
                'first_scan' => false,
                'can_report_counterfeit' => true,
            ])
            ->assertJsonStructure(['first_scanned_at']);

        $this->assertDatabaseHas('authenticity_scan_logs', [
            'code_id' => $this->code->id,
            'result' => 'valid_rescan',
        ]);
    }

    public function test_invalid_code_returns_404(): void
    {
        $response = $this->actingAsCustomer()->postJson('/api/v1/authenticity/verify', [
            'code' => '9999-9999-9999',
        ]);

        $response->assertStatus(404)
            ->assertJson(['authentic' => false]);

        $this->assertDatabaseHas('authenticity_scan_logs', [
            'result' => 'invalid',
            'code_entered' => '9999-9999-9999',
        ]);
    }

    public function test_code_without_dashes_is_accepted(): void
    {
        // User inputs the 12 digits without separators; server normalizes to 1234-5678-9012
        $noDashes = str_replace('-', '', $this->code->code);

        $response = $this->actingAsCustomer()->postJson('/api/v1/authenticity/verify', [
            'code' => $noDashes,
        ]);

        $response->assertOk()->assertJson(['authentic' => true]);
    }

    public function test_code_with_spaces_instead_of_dashes_is_accepted(): void
    {
        // Allow spaces; will be normalized back to 12 digits
        $withSpaces = substr($this->code->code, 0, 4) . ' ' . substr($this->code->code, 4, 4) . ' ' . substr($this->code->code, 8, 4);

        $response = $this->actingAsCustomer()->postJson('/api/v1/authenticity/verify', [
            'code' => $withSpaces,
        ]);

        $response->assertOk()->assertJson(['authentic' => true]);
    }

    public function test_non_12_digit_code_returns_422(): void
    {
        $response = $this->actingAsCustomer()->postJson('/api/v1/authenticity/verify', [
            'code' => '12345ABCDE',
        ]);

        $response->assertStatus(422)
            ->assertJson(['authentic' => false]);
    }

    public function test_blocked_user_returns_403(): void
    {
        // Manually set the block cache key
        Cache::put("authenticity_block_user:{$this->user->id}", true, 3600);

        $response = $this->actingAsCustomer()->postJson('/api/v1/authenticity/verify', [
            'code' => $this->code->code,
        ]);

        $response->assertStatus(403);

        $this->assertDatabaseHas('authenticity_scan_logs', [
            'result' => 'blocked',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_code_required_validation(): void
    {
        $response = $this->actingAsCustomer()->postJson('/api/v1/authenticity/verify', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Report counterfeit endpoint
    // -------------------------------------------------------------------------

    public function test_report_counterfeit_requires_auth(): void
    {
        $response = $this->postJson('/api/v1/authenticity/report-counterfeit', [
            'code' => $this->code->code,
        ]);

        $response->assertStatus(401);
    }

    public function test_report_counterfeit_for_used_code(): void
    {
        // Mark code as used first
        $this->code->update([
            'status' => 'used',
            'first_scanned_at' => now(),
            'first_scanned_by_user_id' => $this->user->id,
            'first_scanned_ip' => '127.0.0.1',
        ]);

        $response = $this->actingAsCustomer()->postJson('/api/v1/authenticity/report-counterfeit', [
            'code' => $this->code->code,
            'notes' => 'Suspicious product packaging',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('authenticity_counterfeit_reports', [
            'code_id' => $this->code->id,
            'user_id' => $this->user->id,
            'notes' => 'Suspicious product packaging',
        ]);
    }

    public function test_cannot_report_unused_code(): void
    {
        $response = $this->actingAsCustomer()->postJson('/api/v1/authenticity/report-counterfeit', [
            'code' => $this->code->code,
        ]);

        $response->assertStatus(422);
    }

    public function test_report_invalid_code_returns_404(): void
    {
        $response = $this->actingAsCustomer()->postJson('/api/v1/authenticity/report-counterfeit', [
            'code' => '9999-9999-9999',
        ]);

        $response->assertStatus(404);
    }

    public function test_duplicate_report_from_same_user_returns_ok_without_duplicate(): void
    {
        $this->code->update([
            'status' => 'used',
            'first_scanned_at' => now(),
            'first_scanned_by_user_id' => $this->user->id,
            'first_scanned_ip' => '127.0.0.1',
        ]);

        $this->actingAsCustomer()->postJson('/api/v1/authenticity/report-counterfeit', [
            'code' => $this->code->code,
        ]);

        // Second report from same user
        $this->actingAsCustomer()->postJson('/api/v1/authenticity/report-counterfeit', [
            'code' => $this->code->code,
        ]);

        $this->assertSame(1, AuthenticityCounterfeitReport::where('code_id', $this->code->id)
            ->where('user_id', $this->user->id)
            ->count());
    }

    // -------------------------------------------------------------------------
    // Race condition safety
    // -------------------------------------------------------------------------

    public function test_concurrent_first_scans_result_in_single_valid_first(): void
    {
        // Simulate two rapid scan requests for the same unused code
        // by directly calling verify twice synchronously.
        // The transaction + lockForUpdate prevents both from marking valid_first.

        $response1 = $this->actingAsCustomer()->postJson('/api/v1/authenticity/verify', [
            'code' => $this->code->code,
        ]);
        $response2 = $this->actingAsCustomer()->postJson('/api/v1/authenticity/verify', [
            'code' => $this->code->code,
        ]);

        $response1->assertOk()->assertJson(['first_scan' => true]);
        $response2->assertOk()->assertJson(['first_scan' => false]);

        // Only one "valid_first" log entry
        $this->assertSame(1, AuthenticityScanLog::where('code_id', $this->code->id)
            ->where('result', 'valid_first')
            ->count());
    }
}
