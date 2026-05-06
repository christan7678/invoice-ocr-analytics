<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ExchangeRateService
{
    public function rateToMyr(string $from): array
    {
        $from = strtoupper($from);

        if ($from === 'MYR') {
            return [
                'base' => 'MYR',
                'quote' => 'MYR',
                'rate' => 1.0,
                'date' => now()->toDateString(),
                'source' => 'Local fixed rate',
            ];
        }

        return Cache::remember("exchange-rate.{$from}.MYR", now()->addHours(12), function () use ($from) {
            $baseUrl = rtrim(config('services.exchange_rates.base_url'), '/');
            $client = Http::timeout(10);

            if (! config('services.exchange_rates.verify_ssl')) {
                $client = $client->withoutVerifying();
            }

            $response = $client->get("{$baseUrl}/rate/{$from}/MYR");

            if (! $response->successful()) {
                throw new RuntimeException('Exchange-rate service is unavailable. Please enter the rate manually.');
            }

            $payload = $response->json();

            if (! isset($payload['rate']) || ! is_numeric($payload['rate'])) {
                throw new RuntimeException('Exchange-rate service returned an invalid rate. Please enter the rate manually.');
            }

            return [
                'base' => $payload['base'] ?? $from,
                'quote' => $payload['quote'] ?? 'MYR',
                'rate' => (float) $payload['rate'],
                'date' => $payload['date'] ?? now()->toDateString(),
                'source' => 'Frankfurter',
            ];
        });
    }
}
