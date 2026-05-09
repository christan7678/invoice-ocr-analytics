<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\ReportExplanationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReportController extends Controller
{
    private const REPORT_TYPES = [
        'full' => 'Full sales report',
        'monthly' => 'Monthly sales report',
        'customer' => 'Customer sales report',
        'payment' => 'Paid/unpaid invoice report',
        'currency' => 'Currency conversion report',
    ];

    public function index(Request $request, ReportExplanationService $explanationService): View
    {
        [$invoices, $summary] = $this->reportData($request);
        $customerBreakdown = $this->customerBreakdown($invoices);

        return view('reports.index', [
            'company' => $request->user()->company,
            'invoices' => $invoices,
            'summary' => $summary,
            'monthlyBreakdown' => $this->monthlyBreakdown($invoices),
            'customerBreakdown' => $customerBreakdown,
            'statusBreakdown' => $this->statusBreakdown($invoices),
            'currencyBreakdown' => $this->currencyBreakdown($invoices),
            'customers' => $request->user()->company->customers()->orderBy('customer_name')->get(),
            'paymentStatuses' => Invoice::PAYMENT_STATUSES,
            'reportTypes' => self::REPORT_TYPES,
            'currencies' => $request->user()->company->invoices()->select('currency_code')->distinct()->orderBy('currency_code')->pluck('currency_code'),
            'reportExplanation' => $explanationService->explain($invoices, $summary, $customerBreakdown->all()),
            'filters' => $request->only(['report_type', 'date_from', 'date_to', 'customer_id', 'payment_status', 'currency_code']),
        ]);
    }

    public function csv(Request $request)
    {
        [$invoices] = $this->reportData($request);
        $fileName = 'sales-report-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($invoices) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Invoice No', 'Date', 'Customer', 'Status', 'Currency', 'Subtotal', 'Tax', 'Discount', 'Service Charge', 'Original Total', 'Rate to MYR', 'Total MYR']);

            foreach ($invoices as $invoice) {
                fputcsv($handle, [
                    $invoice->invoice_number,
                    $invoice->invoice_date->format('Y-m-d'),
                    $invoice->customer->customer_name,
                    $invoice->paymentStatusLabel(),
                    $invoice->currency_code,
                    number_format($invoice->subtotal, 2, '.', ''),
                    number_format($invoice->tax_amount, 2, '.', ''),
                    number_format($invoice->discount_amount, 2, '.', ''),
                    number_format($invoice->service_charge ?? 0, 2, '.', ''),
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
        $query = $company->invoices()
            ->with('customer')
            ->verifiedForAnalytics()
            ->orderBy('invoice_date');
        $requestedReportType = (string) $request->input('report_type', 'full');
        $reportType = array_key_exists($requestedReportType, self::REPORT_TYPES) ? $requestedReportType : 'full';
        $activeFilters = [];

        if ($request->filled('date_from')) {
            $query->whereDate('invoice_date', '>=', $request->date('date_from'));
            $activeFilters[] = 'From '.Carbon::parse($request->date('date_from'))->format('d M Y');
        }

        if ($request->filled('date_to')) {
            $query->whereDate('invoice_date', '<=', $request->date('date_to'));
            $activeFilters[] = 'To '.Carbon::parse($request->date('date_to'))->format('d M Y');
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
            $customerName = $company->customers()
                ->whereKey($request->integer('customer_id'))
                ->value('customer_name');
            $activeFilters[] = 'Customer: '.($customerName ?: 'Selected customer');
        }

        if ($request->filled('payment_status')) {
            $paymentStatus = (string) $request->input('payment_status');
            $query->where('payment_status', $paymentStatus);
            $activeFilters[] = 'Payment: '.(Invoice::PAYMENT_STATUSES[$paymentStatus] ?? ucfirst($paymentStatus));
        }

        if ($request->filled('currency_code')) {
            $currency = strtoupper((string) $request->input('currency_code'));
            $query->where('currency_code', $currency);
            $activeFilters[] = 'Currency: '.$currency;
        }

        $invoices = $query->get();
        $start = $request->filled('date_from') ? Carbon::parse($request->date('date_from'))->format('d M Y') : 'All dates';
        $end = $request->filled('date_to') ? Carbon::parse($request->date('date_to'))->format('d M Y') : 'Today';

        return [$invoices, [
            'report_type' => $reportType,
            'report_type_label' => self::REPORT_TYPES[$reportType],
            'date_range' => $start.' - '.$end,
            'active_filters' => $activeFilters,
            'has_filters' => count($activeFilters) > 0,
            'generated_at' => now(),
            'invoice_count' => $invoices->count(),
            'total_myr' => (float) $invoices->sum('total_amount_myr'),
            'paid_myr' => (float) $invoices->where('payment_status', 'paid')->sum('total_amount_myr'),
            'outstanding_myr' => (float) $invoices->whereIn('payment_status', ['unpaid', 'pending', 'partial', 'overdue'])->sum('total_amount_myr'),
            'tax_total_myr' => (float) $invoices->sum(fn ($invoice) => (float) $invoice->tax_amount * (float) $invoice->exchange_rate_to_myr),
            'discount_total_myr' => (float) $invoices->sum(fn ($invoice) => (float) $invoice->discount_amount * (float) $invoice->exchange_rate_to_myr),
            'service_charge_total_myr' => (float) $invoices->sum(fn ($invoice) => (float) ($invoice->service_charge ?? 0) * (float) $invoice->exchange_rate_to_myr),
            'average_invoice_myr' => $invoices->count() > 0 ? round((float) $invoices->sum('total_amount_myr') / $invoices->count(), 2) : 0,
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
