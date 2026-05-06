<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-emerald-700">{{ $document ? 'OCR Verification' : 'Manual Entry' }}</p>
            <h2 class="text-2xl font-semibold text-gray-900">Create Invoice</h2>
        </div>
    </x-slot>

    @include('invoices.partials.form', [
        'action' => route('invoices.store'),
        'method' => 'POST',
    ])
</x-app-layout>
