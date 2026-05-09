<?php

namespace App\Services;

class InvoiceCalculationService
{
    public function calculate(array $validated): array
    {
        $items = $this->normaliseItems($validated['items'] ?? []);
        $itemSubtotal = round((float) collect($items)->sum('line_total'), 2);
        $itemTax = round((float) collect($items)->sum('tax_amount'), 2);
        $itemDiscount = round((float) collect($items)->sum('discount_amount'), 2);

        $subtotal = $this->numberOrNull($validated['subtotal'] ?? null);
        $taxAmount = $this->numberOrNull($validated['tax_amount'] ?? null);
        $discount = $this->numberOrNull($validated['discount_amount'] ?? null);
        $serviceCharge = $this->numberOrNull($validated['service_charge'] ?? null);
        $total = $this->numberOrNull($validated['total_amount'] ?? null);
        $taxRate = $this->numberOrNull($validated['tax_rate'] ?? null);
        $warnings = [];

        if ($subtotal === null) {
            $subtotal = $itemSubtotal;
        }

        if ($discount === null) {
            $discount = $itemDiscount;
        }

        if ($taxAmount === null && $itemTax > 0) {
            $taxAmount = $itemTax;
        }

        if ($taxAmount === null && $subtotal > 0 && $taxRate !== null) {
            $taxAmount = round($subtotal * ($taxRate / 100), 2);
            $warnings[] = 'Tax amount was calculated from subtotal and tax rate.';
        }

        $serviceCharge ??= 0.0;
        $taxAmount ??= 0.0;
        $discount ??= 0.0;

        $expectedTotal = round(max($subtotal - $discount + $serviceCharge + $taxAmount, 0), 2);

        if ($total === null) {
            $total = $expectedTotal;
        }

        if ($itemSubtotal > 0 && abs($itemSubtotal - $subtotal) > 0.05) {
            $warnings[] = 'Item line total sum does not match the subtotal.';
        }

        if (abs($expectedTotal - $total) > 0.05) {
            $warnings[] = 'Subtotal, discount, service charge, and tax do not match the grand total.';
        }

        return [
            'items' => $items,
            'subtotal' => round($subtotal, 2),
            'tax_rate' => $taxRate === null ? null : round($taxRate, 2),
            'tax_amount' => round($taxAmount, 2),
            'discount_amount' => round($discount, 2),
            'service_charge' => round($serviceCharge, 2),
            'total_amount' => round(max($total, 0), 2),
            'expected_total' => $expectedTotal,
            'item_subtotal' => $itemSubtotal,
            'warnings' => $warnings,
        ];
    }

    public function normaliseItems(array $items): array
    {
        return collect($items)
            ->filter(fn ($item) => filled($item['item_name'] ?? null))
            ->map(function (array $item) {
                $quantity = max((float) ($item['quantity'] ?? 1), 0);
                $unitPrice = max((float) ($item['unit_price'] ?? 0), 0);
                $taxAmount = max((float) ($item['tax_amount'] ?? 0), 0);
                $discountAmount = max((float) ($item['discount_amount'] ?? 0), 0);
                $lineTotal = $this->numberOrNull($item['line_total'] ?? null);

                if ($lineTotal === null) {
                    $lineTotal = round($quantity * $unitPrice, 2);
                }

                if ($unitPrice <= 0 && $quantity > 0 && $lineTotal > 0) {
                    $unitPrice = round($lineTotal / $quantity, 2);
                }

                return [
                    'item_name' => $item['item_name'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $this->numberOrNull($item['tax_rate'] ?? null),
                    'tax_amount' => $taxAmount,
                    'discount_amount' => $discountAmount,
                    'line_total' => round(max($lineTotal, 0), 2),
                ];
            })
            ->values()
            ->all();
    }

    private function numberOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
