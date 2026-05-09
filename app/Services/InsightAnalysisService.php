<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class InsightAnalysisService
{
    public function __construct(private GeminiInsightService $gemini)
    {
    }

    public function analyse(Company $company): array
    {
        $allInvoices = $company->invoices()->with(['customer', 'items'])->get();
        $latestInvoice = $allInvoices->sortByDesc('invoice_date')->first();

        if (! $latestInvoice) {
            return [
                'has_data' => false,
                'summary' => 'No verified invoice data is available yet. Add invoices first, then the system will generate evidence-based sales insights.',
                'evidence' => [
                    'Analysis status' => 'No invoice records found',
                    'Analysis month' => 'Not available',
                    'Analysis month sales' => 'RM0.00',
                    'Previous month sales' => 'RM0.00',
                    'Top customer' => 'Not available',
                    'Outstanding amount' => 'RM0.00',
                ],
                'observations' => [],
                'recommendations' => [],
                'risk_warnings' => [],
                'customer_payment_analysis' => [],
                'collection_forecast' => [],
                'item_analytics' => [],
                'ai' => ['enabled' => false, 'source' => 'Rule-based fallback'],
            ];
        }

        $currentMonth = Carbon::now();
        $currentMonthHasInvoices = $allInvoices->contains(
            fn (Invoice $invoice) => $invoice->invoice_date->betweenIncluded($currentMonth->copy()->startOfMonth(), $currentMonth->copy()->endOfMonth())
        );
        $analysisMonth = $currentMonthHasInvoices ? $currentMonth : $latestInvoice->invoice_date->copy();
        $previousMonth = $analysisMonth->copy()->subMonth();
        $analysisInvoices = $this->inMonth($allInvoices, $analysisMonth);
        $previousInvoices = $this->inMonth($allInvoices, $previousMonth);
        $paymentStatuses = ['unpaid', 'pending', 'partial', 'overdue'];

        $analysisSales = (float) $analysisInvoices->sum('total_amount_myr');
        $previousSales = (float) $previousInvoices->sum('total_amount_myr');
        $difference = round($analysisSales - $previousSales, 2);
        $percentage = $previousSales > 0 ? round(($difference / $previousSales) * 100, 2) : null;
        $paidAmount = (float) $analysisInvoices->where('payment_status', 'paid')->sum('total_amount_myr');
        $outstandingAmount = (float) $analysisInvoices->whereIn('payment_status', $paymentStatuses)->sum('total_amount_myr');
        $overdueAmount = (float) $allInvoices
            ->whereIn('payment_status', $paymentStatuses)
            ->filter(fn (Invoice $invoice) => $invoice->due_date?->isPast())
            ->sum('total_amount_myr');

        $topCustomer = $this->topCustomer($analysisInvoices);
        $largestInvoice = $analysisInvoices->sortByDesc('total_amount_myr')->first();
        $smallestInvoice = $analysisInvoices->where('total_amount_myr', '>', 0)->sortBy('total_amount_myr')->first();

        $metrics = [
            'analysis_mode' => $currentMonthHasInvoices ? 'Current month' : 'Latest available invoice month',
            'analysis_month' => $analysisMonth->format('F Y'),
            'previous_month' => $previousMonth->format('F Y'),
            'current_sales_myr' => $analysisSales,
            'previous_sales_myr' => $previousSales,
            'sales_difference_myr' => $difference,
            'percentage_change' => $percentage,
            'invoice_count' => $analysisInvoices->count(),
            'paid_amount_myr' => $paidAmount,
            'outstanding_amount_myr' => $outstandingAmount,
            'overdue_amount_myr' => $overdueAmount,
            'tax_collected_myr' => (float) $analysisInvoices->sum('tax_amount'),
            'discount_total_myr' => (float) $analysisInvoices->sum('discount_amount'),
            'service_charge_total_myr' => (float) $analysisInvoices->sum('service_charge'),
            'average_invoice_value_myr' => $analysisInvoices->count() > 0 ? round($analysisSales / $analysisInvoices->count(), 2) : 0,
            'top_customer' => $topCustomer,
            'largest_invoice' => $largestInvoice ? [
                'invoice_number' => $largestInvoice->invoice_number,
                'customer' => $largestInvoice->customer?->customer_name,
                'amount_myr' => (float) $largestInvoice->total_amount_myr,
            ] : null,
            'smallest_invoice' => $smallestInvoice ? [
                'invoice_number' => $smallestInvoice->invoice_number,
                'customer' => $smallestInvoice->customer?->customer_name,
                'amount_myr' => (float) $smallestInvoice->total_amount_myr,
            ] : null,
            'payment_status_breakdown' => $this->statusBreakdown($analysisInvoices),
            'currency_breakdown' => $this->currencyBreakdown($analysisInvoices),
        ];

        $ruleBased = [
            'summary' => $this->summaryText($metrics),
            'key_observations' => $this->observations($metrics),
            'recommendations' => $this->recommendations($metrics),
            'risk_warnings' => $this->riskWarnings($metrics),
        ];
        $ai = $this->gemini->rewrite($metrics, $ruleBased);
        $final = $ai ?: array_merge($ruleBased, ['source' => 'Rule-based fallback']);

        return [
            'has_data' => true,
            'summary' => $final['summary'],
            'evidence' => $this->evidence($metrics),
            'observations' => $final['key_observations'] ?: $ruleBased['key_observations'],
            'recommendations' => $final['recommendations'] ?: $ruleBased['recommendations'],
            'risk_warnings' => $final['risk_warnings'] ?: $ruleBased['risk_warnings'],
            'customer_payment_analysis' => $this->customerPaymentAnalysis($allInvoices),
            'collection_forecast' => $this->collectionForecast($allInvoices),
            'item_analytics' => $this->itemAnalytics($analysisInvoices),
            'ai' => [
                'enabled' => (bool) $ai,
                'source' => $final['source'],
            ],
            'metrics' => $metrics,
        ];
    }

    private function inMonth(Collection $invoices, Carbon $month): Collection
    {
        return $invoices->filter(
            fn (Invoice $invoice) => $invoice->invoice_date->betweenIncluded($month->copy()->startOfMonth(), $month->copy()->endOfMonth())
        )->values();
    }

    private function topCustomer(Collection $invoices): ?array
    {
        $totalSales = (float) $invoices->sum('total_amount_myr');

        $top = $invoices
            ->groupBy('customer_id')
            ->map(fn ($customerInvoices) => [
                'name' => optional($customerInvoices->first()->customer)->customer_name ?? 'Unknown customer',
                'sales_myr' => (float) $customerInvoices->sum('total_amount_myr'),
                'invoice_count' => $customerInvoices->count(),
            ])
            ->sortByDesc('sales_myr')
            ->first();

        if (! $top) {
            return null;
        }

        $top['contribution_percentage'] = $totalSales > 0 ? round(($top['sales_myr'] / $totalSales) * 100, 2) : 0;

        return $top;
    }

    private function statusBreakdown(Collection $invoices): array
    {
        return $invoices
            ->groupBy('payment_status')
            ->map(fn ($items, $status) => [
                'label' => Invoice::PAYMENT_STATUSES[$status] ?? ucfirst((string) $status),
                'amount_myr' => (float) $items->sum('total_amount_myr'),
                'count' => $items->count(),
            ])
            ->sortByDesc('amount_myr')
            ->values()
            ->all();
    }

    private function currencyBreakdown(Collection $invoices): array
    {
        return $invoices
            ->groupBy('currency_code')
            ->map(fn ($items, $currency) => [
                'currency' => $currency,
                'original_total' => (float) $items->sum('total_amount'),
                'amount_myr' => (float) $items->sum('total_amount_myr'),
                'count' => $items->count(),
            ])
            ->sortByDesc('amount_myr')
            ->values()
            ->all();
    }

    private function summaryText(array $metrics): string
    {
        $direction = $metrics['sales_difference_myr'] >= 0 ? 'increased' : 'decreased';
        $percentText = $metrics['percentage_change'] === null
            ? 'because there was no previous-month baseline'
            : 'by '.number_format(abs($metrics['percentage_change']), 2).'%';
        $scopeText = $metrics['analysis_mode'] === 'Latest available invoice month'
            ? 'The latest available invoice month is '.$metrics['analysis_month'].'. '
            : '';
        $customerText = $metrics['top_customer']
            ? ' The highest contributing customer was '.$metrics['top_customer']['name'].' with RM'.number_format($metrics['top_customer']['sales_myr'], 2).' in sales.'
            : '';
        $paymentText = $metrics['outstanding_amount_myr'] > 0
            ? ' RM'.number_format($metrics['outstanding_amount_myr'], 2).' remains outstanding.'
            : ' No outstanding amount is recorded for the analysis month.';

        return $scopeText.'Sales in '.$metrics['analysis_month'].' '.$direction.' '.$percentText.' compared with '.$metrics['previous_month'].'.'.$customerText.$paymentText;
    }

    private function observations(array $metrics): array
    {
        $observations = [
            'The analysis is based on '.$metrics['invoice_count'].' verified invoice record(s) for '.$metrics['analysis_month'].'.',
            'Average invoice value is RM'.number_format($metrics['average_invoice_value_myr'], 2).'.',
        ];

        if ($metrics['top_customer']) {
            $observations[] = $metrics['top_customer']['name'].' contributes '.number_format($metrics['top_customer']['contribution_percentage'], 2).'% of sales in the analysis month.';
        }

        if ($metrics['largest_invoice']) {
            $observations[] = 'Largest invoice is '.$metrics['largest_invoice']['invoice_number'].' at RM'.number_format($metrics['largest_invoice']['amount_myr'], 2).'.';
        }

        return $observations;
    }

    private function recommendations(array $metrics): array
    {
        $recommendations = [];

        if ($metrics['sales_difference_myr'] >= 0) {
            $recommendations[] = 'Maintain the activities or customer relationships that contributed to the sales increase.';
        } else {
            $recommendations[] = 'Review customers with lower activity and follow up on missed or delayed sales opportunities.';
        }

        if ($metrics['outstanding_amount_myr'] > 0) {
            $recommendations[] = 'Prioritise payment follow-up for unpaid, pending, partial, or overdue invoices.';
        }

        if (($metrics['top_customer']['contribution_percentage'] ?? 0) >= 50) {
            $recommendations[] = 'Continue serving the top customer well, but reduce dependency risk by developing additional customers.';
        }

        if (count($metrics['currency_breakdown']) > 1) {
            $recommendations[] = 'Monitor foreign-currency invoices because exchange-rate changes affect MYR reporting value.';
        }

        return $recommendations;
    }

    private function riskWarnings(array $metrics): array
    {
        $warnings = [];

        if ($metrics['current_sales_myr'] > 0 && ($metrics['outstanding_amount_myr'] / $metrics['current_sales_myr']) >= 0.25) {
            $warnings[] = 'Outstanding amount is more than 25% of analysis-month sales.';
        }

        if (($metrics['top_customer']['contribution_percentage'] ?? 0) >= 50) {
            $warnings[] = 'One customer contributes more than half of the analysis-month sales.';
        }

        if ($metrics['overdue_amount_myr'] > 0) {
            $warnings[] = 'There are overdue invoices that require follow-up.';
        }

        return $warnings;
    }

    private function evidence(array $metrics): array
    {
        return [
            'Analysis mode' => $metrics['analysis_mode'],
            'Analysis month' => $metrics['analysis_month'],
            'Analysis month sales' => 'RM'.number_format($metrics['current_sales_myr'], 2),
            'Previous month' => $metrics['previous_month'],
            'Previous sales' => 'RM'.number_format($metrics['previous_sales_myr'], 2),
            'Difference' => 'RM'.number_format($metrics['sales_difference_myr'], 2),
            'Percentage change' => $metrics['percentage_change'] === null ? 'Not available' : number_format($metrics['percentage_change'], 2).'%',
            'Invoice count' => (string) $metrics['invoice_count'],
            'Paid amount' => 'RM'.number_format($metrics['paid_amount_myr'], 2),
            'Outstanding amount' => 'RM'.number_format($metrics['outstanding_amount_myr'], 2),
            'Overdue amount' => 'RM'.number_format($metrics['overdue_amount_myr'], 2),
            'Tax collected' => 'RM'.number_format($metrics['tax_collected_myr'], 2),
            'Average invoice value' => 'RM'.number_format($metrics['average_invoice_value_myr'], 2),
            'Top customer' => $metrics['top_customer'] ? $metrics['top_customer']['name'].' (RM'.number_format($metrics['top_customer']['sales_myr'], 2).')' : 'Not available',
        ];
    }

    private function customerPaymentAnalysis(Collection $allInvoices): array
    {
        return $allInvoices
            ->groupBy('customer_id')
            ->map(function ($invoices) {
                $outstandingStatuses = ['unpaid', 'pending', 'partial', 'overdue'];
                $outstanding = (float) $invoices->whereIn('payment_status', $outstandingStatuses)->sum('total_amount_myr');
                $overdueCount = $invoices
                    ->whereIn('payment_status', $outstandingStatuses)
                    ->filter(fn (Invoice $invoice) => $invoice->due_date?->isPast())
                    ->count();
                $invoiceCount = $invoices->count();
                $unpaidCount = $invoices->whereIn('payment_status', $outstandingStatuses)->count();
                $risk = 'Low';

                if ($overdueCount > 0 || ($invoiceCount > 0 && ($unpaidCount / $invoiceCount) >= 0.5)) {
                    $risk = 'High';
                } elseif ($outstanding > 0 || $unpaidCount > 0) {
                    $risk = 'Medium';
                }

                return [
                    'customer' => optional($invoices->first()->customer)->customer_name ?? 'Unknown customer',
                    'total_sales_myr' => (float) $invoices->sum('total_amount_myr'),
                    'paid_amount_myr' => (float) $invoices->where('payment_status', 'paid')->sum('total_amount_myr'),
                    'outstanding_amount_myr' => $outstanding,
                    'invoice_count' => $invoiceCount,
                    'paid_invoice_count' => $invoices->where('payment_status', 'paid')->count(),
                    'unpaid_invoice_count' => $unpaidCount,
                    'overdue_invoice_count' => $overdueCount,
                    'average_invoice_value_myr' => $invoiceCount > 0 ? round((float) $invoices->sum('total_amount_myr') / $invoiceCount, 2) : 0,
                    'last_invoice_date' => optional($invoices->sortByDesc('invoice_date')->first()?->invoice_date)->format('d M Y'),
                    'risk_level' => $risk,
                ];
            })
            ->sortByDesc('outstanding_amount_myr')
            ->values()
            ->take(8)
            ->all();
    }

    private function collectionForecast(Collection $allInvoices): array
    {
        $statuses = ['unpaid', 'pending', 'partial', 'overdue'];
        $openInvoices = $allInvoices->whereIn('payment_status', $statuses);
        $now = Carbon::now();
        $dueSoon = $openInvoices->filter(fn (Invoice $invoice) => $invoice->due_date && $invoice->due_date->betweenIncluded($now, $now->copy()->addDays(7)));
        $overdue = $openInvoices->filter(fn (Invoice $invoice) => $invoice->due_date?->isPast());
        $priority = $openInvoices
            ->sortByDesc(fn (Invoice $invoice) => ($invoice->due_date?->isPast() ? 100000000 : 0) + (float) $invoice->total_amount_myr)
            ->take(5)
            ->map(fn (Invoice $invoice) => [
                'invoice_number' => $invoice->invoice_number,
                'customer' => $invoice->customer?->customer_name,
                'amount_myr' => (float) $invoice->total_amount_myr,
                'due_date' => $invoice->due_date?->format('d M Y') ?? '-',
                'status' => $invoice->paymentStatusLabel(),
            ])
            ->values()
            ->all();

        return [
            'total_outstanding_myr' => (float) $openInvoices->sum('total_amount_myr'),
            'due_soon_myr' => (float) $dueSoon->sum('total_amount_myr'),
            'overdue_myr' => (float) $overdue->sum('total_amount_myr'),
            'priority_invoices' => $priority,
        ];
    }

    private function itemAnalytics(Collection $analysisInvoices): array
    {
        return $analysisInvoices
            ->flatMap(fn (Invoice $invoice) => $invoice->items->map(fn ($item) => [
                'item_name' => $item->item_name,
                'quantity' => (float) $item->quantity,
                'revenue_myr' => (float) $item->total_price * (float) $invoice->exchange_rate_to_myr,
            ]))
            ->groupBy(fn ($item) => strtolower($item['item_name']))
            ->map(fn ($items) => [
                'item_name' => $items->first()['item_name'],
                'quantity' => (float) $items->sum('quantity'),
                'revenue' => (float) $items->sum('revenue_myr'),
                'frequency' => $items->count(),
                'average_price' => $items->count() > 0 ? round((float) $items->sum('revenue_myr') / $items->count(), 2) : 0,
            ])
            ->sortByDesc('revenue')
            ->values()
            ->take(5)
            ->all();
    }
}
