<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-700">Verified Records</p>
                <h2 class="text-2xl font-semibold text-gray-900">Invoices</h2>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('invoice-upload.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">Upload</a>
                <a href="{{ route('invoices.create') }}" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800">New Invoice</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif

            <form method="GET" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="grid gap-4 md:grid-cols-6">
                    <div class="md:col-span-2">
                        <x-input-label for="search" value="Search" />
                        <x-text-input id="search" name="search" class="mt-1 block w-full" value="{{ $filters['search'] ?? '' }}" placeholder="Invoice no or customer" />
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
                            <option value="">All</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" @selected(($filters['customer_id'] ?? '') == $customer->id)>{{ $customer->customer_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="payment_status" value="Status" />
                        <select id="payment_status" name="payment_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="">All</option>
                            @foreach ($paymentStatuses as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['payment_status'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <a href="{{ route('invoices.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reset</a>
                    <x-primary-button>Filter</x-primary-button>
                </div>
            </form>

            <div class="mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                            <tr>
                                <th class="px-6 py-3">Invoice</th>
                                <th class="px-6 py-3">Customer</th>
                                <th class="px-6 py-3">Date</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3 text-right">Original</th>
                                <th class="px-6 py-3 text-right">Report MYR</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($invoices as $invoice)
                                <tr>
                                    <td class="px-6 py-4 font-semibold text-gray-900">{{ $invoice->invoice_number }}</td>
                                    <td class="px-6 py-4 text-gray-700">{{ $invoice->customer->customer_name }}</td>
                                    <td class="px-6 py-4 text-gray-600">{{ $invoice->invoice_date->format('d M Y') }}</td>
                                    <td class="px-6 py-4">
                                        <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">{{ $invoice->paymentStatusLabel() }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-gray-700">{{ $invoice->currency_code }} {{ number_format($invoice->total_amount, 2) }}</td>
                                    <td class="px-6 py-4 text-right font-semibold text-gray-900">RM{{ number_format($invoice->total_amount_myr, 2) }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="{{ route('invoices.show', $invoice) }}" class="font-semibold text-emerald-700 hover:text-emerald-900">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-10 text-center text-gray-500">No invoices found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-gray-200 px-6 py-4">
                    {{ $invoices->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
