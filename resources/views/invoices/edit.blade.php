<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-emerald-700">Invoice Management</p>
            <h2 class="text-2xl font-semibold text-gray-900">Edit Invoice {{ $invoice->invoice_number }}</h2>
        </div>
    </x-slot>

    @include('invoices.partials.form', [
        'action' => route('invoices.update', $invoice),
        'method' => 'PUT',
    ])
</x-app-layout>
