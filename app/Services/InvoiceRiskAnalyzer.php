<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Str;

class InvoiceRiskAnalyzer
{
    public function forExtraction(array $fields, int $companyId, ?int $ignoreInvoiceId = null): array
    {
        return $this->forInvoiceData([
            'invoice_number' => $fields['invoice_number'] ?? null,
            'invoice_date' => $fields['invoice_date'] ?? null,
            'customer_name' => $fields['customer_name'] ?? null,
            'currency_code' => $fields['currency_code'] ?? 'MYR',
            'exchange_rate_to_myr' => 1,
            'subtotal' => $fields['subtotal'] ?? null,
            'tax_amount' => $fields['tax_amount'] ?? null,
            'discount_amount' => $fields['discount_amount'] ?? null,
            'service_charge' => $fields['service_charge'] ?? null,
            'total_amount' => $fields['total_amount'] ?? null,
            'items' => $fields['items'] ?? [],
        ], $companyId, $ignoreInvoiceId, $fields['raw_ocr_text'] ?? null, $fields['warnings'] ?? []);
    }

    public function forInvoiceData(array $data, int $companyId, ?int $ignoreInvoiceId = null, ?string $rawOcr = null, array $existingWarnings = []): array
    {
        $alerts = [];
        $invoiceNumber = trim((string) ($data['invoice_number'] ?? ''));
        $customerName = trim((string) ($data['customer_name'] ?? ''));
        $invoiceDate = $data['invoice_date'] ?? null;
        $totalAmount = $this->numberOrNull($data['total_amount'] ?? null);
        $currency = strtoupper((string) ($data['currency_code'] ?? 'MYR'));
        $items = $data['items'] ?? [];

        foreach ($existingWarnings as $warning) {
            $alerts[] = $this->alert('Medium', $warning, 'ocr_warning');
        }

        if ($invoiceNumber === '') {
            $alerts[] = $this->alert('High', 'Invoice number is missing.', 'missing_invoice_number');
        }

        if ($customerName === '') {
            $alerts[] = $this->alert('Medium', 'Customer name is missing.', 'missing_customer_name');
        }

        if (! filled($invoiceDate)) {
            $alerts[] = $this->alert('Medium', 'Invoice date is missing.', 'missing_invoice_date');
        }

        if (count($items) === 0) {
            $alerts[] = $this->alert('High', 'No invoice item rows are available.', 'missing_items');
        }

        $this->duplicateAlerts($alerts, $companyId, $ignoreInvoiceId, $invoiceNumber, $customerName, $invoiceDate, $totalAmount, $currency);
        $this->calculationAlerts($alerts, $data);

        if ($rawOcr && preg_match('/\b(tax|sst|gst|vat|service tax|sales tax)\b/i', $rawOcr) && $this->numberOrNull($data['tax_amount'] ?? null) === null) {
            $alerts[] = $this->alert('Medium', 'A tax label exists in OCR text, but tax amount is missing.', 'tax_missing');
        }

        if ($currency !== 'MYR' && $this->numberOrNull($data['exchange_rate_to_myr'] ?? null) === null) {
            $alerts[] = $this->alert('Medium', 'Foreign currency is used but exchange rate is missing.', 'currency_rate_missing');
        }

        return collect($alerts)
            ->unique(fn ($alert) => $alert['code'].'|'.$alert['message'])
            ->values()
            ->all();
    }

    public function forSavedInvoice(Invoice $invoice): array
    {
        $average = (float) Invoice::query()
            ->where('company_id', $invoice->company_id)
            ->where('id', '!=', $invoice->id)
            ->avg('total_amount_myr');

        $alerts = $this->forInvoiceData([
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => $invoice->invoice_date?->toDateString(),
            'customer_name' => $invoice->customer?->customer_name,
            'currency_code' => $invoice->currency_code,
            'exchange_rate_to_myr' => $invoice->exchange_rate_to_myr,
            'subtotal' => $invoice->subtotal,
            'tax_amount' => $invoice->tax_amount,
            'discount_amount' => $invoice->discount_amount,
            'service_charge' => $invoice->service_charge,
            'total_amount' => $invoice->total_amount,
            'items' => $invoice->items->toArray(),
        ], $invoice->company_id, $invoice->id, $invoice->raw_ocr_text);

        if ($average > 0 && (float) $invoice->total_amount_myr > ($average * 2.5)) {
            $alerts[] = $this->alert('Medium', 'Invoice amount is much higher than the company average.', 'abnormal_amount');
        }

        if (in_array($invoice->payment_status, ['unpaid', 'pending', 'partial', 'overdue'], true) && (float) $invoice->total_amount_myr >= max($average, 1000)) {
            $alerts[] = $this->alert('Medium', 'Large unpaid invoice should be prioritised for follow-up.', 'large_unpaid');
        }

        if ($invoice->due_date && $invoice->due_date->isPast() && in_array($invoice->payment_status, ['unpaid', 'pending', 'partial', 'overdue'], true)) {
            $alerts[] = $this->alert('High', 'Invoice is overdue and still not fully paid.', 'overdue');
        }

        return $alerts;
    }

    private function duplicateAlerts(array &$alerts, int $companyId, ?int $ignoreInvoiceId, string $invoiceNumber, string $customerName, mixed $invoiceDate, ?float $totalAmount, string $currency): void
    {
        if ($invoiceNumber !== '') {
            $exactQuery = Invoice::query()
                ->where('company_id', $companyId)
                ->where('invoice_number', $invoiceNumber);

            if ($ignoreInvoiceId) {
                $exactQuery->where('id', '!=', $ignoreInvoiceId);
            }

            if ($exactQuery->exists()) {
                $alerts[] = $this->alert('High', 'This invoice number already exists for this company.', 'duplicate_invoice_number');
            }
        }

        if ($customerName !== '' && filled($invoiceDate) && $totalAmount !== null) {
            $customerIds = Customer::query()
                ->where('company_id', $companyId)
                ->where('customer_name', $customerName)
                ->pluck('id');

            if ($customerIds->isNotEmpty()) {
                $date = Carbon::parse($invoiceDate)->toDateString();
                $similarQuery = Invoice::query()
                    ->where('company_id', $companyId)
                    ->whereIn('customer_id', $customerIds)
                    ->whereDate('invoice_date', $date)
                    ->where('currency_code', $currency)
                    ->whereBetween('total_amount', [$totalAmount - 0.05, $totalAmount + 0.05]);

                if ($ignoreInvoiceId) {
                    $similarQuery->where('id', '!=', $ignoreInvoiceId);
                }

                if ($similarQuery->exists()) {
                    $alerts[] = $this->alert('Medium', 'A similar invoice exists for the same customer, date, currency, and amount.', 'possible_duplicate');
                }
            }
        }
    }

    private function calculationAlerts(array &$alerts, array $data): void
    {
        $subtotal = $this->numberOrNull($data['subtotal'] ?? null);
        $tax = $this->numberOrNull($data['tax_amount'] ?? null) ?? 0.0;
        $discount = $this->numberOrNull($data['discount_amount'] ?? null) ?? 0.0;
        $serviceCharge = $this->numberOrNull($data['service_charge'] ?? null) ?? 0.0;
        $total = $this->numberOrNull($data['total_amount'] ?? null);
        $items = $data['items'] ?? [];
        $lineSum = round((float) collect($items)->sum(fn ($item) => (float) ($item['line_total'] ?? $item['total_price'] ?? 0)), 2);

        if ($subtotal !== null && $lineSum > 0 && abs($subtotal - $lineSum) > 0.05) {
            $alerts[] = $this->alert('High', 'Item totals do not match subtotal.', 'item_subtotal_mismatch');
        }

        if ($subtotal !== null && $total !== null) {
            $expected = round(max($subtotal - $discount + $serviceCharge + $tax, 0), 2);

            if (abs($expected - $total) > 0.05) {
                $alerts[] = $this->alert('High', 'Subtotal, tax, discount, and service charge do not match grand total.', 'grand_total_mismatch');
            }
        }
    }

    private function alert(string $severity, string $message, string $code): array
    {
        return [
            'severity' => $severity,
            'message' => $message,
            'code' => $code,
        ];
    }

    private function numberOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
