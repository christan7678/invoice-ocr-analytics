<?php

namespace Tests\Feature;

use App\Models\UploadedDocument;
use App\Models\User;
use App\Services\InvoiceRiskAnalyzer;
use App\Services\OcrProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class InvoiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_ocr_result_redirects_to_human_verification_page_with_raw_text(): void
    {
        Storage::fake('local');

        $ocrText = <<<OCR
        Invoice No: INV-OCR-1001
        Invoice Date: 10/03/2025
        Bill To: ABC Sdn Bhd
        Description Qty Unit Price Amount
        Web Design Service 1 1500.00 1500.00
        Grand Total RM 1,500.00
        OCR;

        $this->app->instance(OcrProcessor::class, new class($ocrText) extends OcrProcessor {
            public function __construct(private string $text)
            {
            }

            public function extract(string $absolutePath): array
            {
                return [
                    'text' => $this->text,
                    'confidence' => 88.5,
                ];
            }
        });

        $user = User::factory()->create();
        $user->company()->create([
            'company_name' => 'Test Company Sdn Bhd',
            'default_currency_code' => 'MYR',
        ]);

        $response = $this
            ->actingAs($user)
            ->post('/invoice-upload', [
                'invoice_file' => UploadedFile::fake()->image('invoice.png'),
            ]);

        $document = UploadedDocument::firstOrFail();

        $response->assertRedirect(route('invoices.create', ['document' => $document->id]));
        $this->assertDatabaseHas('uploaded_documents', [
            'id' => $document->id,
            'processing_status' => 'pending_user_verification',
        ]);

        $this
            ->actingAs($user)
            ->get(route('invoices.create', ['document' => $document->id]))
            ->assertOk()
            ->assertSee('Please review the OCR result before saving.')
            ->assertSee('Different companies use different invoice formats, so some fields may be missing or incorrectly detected.')
            ->assertSee('Pending User Verification')
            ->assertSee('Use this raw OCR text as reference when correcting the fields.')
            ->assertSee('Web Design Service')
            ->assertSee('Confidence:');
    }

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
                'total_amount' => 205,
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

    public function test_required_verification_fields_are_validated_before_saving(): void
    {
        $user = User::factory()->create();
        $user->company()->create([
            'company_name' => 'Test Company Sdn Bhd',
            'default_currency_code' => 'MYR',
        ]);

        $this
            ->actingAs($user)
            ->from('/invoices/create')
            ->post('/invoices', [
                'invoice_date' => '',
                'customer_name' => '',
                'currency_code' => 'MYR',
                'exchange_rate_to_myr' => 1,
                'payment_status' => 'pending',
                'items' => [],
            ])
            ->assertRedirect('/invoices/create')
            ->assertSessionHasErrors(['invoice_number', 'invoice_date', 'customer_name', 'total_amount', 'items']);
    }

    public function test_user_can_manually_add_item_rows_when_ocr_misses_items(): void
    {
        Storage::fake('local');

        $ocrText = <<<OCR
        Invoice No: INV-OCR-MISS-1001
        Invoice Date: 10/03/2025
        Bill To: ABC Sdn Bhd
        Grand Total RM 500.00
        OCR;

        $this->app->instance(OcrProcessor::class, new class($ocrText) extends OcrProcessor {
            public function __construct(private string $text)
            {
            }

            public function extract(string $absolutePath): array
            {
                return ['text' => $this->text, 'confidence' => 61.0];
            }
        });

        $user = User::factory()->create();
        $company = $user->company()->create([
            'company_name' => 'Test Company Sdn Bhd',
            'default_currency_code' => 'MYR',
        ]);

        $this
            ->actingAs($user)
            ->post('/invoice-upload', [
                'invoice_file' => UploadedFile::fake()->image('invoice.png'),
            ]);

        $document = UploadedDocument::firstOrFail();

        $this
            ->actingAs($user)
            ->get(route('invoices.create', ['document' => $document->id]))
            ->assertOk()
            ->assertSee('No invoice items were detected. Please add item rows manually before saving.');

        $this
            ->actingAs($user)
            ->post('/invoices', [
                'uploaded_document_id' => $document->id,
                'invoice_number' => 'INV-OCR-MISS-1001',
                'invoice_date' => '2025-03-10',
                'customer_name' => 'ABC Sdn Bhd',
                'currency_code' => 'MYR',
                'exchange_rate_to_myr' => 1,
                'subtotal' => 500,
                'total_amount' => 500,
                'payment_status' => 'pending',
                'items' => [
                    ['item_name' => 'Manually Added Service', 'quantity' => 1, 'unit_price' => 500, 'line_total' => 500],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('invoices', [
            'company_id' => $company->id,
            'uploaded_document_id' => $document->id,
            'invoice_number' => 'INV-OCR-MISS-1001',
            'verification_status' => 'verified_from_ocr',
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'item_name' => 'Manually Added Service',
            'line_total' => 500,
        ]);
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

    public function test_quantity_unit_price_line_total_mismatch_triggers_warning(): void
    {
        $user = User::factory()->create();
        $company = $user->company()->create([
            'company_name' => 'Test Company Sdn Bhd',
            'default_currency_code' => 'MYR',
        ]);

        $alerts = app(InvoiceRiskAnalyzer::class)->forInvoiceData([
            'invoice_number' => 'INV-MISMATCH-1001',
            'invoice_date' => '2025-03-10',
            'customer_name' => 'ABC Sdn Bhd',
            'currency_code' => 'MYR',
            'subtotal' => 500,
            'total_amount' => 500,
            'items' => [
                ['item_name' => 'Service', 'quantity' => 2, 'unit_price' => 100, 'line_total' => 500],
            ],
        ], $company->id);

        $this->assertTrue(collect($alerts)->contains(fn ($alert) => $alert['code'] === 'line_total_mismatch'));
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

        $company->invoices()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-UNVERIFIED-1001',
            'invoice_date' => '2025-03-10',
            'subtotal' => 7000,
            'total_amount' => 7000,
            'currency_code' => 'MYR',
            'exchange_rate_to_myr' => 1,
            'total_amount_myr' => 7000,
            'payment_status' => 'paid',
            'verification_status' => 'pending_user_verification',
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
            ->assertDontSee('INV-UNVERIFIED-1001')
            ->assertDontSee('INV-HIDDEN-1001')
            ->assertDontSee('Hidden Customer');

        $this->actingAs($user)
            ->get('/insights')
            ->assertOk()
            ->assertSee('ABC Sdn Bhd')
            ->assertSee('RM1,000.00')
            ->assertDontSee('RM7,000.00')
            ->assertDontSee('Hidden Customer');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('RM7,000.00');
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
