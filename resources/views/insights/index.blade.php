<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-emerald-700">{{ $ai['source'] ?? 'Rule-based fallback' }}</p>
            <h2 class="text-2xl font-semibold text-gray-900">Explainable Sales Insights</h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="rounded-lg bg-emerald-50 p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold uppercase text-emerald-700">Generated Insight</p>
                            <p class="mt-3 text-lg leading-8 text-emerald-950">{{ $summary }}</p>
                        </div>
                        <span class="rounded-md bg-white px-3 py-1 text-xs font-semibold text-emerald-700">
                            {{ ($ai['enabled'] ?? false) ? 'AI rewritten' : 'No AI key / fallback' }}
                        </span>
                    </div>
                </div>

                <div class="mt-8 grid gap-6 lg:grid-cols-3">
                    <div class="lg:col-span-2">
                        <h3 class="text-base font-semibold text-gray-900">Supporting Evidence</h3>
                        <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                            @foreach ($evidence as $label => $value)
                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                    <dt class="text-sm text-gray-500">{{ $label }}</dt>
                                    <dd class="mt-1 text-base font-semibold text-gray-900">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>

                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Recommendations</h3>
                        <div class="mt-4 space-y-3">
                            @forelse ($recommendations as $recommendation)
                                <div class="rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ $recommendation }}</div>
                            @empty
                                <p class="text-sm text-gray-500">No recommendations yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="mt-8 grid gap-6 lg:grid-cols-2">
                    <section class="rounded-lg border border-gray-200 bg-white p-5">
                        <h3 class="text-base font-semibold text-gray-900">Key Observations</h3>
                        <div class="mt-4 space-y-3">
                            @forelse ($observations as $observation)
                                <p class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-700">{{ $observation }}</p>
                            @empty
                                <p class="text-sm text-gray-500">No observations available.</p>
                            @endforelse
                        </div>
                    </section>

                    <section class="rounded-lg border border-gray-200 bg-white p-5">
                        <h3 class="text-base font-semibold text-gray-900">Risk Warnings</h3>
                        <div class="mt-4 space-y-3">
                            @forelse ($risk_warnings as $warning)
                                <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $warning }}</p>
                            @empty
                                <p class="text-sm text-gray-500">No major sales or payment risk detected from verified data.</p>
                            @endforelse
                        </div>
                    </section>
                </div>
            </section>

            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">Customer Payment Behaviour</h3>
                    <div class="mt-5 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                                <tr>
                                    <th class="px-4 py-3">Customer</th>
                                    <th class="px-4 py-3 text-right">Sales</th>
                                    <th class="px-4 py-3 text-right">Outstanding</th>
                                    <th class="px-4 py-3 text-right">Overdue</th>
                                    <th class="px-4 py-3">Risk</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse ($customer_payment_analysis as $customer)
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900">{{ $customer['customer'] }}</td>
                                        <td class="px-4 py-3 text-right">RM{{ number_format($customer['total_sales_myr'], 2) }}</td>
                                        <td class="px-4 py-3 text-right">RM{{ number_format($customer['outstanding_amount_myr'], 2) }}</td>
                                        <td class="px-4 py-3 text-right">{{ $customer['overdue_invoice_count'] }}</td>
                                        <td class="px-4 py-3">{{ $customer['risk_level'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No customer payment data yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">Cash Collection Forecast</h3>
                    <div class="mt-5 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-lg bg-gray-50 p-4">
                            <p class="text-sm text-gray-500">Outstanding</p>
                            <p class="mt-1 text-lg font-semibold">RM{{ number_format($collection_forecast['total_outstanding_myr'] ?? 0, 2) }}</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-4">
                            <p class="text-sm text-gray-500">Due Soon</p>
                            <p class="mt-1 text-lg font-semibold">RM{{ number_format($collection_forecast['due_soon_myr'] ?? 0, 2) }}</p>
                        </div>
                        <div class="rounded-lg bg-amber-50 p-4">
                            <p class="text-sm text-amber-700">Overdue</p>
                            <p class="mt-1 text-lg font-semibold text-amber-900">RM{{ number_format($collection_forecast['overdue_myr'] ?? 0, 2) }}</p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-3">
                        @forelse (($collection_forecast['priority_invoices'] ?? []) as $invoice)
                            <div class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3 text-sm">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $invoice['invoice_number'] }} - {{ $invoice['customer'] }}</p>
                                    <p class="text-xs text-gray-500">Due {{ $invoice['due_date'] }} | {{ $invoice['status'] }}</p>
                                </div>
                                <p class="font-semibold text-gray-900">RM{{ number_format($invoice['amount_myr'], 2) }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">No unpaid invoice follow-up is needed.</p>
                        @endforelse
                    </div>
                </section>
            </div>

            <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">Invoice Item-Level Analytics</h3>
                <div class="mt-5 grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                    @forelse ($item_analytics as $item)
                        <div class="rounded-lg bg-gray-50 p-4">
                            <p class="text-sm font-medium text-gray-900">{{ $item['item_name'] }}</p>
                            <p class="mt-2 text-lg font-semibold text-gray-900">RM{{ number_format($item['revenue'], 2) }}</p>
                            <p class="text-xs text-gray-500">{{ $item['frequency'] }} rows | Qty {{ number_format($item['quantity'], 2) }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No item-level data is available for the analysis month.</p>
                    @endforelse
                </div>
            </section>

            <div class="mt-6 rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-600">
                AI is optional. Financial numbers are calculated by Laravel from verified invoice records first; Gemini is only used to rewrite the already-calculated summary when a valid API key is configured.
            </div>
        </div>
    </div>
</x-app-layout>
