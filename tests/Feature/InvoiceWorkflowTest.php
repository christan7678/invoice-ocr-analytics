<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_invoice_with_items_and_myr_conversion(): void
    {
        $user = User::factory()->create();
        $company = $user->company()->create([
            'company_name' => 'Test Company Sdn Bhd',
            'default_currency_code' => 'MYR',
        ]);

        $this
            ->actingAs($user)
            ->post('/invoices', [
                'invoice_number' => 'INV-1001',
                'invoice_date' => '2026-05-06',
                'customer_name' => 'ABC Sdn Bhd',
                'currency_code' => 'USD',
                'exchange_rate_to_myr' => 4.5,
                'payment_status' => 'paid',
                'items' => [
                    [
                        'item_name' => 'Software Service',
                        'description' => 'Monthly service',
                        'quantity' => 2,
                        'unit_price' => 100,
                        'tax_amount' => 10,
                        'discount_amount' => 5,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'company_id' => $company->id,
            'customer_name' => 'ABC Sdn Bhd',
        ]);

        $this->assertDatabaseHas('invoices', [
            'company_id' => $company->id,
            'invoice_number' => 'INV-1001',
            'currency_code' => 'USD',
            'total_amount' => 205,
            'total_amount_myr' => 922.5,
        ]);
    }

    public function test_insight_uses_latest_available_invoice_month_when_current_month_has_no_data(): void
    {
        $user = User::factory()->create();
        $company = $user->company()->create([
            'company_name' => 'Test Company Sdn Bhd',
            'default_currency_code' => 'MYR',
        ]);
        $customer = $company->customers()->create([
            'customer_name' => 'ABC Sdn Bhd',
        ]);

        $company->invoices()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-OLD-1001',
            'invoice_date' => '2025-03-10',
            'subtotal' => 1000,
            'total_amount' => 1000,
            'currency_code' => 'MYR',
            'exchange_rate_to_myr' => 1,
            'total_amount_myr' => 1000,
            'payment_status' => 'paid',
            'verification_status' => 'verified',
        ]);

        $this
            ->actingAs($user)
            ->get('/insights')
            ->assertOk()
            ->assertSee('Latest available invoice month')
            ->assertSee('March 2025')
            ->assertSee('ABC Sdn Bhd')
            ->assertDontSee('No verified invoice data is available yet');
    }

    public function test_report_page_shows_generated_feedback_and_selected_report_type(): void
    {
        $user = User::factory()->create();
        $company = $user->company()->create([
            'company_name' => 'Test Company Sdn Bhd',
            'default_currency_code' => 'MYR',
        ]);
        $customer = $company->customers()->create([
            'customer_name' => 'ABC Sdn Bhd',
        ]);

        $company->invoices()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-RPT-1001',
            'invoice_date' => '2025-03-10',
            'subtotal' => 850,
            'total_amount' => 850,
            'currency_code' => 'MYR',
            'exchange_rate_to_myr' => 1,
            'total_amount_myr' => 850,
            'payment_status' => 'unpaid',
            'verification_status' => 'verified',
        ]);

        $this
            ->actingAs($user)
            ->get('/reports?report_type=customer')
            ->assertOk()
            ->assertSee('Report generated')
            ->assertSee('Customer sales report')
            ->assertSee('No filters applied')
            ->assertSee('ABC Sdn Bhd');
    }
}
