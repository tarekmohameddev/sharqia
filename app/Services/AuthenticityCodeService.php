<?php

namespace App\Services;

use App\Models\AuthenticityCode;
use App\Models\AuthenticityCodeBatch;
use Illuminate\Support\Facades\DB;

class AuthenticityCodeService
{
    /**
     * Charset excludes 0/O, 1/I/L to avoid visual confusion when reading from printed cards.
     * Code format: XXXX-XXXX-XXXX (3 groups of 4, dash-separated), e.g. FSY9-6HKI-7TOP
     */
    private const CHARSET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    private const SEGMENT_LENGTH = 4;
    private const SEGMENT_COUNT = 3;
    private const MAX_BATCH_DIRECT = 5000;

    public function generateCode(): string
    {
        $charset = self::CHARSET;
        $charsetLength = strlen($charset);
        $segments = [];

        for ($s = 0; $s < self::SEGMENT_COUNT; $s++) {
            $segment = '';
            for ($i = 0; $i < self::SEGMENT_LENGTH; $i++) {
                $segment .= $charset[random_int(0, $charsetLength - 1)];
            }
            $segments[] = $segment;
        }

        return implode('-', $segments);
    }

    /**
     * Normalize a user-supplied code to the canonical stored format (XXXX-XXXX-XXXX).
     * Accepts codes with or without dashes/spaces.
     */
    public function normalizeCode(string $input): string
    {
        $stripped = strtoupper(preg_replace('/[\s\-]+/', '', $input));

        // If 12 raw chars, reformat with dashes
        if (strlen($stripped) === self::SEGMENT_LENGTH * self::SEGMENT_COUNT) {
            return implode('-', str_split($stripped, self::SEGMENT_LENGTH));
        }

        // Already formatted or unknown — return uppercased as-is
        return strtoupper(trim($input));
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
