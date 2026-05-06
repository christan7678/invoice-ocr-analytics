<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-emerald-700">Testing And Evaluation</p>
            <h2 class="text-2xl font-semibold text-gray-900">OCR Evaluation</h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">Recent OCR Results</h3>
                <p class="mt-1 text-sm text-gray-500">This page shows OCR results and whether the user has verified the extracted data before saving.</p>

                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                            <tr>
                                <th class="px-6 py-3">Document</th>
                                <th class="px-6 py-3">Invoice No</th>
                                <th class="px-6 py-3">Customer</th>
                                <th class="px-6 py-3">Total</th>
                                <th class="px-6 py-3">Confidence</th>
                                <th class="px-6 py-3">Verified</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($documents as $document)
                                <tr>
                                    <td class="px-6 py-4 font-medium text-gray-900">{{ $document->file_name }}</td>
                                    <td class="px-6 py-4">{{ $document->ocrExtractionResult?->extracted_invoice_number ?? '-' }}</td>
                                    <td class="px-6 py-4">{{ $document->ocrExtractionResult?->extracted_customer_name ?? '-' }}</td>
                                    <td class="px-6 py-4">{{ $document->ocrExtractionResult?->extracted_total_amount ? number_format($document->ocrExtractionResult->extracted_total_amount, 2) : '-' }}</td>
                                    <td class="px-6 py-4">{{ $document->ocrExtractionResult?->confidence_score ? number_format($document->ocrExtractionResult->confidence_score, 2).'%' : '-' }}</td>
                                    <td class="px-6 py-4">{{ $document->ocrExtractionResult?->is_verified ? 'Yes' : 'No' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-gray-500">No OCR documents uploaded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
