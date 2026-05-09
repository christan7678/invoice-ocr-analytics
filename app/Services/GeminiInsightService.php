<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiInsightService
{
    public function rewrite(array $metrics, array $ruleBased): ?array
    {
        $apiKey = config('services.gemini.api_key');

        if (! $apiKey) {
            return null;
        }

        try {
            $model = config('services.gemini.model', 'gemini-2.0-flash');
            $baseUrl = rtrim(config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
            $url = "{$baseUrl}/models/{$model}:generateContent";

            $response = Http::timeout((int) config('services.gemini.timeout', 12))
                ->withHeaders([
                    'x-goog-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($url, [
                    'systemInstruction' => [
                        'parts' => [[
                            'text' => 'You are an SME sales analytics assistant. Rewrite the provided verified invoice metrics into a clear business insight. Do not invent numbers. Do not calculate new values. Only use the provided data. If the data is insufficient, say so clearly. Keep the explanation practical, concise, and suitable for small business users.',
                        ]],
                    ],
                    'contents' => [[
                        'parts' => [[
                            'text' => json_encode([
                                'task' => 'Return JSON with summary, key_observations, recommendations, and risk_warnings. Use only the supplied metrics and rule-based findings.',
                                'metrics' => $metrics,
                                'rule_based' => $ruleBased,
                            ], JSON_PRETTY_PRINT),
                        ]],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

            if (! $response->successful()) {
                return null;
            }

            $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

            if (! is_string($text) || trim($text) === '') {
                return null;
            }

            $decoded = json_decode($text, true);

            if (! is_array($decoded)) {
                return null;
            }

            return [
                'summary' => (string) ($decoded['summary'] ?? $ruleBased['summary']),
                'key_observations' => $this->stringList($decoded['key_observations'] ?? []),
                'recommendations' => $this->stringList($decoded['recommendations'] ?? []),
                'risk_warnings' => $this->stringList($decoded['risk_warnings'] ?? []),
                'source' => 'Gemini AI rewrite',
            ];
        } catch (\Throwable $exception) {
            Log::warning('Gemini insight rewrite failed', ['message' => $exception->getMessage()]);

            return null;
        }
    }

    private function stringList(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
            ->map(fn ($item) => trim($item))
            ->values()
            ->all();
    }
}
