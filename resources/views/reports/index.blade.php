<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-700">Sales Reporting</p>
                <h2 class="text-2xl font-semibold text-gray-900">Reports</h2>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('reports.csv', request()->query()) }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">Export CSV</a>
                <button onclick="window.print()" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800">Print / Save PDF</button>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <form method="GET" class="mb-6 rounded-lg border border-gray-200 bg-white p-5 shadow-sm print:hidden">
                <div class="grid gap-4 lg:grid-cols-6">
                    <div>
                        <x-input-label for="report_type" value="Report Type" />
                        <select id="report_type" name="report_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            @foreach ($reportTypes as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['report_type'] ?? 'full') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="date_from" value="From" />
                        <x-text-input id="date_from" name="date_from" type="date" class="mt-1 block w-full" value="{{ $filters['date_from'] ?? '' }}" />
                    </div>
                    <div>
                        <x-input-label for="date_to" value="To" />
                        <x-text-input id="date_to" name="date_to" type="date" class="mt-1 block w-full" value="{{ $filters['date_to'] ?? '' }}" />
                    </div>
                    <div>
                        <x-input-label for="customer_id" value="Customer" />
                        <select id="customer_id" name="customer_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="">All customers</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" @selected(($filters['customer_id'] ?? '') == $customer->id)>{{ $customer->customer_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="payment_status" value="Payment Status" />
                        <select id="payment_status" name="payment_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="">All statuses</option>
                            @foreach ($paymentStatuses as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['payment_status'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end justify-end gap-2">
                        <a href="{{ route('reports.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reset</a>
                        <x-primary-button>Generate</x-primary-button>
                    </div>
                </div>
            </form>

            <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-5 shadow-sm print:hidden">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase text-emerald-700">Report generated</p>
                        <h3 class="mt-1 text-xl font-semibold text-emerald-950">{{ $summary['report_type_label'] }}</h3>
                        <p class="mt-1 text-sm text-emerald-800">{{ $summary['date_range'] }} | {{ $summary['invoice_count'] }} invoice records | RM{{ number_format($summary['total_myr'], 2) }} total MYR</p>
                    </div>
                    <div class="rounded-lg bg-white/80 px-4 py-3 text-sm text-emerald-900">
                        @if ($summary['has_filters'])
                            <p class="font-semibold">Active filters</p>
                            <p class="mt-1">{{ implode(' | ', $summary['active_filters']) }}</p>
                        @else
                            <p class="font-semibold">No filters applied</p>
                            <p class="mt-1">Showing all verified invoice records for this company.</p>
                        @endif
                    </div>
                </div>
            </div>

            <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900">{{ $company->company_name }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ $company->address }}</p>
                            <p class="mt-1 text-sm text-gray-500">{{ $company->email }} {{ $company->phone ? ' | '.$company->phone : '' }}</p>
                        </div>
                        @if ($company->logo_path)
                            <img src="{{ Storage::url($company->logo_path) }}" alt="Company logo" class="h-16 rounded border border-gray-200 bg-white object-contain p-2">
                        @endif
                    </div>
                    <div class="mt-6 flex flex-col gap-1 border-t border-gray-100 pt-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-sm font-medium text-emerald-700">Sales Report</p>
                            <h4 class="text-2xl font-semibold text-gray-900">{{ $summary['date_range'] }}</h4>
                            <p class="mt-1 text-sm text-gray-500">{{ $summary['report_type_label'] }}</p>
                        </div>
                        <p class="text-sm text-gray-500">Generated {{ $summary['generated_at']->format('d M Y, h:i A') }}</p>
                    </div>
                </div>

                <div class="grid gap-4 p-6 sm:grid-cols-4">
                    <div class="rounded-lg bg-gray-50 p-4">
                        <p class="text-sm text-gray-500">Invoices</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $summary['invoice_count'] }}</p>
                    </div>
                    <div class="rounded-lg bg-emerald-50 p-4">
                        <p class="text-sm text-emerald-700">Total Sales</p>
                        <p class="mt-1 text-2xl font-semibold text-emerald-900">RM{{ number_format($summary['total_myr'], 2) }}</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-4">
                        <p class="text-sm text-gray-500">Paid</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">RM{{ number_format($summary['paid_myr'], 2) }}</p>
                    </div>
                    <div class="rounded-lg bg-amber-50 p-4">
                        <p class="text-sm text-amber-700">Outstanding</p>
                        <p class="mt-1 text-2xl font-semibold text-amber-900">RM{{ number_format($summary['outstanding_myr'], 2) }}</p>
                    </div>
                </div>

                <div class="grid gap-6 border-t border-gray-200 p-6 lg:grid-cols-2">
                    <section @class([
                        'rounded-lg border bg-white p-5',
                        'border-emerald-300 ring-2 ring-emerald-100' => $summary['report_type'] === 'monthly',
                        'border-gray-200' => $summary['report_type'] !== 'monthly',
                    ])>
                        <h4 class="text-base font-semibold text-gray-900">Monthly Breakdown</h4>
                        <div class="mt-4 space-y-3">
                            @forelse ($monthlyBreakdown as $row)
                                <div>
                                    <div class="flex justify-between text-sm">
                                        <span class="font-medium text-gray-700">{{ $row['label'] }}</span>
                                        <span class="font-semibold text-gray-900">RM{{ number_format($row['total_myr'], 2) }}</span>
                                    </div>
                                    <div class="mt-2 h-2 rounded-full bg-gray-100">
                                        <div class="h-2 rounded-full bg-emerald-600" style="width: {{ $summary['total_myr'] > 0 ? min(($row['total_myr'] / $summary['total_myr']) * 100, 100) : 0 }}%"></div>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">{{ $row['count'] }} invoices</p>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No monthly data available.</p>
                            @endforelse
                        </div>
                    </section>

                    <section @class([
                        'rounded-lg border bg-white p-5',
                        'border-emerald-300 ring-2 ring-emerald-100' => $summary['report_type'] === 'customer',
                        'border-gray-200' => $summary['report_type'] !== 'customer',
                    ])>
                        <h4 class="text-base font-semibold text-gray-900">Customer Sales</h4>
                        <div class="mt-4 space-y-3">
                            @forelse ($customerBreakdown as $row)
                                <div class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ $row['label'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $row['count'] }} invoices</p>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-900">RM{{ number_format($row['total_myr'], 2) }}</p>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No customer sales available.</p>
                            @endforelse
                        </div>
                    </section>

                    <section @class([
                        'rounded-lg border bg-white p-5',
                        'border-emerald-300 ring-2 ring-emerald-100' => $summary['report_type'] === 'payment',
                        'border-gray-200' => $summary['report_type'] !== 'payment',
                    ])>
                        <h4 class="text-base font-semibold text-gray-900">Payment Status</h4>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            @forelse ($statusBreakdown as $row)
                                <div class="rounded-lg bg-gray-50 p-4">
                                    <p class="text-sm font-medium text-gray-900">{{ $row['label'] }}</p>
                                    <p class="mt-2 text-lg font-semibold text-gray-900">RM{{ number_format($row['total_myr'], 2) }}</p>
                                    <p class="text-xs text-gray-500">{{ $row['count'] }} invoices</p>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No payment data available.</p>
                            @endforelse
                        </div>
                    </section>

                    <section @class([
                        'rounded-lg border bg-white p-5',
                        'border-emerald-300 ring-2 ring-emerald-100' => $summary['report_type'] === 'currency',
                        'border-gray-200' => $summary['report_type'] !== 'currency',
                    ])>
                        <h4 class="text-base font-semibold text-gray-900">Currency Conversion Summary</h4>
                        <div class="mt-4 space-y-3">
                            @forelse ($currencyBreakdown as $row)
                                <div class="rounded-lg bg-gray-50 px-4 py-3">
                                    <div class="flex justify-between">
                                        <p class="text-sm font-medium text-gray-900">{{ $row['label'] }}</p>
                                        <p class="text-sm font-semibold text-gray-900">RM{{ number_format($row['total_myr'], 2) }}</p>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">
                                        Original total: {{ $row['label'] }} {{ number_format($row['original_total'], 2) }} across {{ $row['count'] }} invoices
                                    </p>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No currency data available.</p>
                            @endforelse
                        </div>
                    </section>
                </div>

                <div class="overflow-x-auto border-t border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                            <tr>
                                <th class="px-6 py-3">Invoice</th>
                                <th class="px-6 py-3">Date</th>
                                <th class="px-6 py-3">Customer</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3 text-right">Original Total</th>
                                <th class="px-6 py-3 text-right">MYR Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($invoices as $invoice)
                                <tr>
                                    <td class="px-6 py-4 font-semibold text-gray-900">{{ $invoice->invoice_number }}</td>
                                    <td class="px-6 py-4 text-gray-600">{{ $invoice->invoice_date->format('d M Y') }}</td>
                                    <td class="px-6 py-4 text-gray-700">{{ $invoice->customer->customer_name }}</td>
                                    <td class="px-6 py-4 text-gray-700">{{ $invoice->paymentStatusLabel() }}</td>
                                    <td class="px-6 py-4 text-right">{{ $invoice->currency_code }} {{ number_format($invoice->total_amount, 2) }}</td>
                                    <td class="px-6 py-4 text-right font-semibold">RM{{ number_format($invoice->total_amount_myr, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-gray-500">No records found for this report.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
