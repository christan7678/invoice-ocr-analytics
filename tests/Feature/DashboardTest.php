<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_displays_invoice_ocr_analytics_interface(): void
    {
        $user = User::factory()->create();
        $user->company()->create([
            'company_name' => 'Test Company Sdn Bhd',
            'default_currency_code' => 'MYR',
        ]);

        $this
            ->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Invoice OCR Analytics Dashboard')
            ->assertSee('Upload Invoice')
            ->assertSee('OCR Evaluation');
    }
}
