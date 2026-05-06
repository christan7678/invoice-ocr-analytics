<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-700">{{ $company->company_name }}</p>
                <h2 class="text-2xl font-semibold leading-tight text-gray-900">
                    {{ __('Invoice OCR Analytics Dashboard') }}
                </h2>
            </div>
            <a href="{{ route('invoice-upload.index') }}" class="inline-flex items-center justify-center rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2">
                Upload Invoice
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid gap-4 px-4 sm:grid-cols-2 sm:px-0 lg:grid-cols-4">
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-gray-500">Total Sales This Month</p>
                    <p class="mt-3 text-2xl font-semibold text-gray-900">RM{{ number_format($totalSalesThisMonth, 2) }}</p>
                    <p class="mt-2 text-xs text-gray-500">Based on verified invoices</p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-gray-500">Invoices This Month</p>
                    <p class="mt-3 text-2xl font-semibold text-gray-900">{{ $invoiceCountThisMonth }}</p>
                    <p class="mt-2 text-xs text-gray-500">Uploaded and verified records</p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-gray-500">Unpaid Amount</p>
                    <p class="mt-3 text-2xl font-semibold text-gray-900">RM{{ number_format($unpaidAmount, 2) }}</p>
                    <p class="mt-2 text-xs text-gray-500">Pending customer payments</p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-gray-500">Paid Sales</p>
                    <p class="mt-3 text-2xl font-semibold text-gray-900">RM{{ number_format($paidAmount, 2) }}</p>
                    <p class="mt-2 text-xs text-gray-500">Paid invoices in database</p>
                </div>
            </div>

            <div class="mt-6 grid gap-6 px-4 sm:px-0 lg:grid-cols-3">
                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm lg:col-span-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Sales Overview</h3>
                            <p class="mt-1 text-sm text-gray-500">Monthly sales trend in MYR after currency conversion.</p>
                        </div>
                        <span class="rounded-md bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">Chart.js</span>
                    </div>

                    <div class="mt-6 flex h-64 items-end gap-3 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4">
                        @php
                            $maxTrend = max($trend->max('total') ?: 1, 1);
                        @endphp
                        @foreach ($trend as $point)
                            <div class="flex flex-1 flex-col items-center justify-end gap-2">
                                <div class="w-full rounded-t bg-emerald-600/70" style="height: {{ max(($point['total'] / $maxTrend) * 100, $point['total'] > 0 ? 8 : 2) }}%;"></div>
                                <span class="text-[10px] text-gray-500">{{ $point['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">Processing Pipeline</h3>
                    <div class="mt-5 space-y-4">
                        @foreach ([
                            'Upload sales invoice',
                            'Extract OCR text',
                            'Identify invoice fields',
                            'User verifies values',
                            'Save verified record',
                            'Generate dashboard and reports',
                        ] as $step)
                            <div class="flex items-start gap-3">
                                <div class="mt-1 h-2.5 w-2.5 rounded-full bg-emerald-600"></div>
                                <p class="text-sm text-gray-700">{{ $step }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>

            <div class="mt-6 grid gap-4 px-4 sm:grid-cols-2 sm:px-0 lg:grid-cols-5">
                @foreach ([
                    ['label' => 'Upload Invoice', 'route' => route('invoice-upload.index'), 'meta' => 'PDF, JPG, PNG'],
                    ['label' => 'Invoice Records', 'route' => route('invoices.index'), 'meta' => 'Search and filter'],
                    ['label' => 'Reports', 'route' => route('reports.index'), 'meta' => 'PDF and CSV export'],
                    ['label' => 'Insights', 'route' => route('insights.index'), 'meta' => 'Evidence-backed summary'],
                    ['label' => 'OCR Evaluation', 'route' => route('ocr-evaluation.index'), 'meta' => 'Field accuracy testing'],
                ] as $module)
                    <a href="{{ $module['route'] }}" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:border-emerald-300 hover:shadow">
                        <p class="text-sm font-semibold text-gray-900">{{ $module['label'] }}</p>
                        <p class="mt-2 text-xs text-gray-500">{{ $module['meta'] }}</p>
                    </a>
                @endforeach
            </div>

            <div class="mt-6 grid gap-6 px-4 sm:px-0 lg:grid-cols-2">
                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">Top Customers</h3>
                    <div class="mt-4 space-y-3">
                        @forelse ($topCustomers as $customer)
                            <div class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3">
                                <span class="text-sm font-medium text-gray-700">{{ $customer['name'] }}</span>
                                <span class="text-sm font-semibold text-gray-900">RM{{ number_format($customer['total'], 2) }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">No customer sales yet.</p>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">Month Comparison</h3>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-lg bg-gray-50 p-4">
                            <p class="text-sm text-gray-500">Highest Sales Month</p>
                            <p class="mt-2 text-lg font-semibold text-gray-900">{{ $highestMonth['month'] ?? '-' }}</p>
                            <p class="text-sm text-gray-600">RM{{ number_format($highestMonth['total'] ?? 0, 2) }}</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-4">
                            <p class="text-sm text-gray-500">Lowest Sales Month</p>
                            <p class="mt-2 text-lg font-semibold text-gray-900">{{ $lowestMonth['month'] ?? '-' }}</p>
                            <p class="text-sm text-gray-600">RM{{ number_format($lowestMonth['total'] ?? 0, 2) }}</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
