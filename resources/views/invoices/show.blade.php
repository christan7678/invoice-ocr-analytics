<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-700">{{ $invoice->verification_status }}</p>
                <h2 class="text-2xl font-semibold text-gray-900">Invoice {{ $invoice->invoice_number }}</h2>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('invoices.edit', $invoice) }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">Edit</a>
                <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" onsubmit="return confirm('Delete this invoice?')">
                    @csrf
                    @method('DELETE')
                    <button class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700">Delete</button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif

            <div class="grid gap-6 lg:grid-cols-3">
                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm lg:col-span-2">
                    <h3 class="text-base font-semibold text-gray-900">Invoice Items</h3>
                    <div class="mt-5 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                                <tr>
                                    <th class="px-4 py-3">Item</th>
                                    <th class="px-4 py-3">Description</th>
                                    <th class="px-4 py-3 text-right">Qty</th>
                                    <th class="px-4 py-3 text-right">Unit</th>
                                    <th class="px-4 py-3 text-right">Line</th>
                                    <th class="px-4 py-3 text-right">Tax %</th>
                                    <th class="px-4 py-3 text-right">Tax</th>
                                    <th class="px-4 py-3 text-right">Discount</th>
                                    <th class="px-4 py-3 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach ($invoice->items as $item)
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900">{{ $item->item_name }}</td>
                                        <td class="px-4 py-3 text-gray-600">{{ $item->description }}</td>
                                        <td class="px-4 py-3 text-right">{{ number_format($item->quantity, 2) }}</td>
                                        <td class="px-4 py-3 text-right">{{ number_format($item->unit_price, 2) }}</td>
                                        <td class="px-4 py-3 text-right">{{ number_format($item->line_total ?? ($item->quantity * $item->unit_price), 2) }}</td>
                                        <td class="px-4 py-3 text-right">{{ $item->tax_rate === null ? '-' : number_format($item->tax_rate, 2).'%' }}</td>
                                        <td class="px-4 py-3 text-right">{{ number_format($item->tax_amount, 2) }}</td>
                                        <td class="px-4 py-3 text-right">{{ number_format($item->discount_amount, 2) }}</td>
                                        <td class="px-4 py-3 text-right font-semibold">{{ number_format($item->total_price, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <aside class="space-y-6">
                    <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                        <h3 class="text-base font-semibold text-gray-900">Summary</h3>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between"><dt class="text-gray-500">Customer</dt><dd class="font-medium text-gray-900">{{ $invoice->customer->customer_name }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Date</dt><dd>{{ $invoice->invoice_date->format('d M Y') }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Status</dt><dd>{{ $invoice->paymentStatusLabel() }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd>{{ $invoice->currency_code }} {{ number_format($invoice->subtotal, 2) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Tax</dt><dd>{{ $invoice->tax_rate ? number_format($invoice->tax_rate, 2).'%' : '' }} {{ $invoice->currency_code }} {{ number_format($invoice->tax_amount, 2) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Discount</dt><dd>{{ $invoice->currency_code }} {{ number_format($invoice->discount_amount, 2) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Service Charge</dt><dd>{{ $invoice->currency_code }} {{ number_format($invoice->service_charge ?? 0, 2) }}</dd></div>
                            <div class="border-t border-gray-200 pt-3 flex justify-between text-base"><dt class="font-semibold text-gray-900">Original Total</dt><dd class="font-semibold">{{ $invoice->currency_code }} {{ number_format($invoice->total_amount, 2) }}</dd></div>
                            <div class="flex justify-between text-base"><dt class="font-semibold text-gray-900">Report Total</dt><dd class="font-semibold">RM{{ number_format($invoice->total_amount_myr, 2) }}</dd></div>
                        </dl>
                    </section>

                    @if ($riskAlerts)
                        <section class="rounded-lg border border-amber-200 bg-amber-50 p-6 shadow-sm">
                            <h3 class="text-base font-semibold text-amber-950">Risk Alerts</h3>
                            <div class="mt-4 space-y-3">
                                @foreach ($riskAlerts as $alert)
                                    <div class="rounded-md bg-white px-4 py-3 text-sm {{ ($alert['severity'] ?? '') === 'High' ? 'text-red-700' : 'text-amber-900' }}">
                                        <span class="font-semibold">{{ $alert['severity'] ?? 'Warning' }}:</span> {{ $alert['message'] ?? $alert }}
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    @if ($invoice->uploadedDocument)
                        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                            <h3 class="text-base font-semibold text-gray-900">Original Document</h3>
                            <a href="{{ route('documents.show', $invoice->uploadedDocument) }}" target="_blank" class="mt-4 inline-flex rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">View Uploaded File</a>
                        </section>
                    @endif
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
