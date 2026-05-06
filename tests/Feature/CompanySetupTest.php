<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanySetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_without_company_is_redirected_to_company_setup(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/company/setup');
    }

    public function test_user_can_create_company_profile(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->post('/company/setup', [
                'company_name' => 'Alpha Trading Sdn Bhd',
                'default_currency_code' => 'MYR',
            ])
            ->assertRedirect('/dashboard');

        $this->assertDatabaseHas('companies', [
            'user_id' => $user->id,
            'company_name' => 'Alpha Trading Sdn Bhd',
        ]);
    }
}
