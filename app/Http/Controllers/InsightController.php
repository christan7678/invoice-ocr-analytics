<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class InsightController extends Controller
{
    public function __invoke(Request $request): View
    {
        $company = $request->user()->company;
        $latestInvoice = $company->invoices()
            ->with('customer')
            ->latest('invoice_date')
            ->first();

        if (! $latestInvoice) {
            return view('insights.index', [
                'summary' => 'No verified invoice data is available yet. Add invoices first, then the system will generate evidence-based sales insights.',
                'evidence' => [
                    'Analysis status' => 'No invoice records found',
                    'Analysis month' => 'Not available',
                    'Analysis month sales' => 'RM0.00',
                    'Previous month sales' => 'RM0.00',
                    'Top customer' => 'Not available',
                    'Outstanding amount' => 'RM0.00',
                ],
            ]);
        }

        $currentMonth = Carbon::now();
        $currentMonthHasInvoices = $company->invoices()
            ->whereBetween('invoice_date', [$currentMonth->copy()->startOfMonth(), $currentMonth->copy()->endOfMonth()])
            ->exists();

        $analysisMonth = $currentMonthHasInvoices ? $currentMonth : $latestInvoice->invoice_date->copy();
        $previousMonth = $analysisMonth->copy()->subMonth();
        $isLatestAvailableMonth = ! $currentMonthHasInvoices;

        $analysisSales = (float) $company->invoices()
            ->whereBetween('invoice_date', [$analysisMonth->copy()->startOfMonth(), $analysisMonth->copy()->endOfMonth()])
            ->sum('total_amount_myr');

        $previousSales = (float) $company->invoices()
            ->whereBetween('invoice_date', [$previousMonth->copy()->startOfMonth(), $previousMonth->copy()->endOfMonth()])
            ->sum('total_amount_myr');

        $difference = $analysisSales - $previousSales;
        $percentage = $previousSales > 0 ? ($difference / $previousSales) * 100 : null;

        $topCustomer = $company->invoices()
            ->with('customer')
            ->whereBetween('invoice_date', [$analysisMonth->copy()->startOfMonth(), $analysisMonth->copy()->endOfMonth()])
            ->get()
            ->groupBy('customer_id')
            ->map(fn ($invoices) => [
                'name' => optional($invoices->first()->customer)->customer_name ?? 'Unknown customer',
                'total' => (float) $invoices->sum('total_amount_myr'),
            ])
            ->sortByDesc('total')
            ->first();

        $outstanding = (float) $company->invoices()
            ->whereIn('payment_status', ['unpaid', 'pending', 'partial', 'overdue'])
            ->sum('total_amount_myr');

        $summary = $this->summaryText($analysisMonth, $previousMonth, $analysisSales, $previousSales, $difference, $percentage, $topCustomer, $outstanding, $isLatestAvailableMonth);

        return view('insights.index', [
            'summary' => $summary,
            'evidence' => [
                'Analysis mode' => $isLatestAvailableMonth ? 'Latest available invoice month' : 'Current month',
                'Analysis month' => $analysisMonth->format('F Y'),
                'Analysis month sales' => 'RM'.number_format($analysisSales, 2),
                'Previous month' => $previousMonth->format('F Y'),
                'Previous sales' => 'RM'.number_format($previousSales, 2),
                'Difference' => 'RM'.number_format($difference, 2),
                'Percentage change' => $percentage === null ? 'Not available' : number_format($percentage, 2).'%',
                'Top customer' => $topCustomer ? $topCustomer['name'].' (RM'.number_format($topCustomer['total'], 2).')' : 'Not available',
                'Outstanding amount' => 'RM'.number_format($outstanding, 2),
            ],
        ]);
    }

    private function summaryText(Carbon $analysisMonth, Carbon $previousMonth, float $analysisSales, float $previousSales, float $difference, ?float $percentage, ?array $topCustomer, float $outstanding, bool $isLatestAvailableMonth): string
    {
        if ($analysisSales === 0.0 && $previousSales === 0.0) {
            return 'No verified invoice data is available yet. Add invoices first, then the system will generate evidence-based sales insights.';
        }

        $scopeText = $isLatestAvailableMonth ? 'The latest available invoice month is '.$analysisMonth->format('F Y').'. ' : '';
        $direction = $difference >= 0 ? 'increased' : 'decreased';
        $percentText = $percentage === null ? 'because there was no previous-month baseline' : 'by '.number_format(abs($percentage), 2).'%';
        $customerText = $topCustomer ? ' The highest contributing customer is '.$topCustomer['name'].' with RM'.number_format($topCustomer['total'], 2).' in verified sales.' : '';
        $paymentText = $outstanding > 0 ? ' Outstanding invoices total RM'.number_format($outstanding, 2).', so payment follow-up should be prioritised.' : ' No outstanding invoice amount is currently recorded.';

        return $scopeText.'Sales in '.$analysisMonth->format('F Y').' '.$direction.' '.$percentText.' compared to '.$previousMonth->format('F Y').'.'.$customerText.$paymentText;
    }
}
