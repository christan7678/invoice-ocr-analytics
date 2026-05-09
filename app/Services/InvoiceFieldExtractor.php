<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;

class InvoiceFieldExtractor
{
    private const CURRENCY_CODES = ['MYR', 'USD', 'SGD', 'EUR', 'GBP', 'AUD', 'CNY', 'JPY', 'THB', 'IDR'];

    public function extract(?string $text): array
    {
        $text = $this->normaliseText((string) $text);

        if ($text === '') {
            return $this->emptyResult();
        }

        $lines = $this->normaliseLines($text);
        $items = $this->extractItems($lines);
        $amounts = $this->extractAmounts($lines);
        $tax = $this->extractTax($lines, $amounts);
        $warnings = [];

        $fields = [
            'invoice_number' => $this->matchInvoiceNumber($text),
            'invoice_date' => $this->matchDate($text, ['invoice date', 'date']),
            'due_date' => $this->matchDate($text, ['due date', 'payment due', 'due']),
            'customer_name' => $this->matchCustomerName($lines),
            'customer_email' => $this->matchEmail($text),
            'customer_phone' => $this->matchPhone($text),
            'customer_address' => $this->matchCustomerAddress($lines),
            'subtotal' => $amounts['subtotal'],
            'tax_rate' => $tax['rate'],
            'tax_amount' => $tax['amount'],
            'discount_amount' => $amounts['discount'],
            'service_charge' => $amounts['service_charge'],
            'total_amount' => $amounts['total'],
            'currency_code' => $this->matchCurrency($text),
            'payment_status' => $this->matchPaymentStatus($text),
            'items' => $items,
        ];

        $lineTotalSum = round((float) collect($items)->sum('line_total'), 2);

        if ($fields['subtotal'] === null && $lineTotalSum > 0) {
            $fields['subtotal'] = $lineTotalSum;
            $warnings[] = 'Subtotal was missing, so it was estimated from item line totals.';
        }

        if ($fields['tax_amount'] === null && $fields['tax_rate'] !== null && $fields['subtotal'] !== null) {
            $fields['tax_amount'] = round($fields['subtotal'] * ($fields['tax_rate'] / 100), 2);
            $warnings[] = 'Tax amount was estimated from detected tax rate and subtotal.';
        }

        $hasDiscountOrCharge = ((float) ($fields['discount_amount'] ?? 0) > 0) || ((float) ($fields['service_charge'] ?? 0) > 0);
        if ($fields['tax_amount'] === null && $fields['subtotal'] !== null && $fields['total_amount'] !== null && ! $hasDiscountOrCharge) {
            $difference = round($fields['total_amount'] - $fields['subtotal'], 2);

            if ($difference > 0) {
                $fields['tax_amount'] = $difference;
                $warnings[] = 'Tax amount was inferred from grand total minus subtotal. Please verify it.';
            }
        }

        if ($fields['total_amount'] === null && $fields['subtotal'] !== null) {
            $fields['total_amount'] = round(
                (float) $fields['subtotal']
                - (float) ($fields['discount_amount'] ?? 0)
                + (float) ($fields['service_charge'] ?? 0)
                + (float) ($fields['tax_amount'] ?? 0),
                2
            );
            $warnings[] = 'Grand total was missing, so it was estimated from subtotal, discount, service charge, and tax.';
        }

        $warnings = array_values(array_unique(array_merge(
            $warnings,
            $amounts['warnings'],
            $tax['warnings'],
            $this->calculationWarnings($fields, $lineTotalSum)
        )));

        $fieldConfidences = $this->fieldConfidences($fields, $warnings, $lineTotalSum);
        $required = ['invoice_number', 'invoice_date', 'customer_name', 'total_amount', 'payment_status'];
        $found = collect($required)->filter(fn ($key) => filled($fields[$key]))->count();

        $fields['confidence_score'] = round(($found / count($required)) * 100, 2);
        $fields['field_confidences'] = $fieldConfidences;
        $fields['warnings'] = $warnings;
        $fields['calculation_summary'] = [
            'item_line_total_sum' => $lineTotalSum,
            'expected_total' => $fields['subtotal'] === null ? null : round(
                (float) $fields['subtotal']
                - (float) ($fields['discount_amount'] ?? 0)
                + (float) ($fields['service_charge'] ?? 0)
                + (float) ($fields['tax_amount'] ?? 0),
                2
            ),
        ];

        return $fields;
    }

    private function emptyResult(): array
    {
        return [
            'invoice_number' => null,
            'invoice_date' => null,
            'due_date' => null,
            'customer_name' => null,
            'customer_email' => null,
            'customer_phone' => null,
            'customer_address' => null,
            'subtotal' => null,
            'tax_rate' => null,
            'tax_amount' => null,
            'discount_amount' => null,
            'service_charge' => null,
            'total_amount' => null,
            'currency_code' => 'MYR',
            'payment_status' => 'pending',
            'items' => [],
            'confidence_score' => 0,
            'field_confidences' => [
                'invoice_number' => 'Missing',
                'invoice_date' => 'Missing',
                'customer_name' => 'Missing',
                'item_rows' => 'Missing',
                'tax_amount' => 'Missing',
                'grand_total' => 'Missing',
            ],
            'warnings' => ['No OCR text was available for extraction.'],
            'calculation_summary' => [
                'item_line_total_sum' => 0,
                'expected_total' => null,
            ],
        ];
    }

    private function normaliseText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;

        return trim($text);
    }

    private function normaliseLines(string $text): array
    {
        return collect(explode("\n", $text))
            ->map(fn ($line) => trim(preg_replace('/\s+/', ' ', $line) ?? $line))
            ->filter()
            ->values()
            ->all();
    }

    private function matchInvoiceNumber(string $text): ?string
    {
        $patterns = [
            '/invoice\s*(?:no\.?|number|#)\s*[:\-]?\s*([A-Z0-9][A-Z0-9\-\/]+)/i',
            '/inv\s*(?:no\.?|number|#)?\s*[:\-]?\s*([A-Z0-9][A-Z0-9\-\/]+)/i',
            '/tax\s*invoice\s*[:\-]?\s*([A-Z0-9][A-Z0-9\-\/]+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return strtoupper(trim($matches[1]));
            }
        }

        return null;
    }

    private function matchDate(string $text, array $labels): ?string
    {
        foreach ($labels as $label) {
            $label = preg_quote($label, '/');
            $patterns = [
                '/'.$label.'\s*[:\-]?\s*([0-9]{1,2}[\/\-.][0-9]{1,2}[\/\-.][0-9]{2,4})/i',
                '/'.$label.'\s*[:\-]?\s*([0-9]{4}[\/\-.][0-9]{1,2}[\/\-.][0-9]{1,2})/i',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $text, $matches)) {
                    return $this->normaliseDate($matches[1]);
                }
            }
        }

        if (preg_match('/([0-9]{4}[\/\-.][0-9]{1,2}[\/\-.][0-9]{1,2})/', $text, $matches)) {
            return $this->normaliseDate($matches[1]);
        }

        return null;
    }

    private function normaliseDate(string $value): ?string
    {
        foreach (['d/m/Y', 'd-m-Y', 'd.m.Y', 'd/m/y', 'd-m-y', 'Y-m-d', 'Y/m/d', 'Y.m.d'] as $format) {
            try {
                return Carbon::createFromFormat($format, trim($value))->format('Y-m-d');
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function matchCustomerName(array $lines): ?string
    {
        foreach ($lines as $index => $line) {
            if (preg_match('/(?:bill\s*to|sold\s*to|customer|client)\s*[:.\-]?\s*(.+)?/i', $line, $matches)) {
                $candidate = trim((string) ($matches[1] ?? ''));

                if ($candidate === '' && isset($lines[$index + 1])) {
                    $candidate = trim($lines[$index + 1]);
                }

                if ($candidate !== '' && ! $this->isSummaryLine($candidate)) {
                    return trim(substr($candidate, 0, 191));
                }
            }
        }

        return null;
    }

    private function matchCustomerAddress(array $lines): ?string
    {
        foreach ($lines as $index => $line) {
            if (preg_match('/(?:bill\s*to|sold\s*to|customer|client)\s*[:.\-]?\s*$/i', $line)) {
                $addressLines = [];

                for ($i = $index + 1; $i <= min($index + 3, count($lines) - 1); $i++) {
                    if ($this->isMetadataLine($lines[$i]) || $this->looksLikeItemHeader($lines[$i])) {
                        break;
                    }

                    $addressLines[] = $lines[$i];
                }

                return $addressLines === [] ? null : implode(', ', $addressLines);
            }
        }

        return null;
    }

    private function matchEmail(string $text): ?string
    {
        return preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $matches)
            ? strtolower($matches[0])
            : null;
    }

    private function matchPhone(string $text): ?string
    {
        return preg_match('/(?:tel|phone|mobile|contact)\s*[:\-]?\s*(\+?[0-9][0-9\s\-()]{6,})/i', $text, $matches)
            ? trim($matches[1])
            : null;
    }

    private function extractAmounts(array $lines): array
    {
        $warnings = [];
        $totalCandidates = [];

        $labels = [
            'subtotal' => ['subtotal', 'sub total', 'net amount', 'amount before tax', 'total before tax', 'taxable amount'],
            'discount' => ['discount', 'less', 'rebate'],
            'service_charge' => ['service charge', 'service fee', 'handling fee'],
            'total' => ['grand total', 'amount due', 'balance due', 'invoice total', 'total amount', 'net total', 'total'],
        ];

        $result = [
            'subtotal' => null,
            'discount' => null,
            'service_charge' => null,
            'total' => null,
            'warnings' => [],
        ];

        foreach ($lines as $line) {
            $normalised = Str::lower($line);

            foreach ($labels as $key => $labelSet) {
                foreach ($labelSet as $label) {
                    if (! Str::contains($normalised, $label)) {
                        continue;
                    }

                    $amount = $this->lastMoneyValue($line);

                    if ($amount === null) {
                        continue;
                    }

                    if ($key === 'total') {
                        $totalCandidates[] = $amount;
                    }

                    $result[$key] = $amount;
                    break;
                }
            }
        }

        if (count(array_unique($totalCandidates)) > 1) {
            $warnings[] = 'Multiple possible grand totals were detected. The final payable amount was selected.';
        }

        $result['warnings'] = $warnings;

        return $result;
    }

    private function extractTax(array $lines, array $amounts): array
    {
        $warnings = [];
        $taxLabels = ['tax', 'sst', 'gst', 'vat', 'sales tax', 'service tax', 'tax amount', 'total tax', 'output tax'];
        $taxAmount = null;
        $taxRate = null;
        $taxLabelFound = false;

        foreach ($lines as $line) {
            $normalised = Str::lower($line);

            if (! collect($taxLabels)->contains(fn ($label) => Str::contains($normalised, $label))) {
                continue;
            }

            $taxLabelFound = true;

            if (preg_match('/([0-9]{1,2}(?:\.[0-9]+)?)\s*%/', $line, $matches)) {
                $taxRate = (float) $matches[1];
            }

            $amount = $this->lastMoneyValue($line);
            if ($amount !== null && $taxRate !== null && ! preg_match('/\b(RM|MYR|USD|SGD|EUR|GBP|AUD|CNY|JPY|THB|IDR)\b|[0-9,]+\.[0-9]{2}/i', $line)) {
                $amount = null;
            }

            if ($amount !== null) {
                $taxAmount = $amount;
            }
        }

        if ($taxLabelFound && $taxAmount === null) {
            $warnings[] = 'A tax label was detected, but the tax amount could not be extracted.';
        }

        if ($taxLabelFound && $taxAmount !== null && $amounts['subtotal'] !== null && $amounts['total'] !== null) {
            $expectedTotal = round(
                (float) $amounts['subtotal']
                - (float) ($amounts['discount'] ?? 0)
                + (float) ($amounts['service_charge'] ?? 0)
                + $taxAmount,
                2
            );

            if (abs($expectedTotal - (float) $amounts['total']) > 0.05) {
                $warnings[] = 'Subtotal, tax, discount, service charge, and grand total do not fully match. Please verify the amounts.';
            }
        }

        return [
            'amount' => $taxAmount,
            'rate' => $taxRate,
            'warnings' => $warnings,
        ];
    }

    private function extractItems(array $lines): array
    {
        $items = [];
        $insideTable = false;
        $pendingDescription = null;

        foreach ($lines as $line) {
            if ($this->looksLikeItemHeader($line)) {
                $insideTable = true;
                $pendingDescription = null;
                continue;
            }

            if ($this->isSummaryLine($line)) {
                if ($insideTable) {
                    break;
                }

                continue;
            }

            if ($this->isMetadataLine($line)) {
                continue;
            }

            $item = $this->parseItemLine($line, $insideTable);

            if ($item) {
                $items[] = $item;
                $pendingDescription = null;
                continue;
            }

            if ($insideTable && $pendingDescription !== null && ($amount = $this->lastMoneyValue($line)) !== null) {
                $items[] = $this->makeItem($pendingDescription, null, null, $amount);
                $pendingDescription = null;
                continue;
            }

            if ($insideTable && $pendingDescription === null && $this->looksLikeDescriptionOnlyLine($line)) {
                $pendingDescription = $line;
            }
        }

        return collect($items)
            ->filter(fn ($item) => filled($item['item_name']) && (float) ($item['line_total'] ?? 0) >= 0)
            ->values()
            ->all();
    }

    private function parseItemLine(string $line, bool $insideTable): ?array
    {
        $line = trim($line);

        if (! $insideTable && substr_count($line, '.') < 1 && ! preg_match('/\bRM\b|\bMYR\b/i', $line)) {
            return null;
        }

        $pattern = '/^\s*(?:[0-9]+[\).\-\s]+)?(?P<desc>[A-Za-z][A-Za-z0-9\s\/&().,+\-]{2,}?)\s+(?P<qty>[0-9]+(?:\.[0-9]+)?)\s+(?P<unit>(?:RM|MYR|USD|SGD|EUR|GBP|AUD|CNY|JPY|THB|IDR)?\s*[0-9,]+(?:\.[0-9]{2})?)\s+(?P<total>(?:RM|MYR|USD|SGD|EUR|GBP|AUD|CNY|JPY|THB|IDR)?\s*[0-9,]+(?:\.[0-9]{2})?)\s*$/i';

        if (preg_match($pattern, $line, $matches)) {
            return $this->makeItem(
                trim($matches['desc']),
                (float) $matches['qty'],
                $this->parseMoney($matches['unit']),
                $this->parseMoney($matches['total']),
                $this->rateFromLine($line)
            );
        }

        $amounts = $this->moneyValues($line);
        if (count($amounts) >= 2) {
            $last = end($amounts);
            $previous = $amounts[count($amounts) - 2];
            $prefix = trim(preg_replace('/(?:RM|MYR|USD|SGD|EUR|GBP|AUD|CNY|JPY|THB|IDR)?\s*[0-9,]+(?:\.[0-9]{2})?\s*$/i', '', $line) ?? $line);
            $parts = preg_split('/\s+/', $prefix) ?: [];
            $quantity = null;

            if (count($parts) > 1 && is_numeric(end($parts))) {
                $quantity = (float) array_pop($parts);
            }

            return $this->makeItem(implode(' ', $parts), $quantity, $previous, $last, $this->rateFromLine($line));
        }

        if (count($amounts) === 1 && $insideTable) {
            $description = trim(preg_replace('/(?:RM|MYR|USD|SGD|EUR|GBP|AUD|CNY|JPY|THB|IDR)?\s*[0-9,]+(?:\.[0-9]{2})?\s*$/i', '', $line) ?? $line);

            if ($this->looksLikeDescriptionOnlyLine($description)) {
                return $this->makeItem($description, 1, $amounts[0], $amounts[0], $this->rateFromLine($line));
            }
        }

        return null;
    }

    private function makeItem(string $description, ?float $quantity, ?float $unitPrice, ?float $lineTotal, ?float $taxRate = null): array
    {
        $quantity = $quantity !== null && $quantity > 0 ? $quantity : 1.0;

        if ($lineTotal === null && $unitPrice !== null) {
            $lineTotal = round($quantity * $unitPrice, 2);
        }

        if ($unitPrice === null && $lineTotal !== null && $quantity > 0) {
            $unitPrice = round($lineTotal / $quantity, 2);
        }

        $description = trim(preg_replace('/\s+/', ' ', $description) ?? $description);

        return [
            'item_name' => Str::limit($description, 191, ''),
            'description' => '',
            'quantity' => round($quantity, 2),
            'unit_price' => round((float) ($unitPrice ?? 0), 2),
            'tax_rate' => $taxRate,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'line_total' => round((float) ($lineTotal ?? 0), 2),
        ];
    }

    private function calculationWarnings(array $fields, float $lineTotalSum): array
    {
        $warnings = [];

        if (count($fields['items']) === 0) {
            $warnings[] = 'No invoice items were detected. Please add item rows manually before saving.';
        }

        if ($lineTotalSum > 0 && $fields['subtotal'] !== null && abs($lineTotalSum - (float) $fields['subtotal']) > 0.05) {
            $warnings[] = 'Item total does not match subtotal. Please check item rows and subtotal.';
        }

        if ($fields['subtotal'] !== null && $fields['total_amount'] !== null) {
            $expected = round(
                (float) $fields['subtotal']
                - (float) ($fields['discount_amount'] ?? 0)
                + (float) ($fields['service_charge'] ?? 0)
                + (float) ($fields['tax_amount'] ?? 0),
                2
            );

            if (abs($expected - (float) $fields['total_amount']) > 0.05) {
                $warnings[] = 'Subtotal plus tax/charges does not match grand total. Please verify the totals.';
            }
        }

        foreach (['invoice_number' => 'Invoice number', 'invoice_date' => 'Invoice date', 'customer_name' => 'Customer name'] as $key => $label) {
            if (! filled($fields[$key])) {
                $warnings[] = $label.' is missing.';
            }
        }

        return $warnings;
    }

    private function fieldConfidences(array $fields, array $warnings, float $lineTotalSum): array
    {
        return [
            'invoice_number' => filled($fields['invoice_number']) ? 'High' : 'Missing',
            'invoice_date' => filled($fields['invoice_date']) ? 'Medium' : 'Missing',
            'customer_name' => filled($fields['customer_name']) ? 'Medium' : 'Missing',
            'item_rows' => count($fields['items']) > 1 ? 'High' : (count($fields['items']) === 1 ? 'Medium' : 'Missing'),
            'tax_amount' => $fields['tax_amount'] !== null ? ($fields['tax_rate'] !== null ? 'High' : 'Medium') : 'Missing',
            'grand_total' => $fields['total_amount'] !== null && ! Str::contains(implode(' ', $warnings), 'grand total') ? 'High' : ($fields['total_amount'] !== null ? 'Medium' : 'Missing'),
            'calculation_match' => $lineTotalSum > 0 && $fields['subtotal'] !== null && abs($lineTotalSum - (float) $fields['subtotal']) <= 0.05 ? 'High' : 'Low',
        ];
    }

    private function matchCurrency(string $text): string
    {
        if (preg_match('/\b(MYR|USD|SGD|EUR|GBP|AUD|CNY|JPY|THB|IDR)\b/i', $text, $matches)) {
            return strtoupper($matches[1]);
        }

        if (preg_match('/\bRM\s*[0-9]/i', $text)) {
            return 'MYR';
        }

        return 'MYR';
    }

    private function matchPaymentStatus(string $text): string
    {
        foreach (['cancelled', 'overdue', 'partial', 'paid', 'unpaid', 'pending'] as $status) {
            if (preg_match('/\b'.$status.'\b/i', $text)) {
                return $status;
            }
        }

        return 'pending';
    }

    private function looksLikeItemHeader(string $line): bool
    {
        $line = Str::lower($line);

        return (Str::contains($line, ['description', 'item', 'service', 'particular']))
            && Str::contains($line, ['qty', 'quantity', 'unit', 'price', 'amount', 'total']);
    }

    private function looksLikeDescriptionOnlyLine(string $line): bool
    {
        $line = trim($line);

        return strlen($line) >= 3
            && preg_match('/[A-Za-z]/', $line)
            && ! $this->isMetadataLine($line)
            && ! $this->isSummaryLine($line);
    }

    private function isMetadataLine(string $line): bool
    {
        return (bool) preg_match('/\b(invoice|date|bill to|sold to|customer|client|address|email|phone|tel|fax|registration|no\.?)\b/i', $line);
    }

    private function isSummaryLine(string $line): bool
    {
        return (bool) preg_match('/\b(subtotal|sub total|grand total|total amount|amount due|balance due|invoice total|net total|tax|sst|gst|vat|discount|rebate|service charge|service fee|handling fee|paid amount)\b/i', $line);
    }

    private function lastMoneyValue(string $line): ?float
    {
        $values = $this->moneyValues($line);

        return $values === [] ? null : end($values);
    }

    private function moneyValues(string $line): array
    {
        preg_match_all('/(?:RM|MYR|USD|SGD|EUR|GBP|AUD|CNY|JPY|THB|IDR)?\s*-?\(?[0-9]{1,3}(?:,[0-9]{3})*(?:\.[0-9]{2})?\)?|(?:RM|MYR|USD|SGD|EUR|GBP|AUD|CNY|JPY|THB|IDR)?\s*-?[0-9]+(?:\.[0-9]{2})/i', $line, $matches);

        return collect($matches[0] ?? [])
            ->map(fn ($value) => $this->parseMoney($value))
            ->filter(fn ($value) => $value !== null)
            ->values()
            ->all();
    }

    private function parseMoney(string $value): ?float
    {
        $negative = Str::contains($value, '(') || Str::startsWith(trim($value), '-');
        $cleaned = preg_replace('/[^0-9.]/', '', $value);

        if ($cleaned === null || $cleaned === '') {
            return null;
        }

        $amount = (float) $cleaned;

        return $negative ? -$amount : $amount;
    }

    private function rateFromLine(string $line): ?float
    {
        return preg_match('/([0-9]{1,2}(?:\.[0-9]+)?)\s*%/', $line, $matches)
            ? (float) $matches[1]
            : null;
    }

}
