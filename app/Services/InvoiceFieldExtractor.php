<?php

namespace App\Services;

use Carbon\Carbon;

class InvoiceFieldExtractor
{
    public function extract(?string $text): array
    {
        $text = trim((string) $text);

        if ($text === '') {
            return $this->emptyResult();
        }

        $fields = [
            'invoice_number' => $this->matchInvoiceNumber($text),
            'invoice_date' => $this->matchDate($text),
            'customer_name' => $this->matchCustomerName($text),
            'subtotal' => $this->matchAmount($text, ['subtotal', 'sub total']),
            'tax_amount' => $this->matchAmount($text, ['tax', 'sst', 'gst']),
            'discount_amount' => $this->matchAmount($text, ['discount']),
            'total_amount' => $this->matchAmount($text, ['grand total', 'total amount', 'amount due', 'total']),
            'currency_code' => $this->matchCurrency($text),
            'payment_status' => $this->matchPaymentStatus($text),
        ];

        $required = ['invoice_number', 'invoice_date', 'customer_name', 'total_amount', 'payment_status'];
        $found = collect($required)->filter(fn ($key) => filled($fields[$key]))->count();

        $fields['confidence_score'] = round(($found / count($required)) * 100, 2);

        return $fields;
    }

    private function emptyResult(): array
    {
        return [
            'invoice_number' => null,
            'invoice_date' => null,
            'customer_name' => null,
            'subtotal' => null,
            'tax_amount' => null,
            'discount_amount' => null,
            'total_amount' => null,
            'currency_code' => 'MYR',
            'payment_status' => 'pending',
            'confidence_score' => 0,
        ];
    }

    private function matchInvoiceNumber(string $text): ?string
    {
        $patterns = [
            '/invoice\s*(?:no\.?|number|#)\s*[:\-]?\s*([A-Z0-9][A-Z0-9\-\/]+)/i',
            '/inv\s*(?:no\.?|#)?\s*[:\-]?\s*([A-Z0-9][A-Z0-9\-\/]+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return strtoupper(trim($matches[1]));
            }
        }

        return null;
    }

    private function matchDate(string $text): ?string
    {
        $patterns = [
            '/invoice\s*date\s*[:\-]?\s*([0-9]{1,2}[\/\-.][0-9]{1,2}[\/\-.][0-9]{2,4})/i',
            '/date\s*[:\-]?\s*([0-9]{1,2}[\/\-.][0-9]{1,2}[\/\-.][0-9]{2,4})/i',
            '/([0-9]{4}[\/\-.][0-9]{1,2}[\/\-.][0-9]{1,2})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return $this->normaliseDate($matches[1]);
            }
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

    private function matchCustomerName(string $text): ?string
    {
        $patterns = [
            '/(?:bill\s*to|sold\s*to|customer|client)\s*[:.\-]?\s*(.+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim(substr($matches[1], 0, 191));
            }
        }

        return null;
    }

    private function matchAmount(string $text, array $labels): ?float
    {
        foreach ($labels as $label) {
            $pattern = '/'.$label.'\s*[:\-]?\s*(?:RM|MYR|USD|SGD|EUR|GBP|AUD|CNY|JPY)?\s*([0-9,]+(?:\.[0-9]{2})?)/i';

            if (preg_match_all($pattern, $text, $matches) && filled($matches[1])) {
                return (float) str_replace(',', '', end($matches[1]));
            }
        }

        return null;
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
}
