<?php

namespace App\Services;

use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EasyOrdersApiService
{
    private const BASE_URL = 'https://api.easy-orders.net/api/v1/external-apps';

    private const RATE_LIMIT_PER_MINUTE = 40;

    private int $requestCount = 0;

    private ?float $minuteStart = null;

    /**
     * Get the EasyOrders API key from business settings.
     */
    public function getApiKey(): ?string
    {
        $setting = BusinessSetting::where('type', 'easyorders_api_key')->first();
        if (!$setting || $setting->value === null || $setting->value === '') {
            return null;
        }
        $value = $setting->value;
        if (is_string($value) && $value !== '') {
            return $value;
        }
        $decoded = json_decode($value, true);
        return is_string($decoded) ? $decoded : (string) $value;
    }

    /**
     * Ensure we respect the 40 requests per minute rate limit.
     */
    private function rateLimit(): void
    {
        $now = microtime(true);
        if ($this->minuteStart === null) {
            $this->minuteStart = $now;
        }
        if ($now - $this->minuteStart >= 60) {
            $this->requestCount = 0;
            $this->minuteStart = $now;
        }
        if ($this->requestCount >= self::RATE_LIMIT_PER_MINUTE) {
            $sleepSeconds = 60 - ($now - $this->minuteStart);
            if ($sleepSeconds > 0) {
                usleep((int) ($sleepSeconds * 1_000_000));
            }
            $this->requestCount = 0;
            $this->minuteStart = microtime(true);
        }
        $this->requestCount++;
    }

    /**
     * Fetch all products from EasyOrders Product API.
     * Uses fields=id,name,sku and pagination (limit=200 per page).
     *
     * @return array<int, array{id: string, name: string, sku: string|null}>
     * @throws \RuntimeException if API key is missing or API request fails
     */
    public function getAllProducts(): array
    {
        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            throw new \RuntimeException('EasyOrders API key is not configured. Please set it in Business Settings.');
        }

        $allProducts = [];
        $page = 1;
        $limit = 200;
        $fields = 'id,name,sku';

        do {
            $this->rateLimit();

            $response = Http::withHeaders([
                'Api-Key' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->get(self::BASE_URL . '/products', [
                'limit' => $limit,
                'page' => $page,
                'fields' => $fields,
            ]);

            if (!$response->successful()) {
                Log::error('EasyOrders API products request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \RuntimeException(
                    'EasyOrders API request failed: ' . $response->status() . ' - ' . $response->body()
                );
            }

            $data = $response->json();
            $items = [];
            if (is_array($data)) {
                if (array_is_list($data)) {
                    $items = $data;
                } elseif (isset($data['data']) && is_array($data['data'])) {
                    $items = $data['data'];
                } elseif (isset($data['id'])) {
                    $items = [$data];
                }
            }

            foreach ($items as $item) {
                if (is_array($item) && isset($item['id'])) {
                    $allProducts[] = [
                        'id' => (string) ($item['id'] ?? ''),
                        'name' => (string) ($item['name'] ?? ''),
                        'sku' => isset($item['sku']) ? (string) $item['sku'] : null,
                    ];
                }
            }

            $page++;
        } while (count($items) >= $limit);

        return $allProducts;
    }
}
