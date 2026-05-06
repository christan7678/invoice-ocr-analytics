<?php

namespace App\Http\Controllers;

use App\Services\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
{
    public function show(Request $request, ExchangeRateService $exchangeRates): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'string', 'size:3'],
        ]);

        try {
            return response()->json($exchangeRates->rateToMyr($validated['from']));
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
