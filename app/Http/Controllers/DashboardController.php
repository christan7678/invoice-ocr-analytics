<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $company = $request->user()->company;
        $now = Carbon::now();

        $monthInvoices = $company->invoices()
            ->with('customer')
            ->whereBetween('invoice_date', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->get();

        $allInvoices = $company->invoices()->with('customer')->get();

        $trend = collect(range(5, 0))->map(function (int $monthsAgo) use ($company, $now) {
            $month = $now->copy()->subMonths($monthsAgo);

            return [
                'label' => $month->format('M Y'),
                'total' => (float) $company->invoices()
                    ->whereBetween('invoice_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                    ->sum('total_amount_myr'),
            ];
        });

        $paidAmount = (float) $allInvoices->where('payment_status', 'paid')->sum('total_amount_myr');
        $unpaidAmount = (float) $allInvoices->whereIn('payment_status', ['unpaid', 'pending', 'partial', 'overdue'])->sum('total_amount_myr');

        $topCustomers = $allInvoices
            ->groupBy('customer_id')
            ->map(fn ($invoices) => [
                'name' => optional($invoices->first()->customer)->customer_name ?? 'Unknown customer',
                'total' => (float) $invoices->sum('total_amount_myr'),
            ])
            ->sortByDesc('total')
            ->take(5)
            ->values();

        $monthlyTotals = $allInvoices
            ->groupBy(fn ($invoice) => $invoice->invoice_date->format('M Y'))
            ->map(fn ($invoices, $month) => ['month' => $month, 'total' => (float) $invoices->sum('total_amount_myr')])
            ->values();

        return view('dashboard', [
            'company' => $company,
            'totalSalesThisMonth' => (float) $monthInvoices->sum('total_amount_myr'),
            'invoiceCountThisMonth' => $monthInvoices->count(),
            'unpaidAmount' => $unpaidAmount,
            'paidAmount' => $paidAmount,
            'trend' => $trend,
            'topCustomers' => $topCustomers,
            'highestMonth' => $monthlyTotals->sortByDesc('total')->first(),
            'lowestMonth' => $monthlyTotals->where('total', '>', 0)->sortBy('total')->first(),
        ]);
    }
}
