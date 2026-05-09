<?php

namespace App\Services;

use Illuminate\Support\Collection;

class ReportExplanationService
{
    public function explain(Collection $invoices, array $summary, array $customerBreakdown): string
    {
        if ($summary['invoice_count'] === 0) {
            return 'This report does not contain any verified invoice records for the selected filters.';
        }

        $topCustomer = collect($customerBreakdown)->first();
        $dateRange = $summary['date_range'];
        $outstandingText = $summary['outstanding_myr'] > 0
            ? 'RM'.number_format($summary['outstanding_myr'], 2).' remains outstanding'
            : 'no outstanding amount is recorded';
        $customerText = $topCustomer
            ? ' The highest contributing customer was '.$topCustomer['label'].' with RM'.number_format($topCustomer['total_myr'], 2).'.'
            : '';

        return 'This report contains '.$summary['invoice_count'].' verified invoice record(s) for '.$dateRange.'. Total sales were RM'.number_format($summary['total_myr'], 2).'. Paid invoices contributed RM'.number_format($summary['paid_myr'], 2).', while '.$outstandingText.'.'.$customerText;
    }
}
