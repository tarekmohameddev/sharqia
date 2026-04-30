<?php

namespace Tests\Unit;

use App\Models\AuthenticityCode;
use App\Models\AuthenticityCodeBatch;
use App\Services\AuthenticityCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticityCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthenticityCodeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AuthenticityCodeService();
    }

    public function test_generated_code_has_correct_format(): void
    {
        $code = $this->service->generateCode();
        $this->assertSame(14, strlen($code));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{4}-\d{4}$/', $code);
    }

    public function test_generated_code_is_digits_only_with_dashes(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $code = $this->service->generateCode();
            $this->assertMatchesRegularExpression('/^\d{4}-\d{4}-\d{4}$/', $code);
        }
    }

    public function test_normalize_code_accepts_code_with_spaces(): void
    {
        $normalized = $this->service->normalizeCode('1234 5678 9012');
        $this->assertSame('1234-5678-9012', $normalized);
    }

    public function test_normalize_code_accepts_code_with_dashes(): void
    {
        $normalized = $this->service->normalizeCode('1234-5678-9012');
        $this->assertSame('1234-5678-9012', $normalized);
    }

    public function test_normalize_code_accepts_plain_12_digits(): void
    {
        $normalized = $this->service->normalizeCode('123456789012');
        $this->assertSame('1234-5678-9012', $normalized);
    }

    public function test_generate_batch_creates_batch_and_codes(): void
    {
        $batch = $this->service->generateBatch(10);

        $this->assertInstanceOf(AuthenticityCodeBatch::class, $batch);
        $this->assertSame(10, $batch->total_count);
        $this->assertStringStartsWith('BATCH-', $batch->batch_number);
        $this->assertSame(10, AuthenticityCode::where('batch_id', $batch->id)->count());
    }

    public function test_batch_codes_are_all_unique(): void
    {
        $batch = $this->service->generateBatch(50);
        $codes = AuthenticityCode::where('batch_id', $batch->id)->pluck('code')->toArray();

        $this->assertSame(count(array_unique($codes)), count($codes));
    }

    public function test_codes_start_as_unused(): void
    {
        $batch = $this->service->generateBatch(5);
        $unusedCount = AuthenticityCode::where('batch_id', $batch->id)
            ->where('status', 'unused')
            ->count();

        $this->assertSame(5, $unusedCount);
    }

    public function test_batch_number_format(): void
    {
        $batch = $this->service->generateBatch(1);
        $year = now()->year;

        $this->assertMatchesRegularExpression("/^BATCH-{$year}-\d{4}$/", $batch->batch_number);
    }

    public function test_sequential_batch_numbers_increment(): void
    {
        $batch1 = $this->service->generateBatch(1);
        $batch2 = $this->service->generateBatch(1);

        $seq1 = (int) substr($batch1->batch_number, -4);
        $seq2 = (int) substr($batch2->batch_number, -4);

        $this->assertSame($seq1 + 1, $seq2);
    }

    public function test_generate_batch_rejects_zero_count(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->generateBatch(0);
    }

    public function test_generate_batch_rejects_excessive_count(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->generateBatch(100001);
    }
}
