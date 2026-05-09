<?php

namespace Tests\Unit;

use App\Services\InvoiceCalculationService;
use App\Services\InvoiceFieldExtractor;
use PHPUnit\Framework\TestCase;

class InvoiceFieldExtractorTest extends TestCase
{
    public function test_extracts_multiple_invoice_items_and_tax_fields(): void
    {
        $text = <<<OCR
        Tax Invoice
        Invoice No: INV-2001
        Invoice Date: 05/03/2025
        Bill To: ABC Sdn Bhd

        Description Qty Unit Price Amount
        Web Design Service 1 1500.00 1500.00
        Hosting Package 1 300.00 300.00
        Maintenance Service 2 150.00 300.00

        Subtotal RM 2,100.00
        SST 6% RM 126.00
        Grand Total RM 2,226.00
        OCR;

        $result = (new InvoiceFieldExtractor())->extract($text);

        $this->assertSame('INV-2001', $result['invoice_number']);
        $this->assertSame('2025-03-05', $result['invoice_date']);
        $this->assertSame('ABC Sdn Bhd', $result['customer_name']);
        $this->assertCount(3, $result['items']);
        $this->assertSame('Web Design Service', $result['items'][0]['item_name']);
        $this->assertSame(6.0, $result['tax_rate']);
        $this->assertSame(126.0, $result['tax_amount']);
        $this->assertSame(2100.0, $result['subtotal']);
        $this->assertSame(2226.0, $result['total_amount']);
    }

    public function test_calculates_line_total_when_quantity_and_unit_price_exist(): void
    {
        $result = (new InvoiceCalculationService())->calculate([
            'items' => [
                [
                    'item_name' => 'Maintenance Service',
                    'quantity' => 2,
                    'unit_price' => 150,
                ],
            ],
        ]);

        $this->assertSame(300.0, $result['items'][0]['line_total']);
        $this->assertSame(300.0, $result['subtotal']);
        $this->assertSame(300.0, $result['total_amount']);
    }

    public function test_calculates_subtotal_from_items_when_subtotal_is_missing(): void
    {
        $text = <<<OCR
        Invoice No: INV-2002
        Date: 06/03/2025
        Customer: ABC Sdn Bhd
        Item Qty Price Total
        Web Design 1 1000.00 1000.00
        Hosting 1 200.00 200.00
        Grand Total RM 1,200.00
        OCR;

        $result = (new InvoiceFieldExtractor())->extract($text);

        $this->assertSame(1200.0, $result['subtotal']);
        $this->assertContains('Subtotal was missing, so it was estimated from item line totals.', $result['warnings']);
    }

    public function test_calculates_tax_when_subtotal_and_total_exist_without_discount_or_service_charge(): void
    {
        $text = <<<OCR
        Invoice No: INV-2003
        Date: 07/03/2025
        Customer: ABC Sdn Bhd
        Item Qty Price Total
        Service 1 1000.00 1000.00
        Subtotal RM 1,000.00
        Grand Total RM 1,060.00
        OCR;

        $result = (new InvoiceFieldExtractor())->extract($text);

        $this->assertSame(60.0, $result['tax_amount']);
        $this->assertContains('Tax amount was inferred from grand total minus subtotal. Please verify it.', $result['warnings']);
    }

    public function test_shows_warning_when_item_total_does_not_match_subtotal(): void
    {
        $text = <<<OCR
        Invoice No: INV-2004
        Date: 08/03/2025
        Customer: ABC Sdn Bhd
        Description Qty Unit Price Amount
        Service A 1 100.00 100.00
        Service B 1 100.00 100.00
        Subtotal RM 500.00
        Grand Total RM 500.00
        OCR;

        $result = (new InvoiceFieldExtractor())->extract($text);

        $this->assertContains('Item total does not match subtotal. Please check item rows and subtotal.', $result['warnings']);
    }

    public function test_shows_warning_when_tax_label_has_no_amount(): void
    {
        $text = <<<OCR
        Invoice No: INV-2005
        Date: 09/03/2025
        Customer: ABC Sdn Bhd
        Description Qty Unit Price Amount
        Service A 1 100.00 100.00
        Subtotal RM 100.00
        SST 6%
        Grand Total RM 106.00
        OCR;

        $result = (new InvoiceFieldExtractor())->extract($text);

        $this->assertContains('A tax label was detected, but the tax amount could not be extracted.', $result['warnings']);
    }

    public function test_extracts_product_service_rate_line_total_layout_with_continued_description(): void
    {
        $text = <<<OCR
        Invoice No: INV-2006
        Date: 10/03/2025
        Customer: ABC Sdn Bhd
        Product/Service Qty Rate Line Total
        Website Design
        Custom landing page package 1 1800.00 1800.00
        Monthly Support 2 200.00 400.00
        Grand Total RM 2,200.00
        OCR;

        $result = (new InvoiceFieldExtractor())->extract($text);

        $this->assertCount(2, $result['items']);
        $this->assertSame('Website Design Custom landing page package', $result['items'][0]['item_name']);
        $this->assertSame(400.0, $result['items'][1]['line_total']);
    }
}
