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
        $currentMonth = Carbon::now();
        $previousMonth = Carbon::now()->subMonth();

        $currentSales = (float) $company->invoices()
            ->whereBetween('invoice_date', [$currentMonth->copy()->startOfMonth(), $currentMonth->copy()->endOfMonth()])
            ->sum('total_amount_myr');

        $previousSales = (float) $company->invoices()
            ->whereBetween('invoice_date', [$previousMonth->copy()->startOfMonth(), $previousMonth->copy()->endOfMonth()])
            ->sum('total_amount_myr');

        $difference = $currentSales - $previousSales;
        $percentage = $previousSales > 0 ? ($difference / $previousSales) * 100 : null;

        $topCustomer = $company->invoices()
            ->with('customer')
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

        $summary = $this->summaryText($currentMonth, $previousMonth, $currentSales, $previousSales, $difference, $percentage, $topCustomer, $outstanding);

        return view('insights.index', [
            'summary' => $summary,
            'evidence' => [
                'Current month' => $currentMonth->format('F Y'),
                'Current sales' => 'RM'.number_format($currentSales, 2),
                'Previous month' => $previousMonth->format('F Y'),
                'Previous sales' => 'RM'.number_format($previousSales, 2),
                'Difference' => 'RM'.number_format($difference, 2),
                'Percentage change' => $percentage === null ? 'Not available' : number_format($percentage, 2).'%',
                'Top customer' => $topCustomer ? $topCustomer['name'].' (RM'.number_format($topCustomer['total'], 2).')' : 'Not available',
                'Outstanding amount' => 'RM'.number_format($outstanding, 2),
            ],
        ]);
    }

    private function summaryText(Carbon $currentMonth, Carbon $previousMonth, float $currentSales, float $previousSales, float $difference, ?float $percentage, ?array $topCustomer, float $outstanding): string
    {
        if ($currentSales === 0.0 && $previousSales === 0.0) {
            return 'No verified invoice data is available yet. Add invoices first, then the system will generate evidence-based sales insights.';
        }

        $direction = $difference >= 0 ? 'increased' : 'decreased';
        $percentText = $percentage === null ? 'because there was no previous-month baseline' : 'by '.number_format(abs($percentage), 2).'%';
        $customerText = $topCustomer ? ' The highest contributing customer is '.$topCustomer['name'].' with RM'.number_format($topCustomer['total'], 2).' in verified sales.' : '';
        $paymentText = $outstanding > 0 ? ' Outstanding invoices total RM'.number_format($outstanding, 2).', so payment follow-up should be prioritised.' : ' No outstanding invoice amount is currently recorded.';

        return 'Sales in '.$currentMonth->format('F Y').' '.$direction.' '.$percentText.' compared to '.$previousMonth->format('F Y').'.'.$customerText.$paymentText;
    }
}
