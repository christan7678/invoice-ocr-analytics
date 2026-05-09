<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\InvoiceRiskAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
            ->assertSee('This report contains')
            ->assertSee('No filters applied')
            ->assertSee('ABC Sdn Bhd');
    }

    public function test_user_edited_item_rows_tax_and_total_are_saved_correctly(): void
    {
        $user = User::factory()->create();
        $company = $user->company()->create([
            'company_name' => 'Test Company Sdn Bhd',
            'default_currency_code' => 'MYR',
        ]);

        $this
            ->actingAs($user)
            ->post('/invoices', [
                'invoice_number' => 'INV-VERIFY-1001',
                'invoice_date' => '2025-03-10',
                'customer_name' => 'ABC Sdn Bhd',
                'currency_code' => 'MYR',
                'exchange_rate_to_myr' => 1,
                'subtotal' => 2100,
                'tax_rate' => 6,
                'tax_amount' => 126,
                'discount_amount' => 0,
                'service_charge' => 0,
                'total_amount' => 2226,
                'payment_status' => 'pending',
                'items' => [
                    ['item_name' => 'Web Design Service', 'quantity' => 1, 'unit_price' => 1500, 'line_total' => 1500],
                    ['item_name' => 'Hosting Package', 'quantity' => 1, 'unit_price' => 300, 'line_total' => 300],
                    ['item_name' => 'Maintenance Service', 'quantity' => 2, 'unit_price' => 150, 'line_total' => 300],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('invoices', [
            'company_id' => $company->id,
            'invoice_number' => 'INV-VERIFY-1001',
            'subtotal' => 2100,
            'tax_rate' => 6,
            'tax_amount' => 126,
            'total_amount' => 2226,
            'total_amount_myr' => 2226,
        ]);

        $this->assertDatabaseCount('invoice_items', 3);
    }

    public function test_duplicate_invoice_detection_reports_existing_invoice_risk(): void
    {
        $user = User::factory()->create();
        $company = $user->company()->create([
            'company_name' => 'Test Company Sdn Bhd',
            'default_currency_code' => 'MYR',
        ]);
        $customer = $company->customers()->create(['customer_name' => 'ABC Sdn Bhd']);

        $company->invoices()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-DUP-1001',
            'invoice_date' => '2025-03-10',
            'subtotal' => 500,
            'total_amount' => 500,
            'currency_code' => 'MYR',
            'exchange_rate_to_myr' => 1,
            'total_amount_myr' => 500,
            'payment_status' => 'pending',
            'verification_status' => 'verified',
        ]);

        $alerts = app(InvoiceRiskAnalyzer::class)->forInvoiceData([
            'invoice_number' => 'INV-DUP-1001',
            'invoice_date' => '2025-03-10',
            'customer_name' => 'ABC Sdn Bhd',
            'currency_code' => 'MYR',
            'total_amount' => 500,
            'items' => [['item_name' => 'Service', 'line_total' => 500]],
        ], $company->id);

        $this->assertTrue(collect($alerts)->contains(fn ($alert) => $alert['code'] === 'duplicate_invoice_number'));
    }

    public function test_reports_and_insights_are_scoped_to_authenticated_company(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $company = $user->company()->create(['company_name' => 'First Company', 'default_currency_code' => 'MYR']);
        $otherCompany = $otherUser->company()->create(['company_name' => 'Other Company', 'default_currency_code' => 'MYR']);
        $customer = $company->customers()->create(['customer_name' => 'ABC Sdn Bhd']);
        $otherCustomer = $otherCompany->customers()->create(['customer_name' => 'Hidden Customer']);

        $company->invoices()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-OWN-1001',
            'invoice_date' => '2025-03-10',
            'subtotal' => 1000,
            'total_amount' => 1000,
            'currency_code' => 'MYR',
            'exchange_rate_to_myr' => 1,
            'total_amount_myr' => 1000,
            'payment_status' => 'paid',
            'verification_status' => 'verified',
        ]);

        $otherCompany->invoices()->create([
            'customer_id' => $otherCustomer->id,
            'invoice_number' => 'INV-HIDDEN-1001',
            'invoice_date' => '2025-03-10',
            'subtotal' => 9000,
            'total_amount' => 9000,
            'currency_code' => 'MYR',
            'exchange_rate_to_myr' => 1,
            'total_amount_myr' => 9000,
            'payment_status' => 'paid',
            'verification_status' => 'verified',
        ]);

        $this->actingAs($user)
            ->get('/reports')
            ->assertOk()
            ->assertSee('INV-OWN-1001')
            ->assertDontSee('INV-HIDDEN-1001')
            ->assertDontSee('Hidden Customer');

        $this->actingAs($user)
            ->get('/insights')
            ->assertOk()
            ->assertSee('ABC Sdn Bhd')
            ->assertDontSee('Hidden Customer');
    }

    public function test_insight_uses_rule_based_fallback_when_ai_is_disabled(): void
    {
        config(['services.gemini.api_key' => null]);

        $user = User::factory()->create();
        $company = $user->company()->create(['company_name' => 'Test Company Sdn Bhd', 'default_currency_code' => 'MYR']);
        $customer = $company->customers()->create(['customer_name' => 'ABC Sdn Bhd']);

        $company->invoices()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-AI-OFF-1001',
            'invoice_date' => '2025-03-10',
            'subtotal' => 1000,
            'total_amount' => 1000,
            'currency_code' => 'MYR',
            'exchange_rate_to_myr' => 1,
            'total_amount_myr' => 1000,
            'payment_status' => 'paid',
            'verification_status' => 'verified',
        ]);

        $this->actingAs($user)
            ->get('/insights')
            ->assertOk()
            ->assertSee('No AI key / fallback')
            ->assertSee('Rule-based fallback');
    }

    public function test_insight_uses_rule_based_fallback_when_ai_request_fails(): void
    {
        config([
            'services.gemini.api_key' => 'fake-key',
            'services.gemini.model' => 'gemini-2.0-flash',
        ]);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'Quota exceeded']], 429),
        ]);

        $user = User::factory()->create();
        $company = $user->company()->create(['company_name' => 'Test Company Sdn Bhd', 'default_currency_code' => 'MYR']);
        $customer = $company->customers()->create(['customer_name' => 'ABC Sdn Bhd']);

        $company->invoices()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-AI-FAIL-1001',
            'invoice_date' => '2025-03-10',
            'subtotal' => 1000,
            'total_amount' => 1000,
            'currency_code' => 'MYR',
            'exchange_rate_to_myr' => 1,
            'total_amount_myr' => 1000,
            'payment_status' => 'paid',
            'verification_status' => 'verified',
        ]);

        $this->actingAs($user)
            ->get('/insights')
            ->assertOk()
            ->assertSee('No AI key / fallback')
            ->assertSee('Rule-based fallback');
    }
}
