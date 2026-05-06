<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExchangeRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_fetch_exchange_rate_to_myr(): void
    {
        Cache::flush();
        Http::fake([
            'api.frankfurter.dev/*' => Http::response([
                'date' => '2026-05-06',
                'base' => 'USD',
                'quote' => 'MYR',
                'rate' => 3.9587,
            ]),
        ]);

        $user = User::factory()->create();
        $user->company()->create([
            'company_name' => 'Test Company Sdn Bhd',
            'default_currency_code' => 'MYR',
        ]);

        $this
            ->actingAs($user)
            ->getJson('/exchange-rate?from=USD')
            ->assertOk()
            ->assertJson([
                'base' => 'USD',
                'quote' => 'MYR',
                'rate' => 3.9587,
                'source' => 'Frankfurter',
            ]);
    }
}
