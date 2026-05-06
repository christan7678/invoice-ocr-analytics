<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        [$invoices, $summary] = $this->reportData($request);

        return view('reports.index', [
            'company' => $request->user()->company,
            'invoices' => $invoices,
            'summary' => $summary,
            'monthlyBreakdown' => $this->monthlyBreakdown($invoices),
            'customerBreakdown' => $this->customerBreakdown($invoices),
            'statusBreakdown' => $this->statusBreakdown($invoices),
            'currencyBreakdown' => $this->currencyBreakdown($invoices),
            'customers' => $request->user()->company->customers()->orderBy('customer_name')->get(),
            'paymentStatuses' => Invoice::PAYMENT_STATUSES,
            'filters' => $request->only(['report_type', 'date_from', 'date_to', 'customer_id', 'payment_status']),
        ]);
    }

    public function csv(Request $request)
    {
        [$invoices] = $this->reportData($request);
        $fileName = 'sales-report-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($invoices) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Invoice No', 'Date', 'Customer', 'Status', 'Currency', 'Original Total', 'Rate to MYR', 'Total MYR']);

            foreach ($invoices as $invoice) {
                fputcsv($handle, [
                    $invoice->invoice_number,
                    $invoice->invoice_date->format('Y-m-d'),
                    $invoice->customer->customer_name,
                    $invoice->paymentStatusLabel(),
                    $invoice->currency_code,
                    number_format($invoice->total_amount, 2, '.', ''),
                    number_format($invoice->exchange_rate_to_myr, 6, '.', ''),
                    number_format($invoice->total_amount_myr, 2, '.', ''),
                ]);
            }

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    private function reportData(Request $request): array
    {
        $company = $request->user()->company;
        $query = $company->invoices()->with('customer')->orderBy('invoice_date');

        if ($request->filled('date_from')) {
            $query->whereDate('invoice_date', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('invoice_date', '<=', $request->date('date_to'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->string('payment_status'));
        }

        $invoices = $query->get();
        $start = $request->filled('date_from') ? Carbon::parse($request->date('date_from'))->format('d M Y') : 'All dates';
        $end = $request->filled('date_to') ? Carbon::parse($request->date('date_to'))->format('d M Y') : 'Today';

        return [$invoices, [
            'date_range' => $start.' - '.$end,
            'invoice_count' => $invoices->count(),
            'total_myr' => (float) $invoices->sum('total_amount_myr'),
            'paid_myr' => (float) $invoices->where('payment_status', 'paid')->sum('total_amount_myr'),
            'outstanding_myr' => (float) $invoices->whereIn('payment_status', ['unpaid', 'pending', 'partial', 'overdue'])->sum('total_amount_myr'),
        ]];
    }

    private function monthlyBreakdown($invoices)
    {
        return $invoices
            ->groupBy(fn ($invoice) => $invoice->invoice_date->format('Y-m'))
            ->map(fn ($items, $month) => [
                'label' => Carbon::createFromFormat('Y-m', $month)->format('M Y'),
                'count' => $items->count(),
                'total_myr' => (float) $items->sum('total_amount_myr'),
            ])
            ->values();
    }

    private function customerBreakdown($invoices)
    {
        return $invoices
            ->groupBy('customer_id')
            ->map(fn ($items) => [
                'label' => $items->first()->customer->customer_name,
                'count' => $items->count(),
                'total_myr' => (float) $items->sum('total_amount_myr'),
            ])
            ->sortByDesc('total_myr')
            ->values();
    }

    private function statusBreakdown($invoices)
    {
        return $invoices
            ->groupBy('payment_status')
            ->map(fn ($items, $status) => [
                'label' => Invoice::PAYMENT_STATUSES[$status] ?? ucfirst($status),
                'count' => $items->count(),
                'total_myr' => (float) $items->sum('total_amount_myr'),
            ])
            ->sortByDesc('total_myr')
            ->values();
    }

    private function currencyBreakdown($invoices)
    {
        return $invoices
            ->groupBy('currency_code')
            ->map(fn ($items, $currency) => [
                'label' => $currency,
                'count' => $items->count(),
                'original_total' => (float) $items->sum('total_amount'),
                'total_myr' => (float) $items->sum('total_amount_myr'),
            ])
            ->sortByDesc('total_myr')
            ->values();
    }
}
