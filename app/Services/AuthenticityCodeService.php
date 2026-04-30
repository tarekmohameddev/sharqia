<?php

namespace App\Services;

use App\Models\AuthenticityCode;
use App\Models\AuthenticityCodeBatch;
use Illuminate\Support\Facades\DB;

class AuthenticityCodeService
{
    /**
     * Code format: 4 digits + dash + 4 digits + dash + 4 digits, e.g. 1234-5678-9012
     */
    private const MAX_BATCH_DIRECT = 5000;
    private const CODE_LENGTH = 12;
    private const SEGMENT_LENGTH = 4;
    private const SEGMENT_COUNT = 3;

    public function generateCode(): string
    {
        $digits = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $digits .= (string) random_int(0, 9);
        }

        return $this->formatDigits($digits);
    }

    /**
     * Normalize a user-supplied code to the canonical stored format (1234-5678-9012).
     * Accepts input with optional spaces/dashes; strips them out then reformats.
     */
    public function normalizeCode(string $input): string
    {
        $stripped = trim($input);
        $digitsOnly = preg_replace('/\D+/', '', $stripped);

        // If we ended up with exactly 12 digits, return as canonical
        if (strlen($digitsOnly) === self::CODE_LENGTH) {
            return $this->formatDigits($digitsOnly);
        }

        // Unknown format — return trimmed input as-is (caller can reject)
        return $stripped;
    }

    private function formatDigits(string $digitsOnly): string
    {
        return implode('-', str_split($digitsOnly, self::SEGMENT_LENGTH));
    }

    /**
     * Generate a unique code that does not yet exist in the database.
     */
    public function generateUniqueCode(): string
    {
        $attempts = 0;
        do {
            $code = $this->generateCode();
            $attempts++;
            if ($attempts > 100) {
                throw new \RuntimeException('Failed to generate unique code after 100 attempts');
            }
        } while (AuthenticityCode::where('code', $code)->exists());

        return $code;
    }

    /**
     * Generate a batch of N unique codes and persist them in a transaction.
     */
    public function generateBatch(int $count): AuthenticityCodeBatch
    {
        if ($count < 1 || $count > 100000) {
            throw new \InvalidArgumentException('Batch count must be between 1 and 100,000');
        }

        return DB::transaction(function () use ($count) {
            $batchNumber = $this->generateBatchNumber();

            $batch = AuthenticityCodeBatch::create([
                'batch_number' => $batchNumber,
                'total_count' => $count,
                'generated_at' => now(),
            ]);

            $this->insertCodes($batch->id, $count);

            return $batch;
        });
    }

    private function generateBatchNumber(): string
    {
        $year = now()->year;
        $lastBatch = AuthenticityCodeBatch::where('batch_number', 'like', "BATCH-{$year}-%")
            ->orderBy('id', 'desc')
            ->first();

        if ($lastBatch) {
            $lastSeq = (int) substr($lastBatch->batch_number, -4);
            $seq = str_pad($lastSeq + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $seq = '0001';
        }

        return "BATCH-{$year}-{$seq}";
    }

    private function insertCodes(int $batchId, int $count): void
    {
        $chunkSize = 500;
        $inserted = 0;
        $now = now()->toDateTimeString();

        while ($inserted < $count) {
            $toInsert = min($chunkSize, $count - $inserted);
            $rows = [];

            for ($i = 0; $i < $toInsert; $i++) {
                $code = $this->generateUniqueCode();
                $rows[] = [
                    'batch_id' => $batchId,
                    'code' => $code,
                    'status' => 'unused',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Use insert ignore / insertOrIgnore to handle rare collision
            AuthenticityCode::insertOrIgnore($rows);

            // Count actually inserted; if short due to collision, loop again
            $actualCount = AuthenticityCode::where('batch_id', $batchId)->count();
            $inserted = $actualCount;

            if ($inserted < $count && count($rows) > 0) {
                // Some collisions happened, continue loop to fill in
                continue;
            }
            $inserted = $count; // All inserted successfully
        }
    }
}
