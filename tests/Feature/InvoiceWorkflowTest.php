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
}
