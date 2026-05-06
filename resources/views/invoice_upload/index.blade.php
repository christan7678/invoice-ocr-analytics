<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-700">OCR-Assisted Upload</p>
                <h2 class="text-2xl font-semibold text-gray-900">Upload Sales Invoice</h2>
            </div>
            <a href="{{ route('invoices.create') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">Create Manual Invoice</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ session('status') }}</div>
            @endif

            <div class="grid gap-6 lg:grid-cols-3">
                <form method="POST" action="{{ route('invoice-upload.store') }}" enctype="multipart/form-data" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm lg:col-span-2">
                    @csrf
                    <h3 class="text-base font-semibold text-gray-900">Upload PDF, JPG, or PNG</h3>
                    <p class="mt-1 text-sm text-gray-500">The system will run OCR, then send you to a verification form before saving the invoice.</p>

                    <div class="mt-5">
                        <input name="invoice_file" type="file" accept=".pdf,.jpg,.jpeg,.png" required class="block w-full rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-emerald-700 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-emerald-800">
                        <x-input-error :messages="$errors->get('invoice_file')" class="mt-2" />
                    </div>

                    <div class="mt-6 flex justify-end">
                        <x-primary-button>Extract OCR Text</x-primary-button>
                    </div>
                </form>

                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">Safe Workflow</h3>
                    <div class="mt-4 space-y-3 text-sm text-gray-600">
                        <p>1. Upload the sales invoice.</p>
                        <p>2. OCR extracts raw text.</p>
                        <p>3. The system guesses fields.</p>
                        <p>4. You verify and correct values.</p>
                        <p>5. Only verified data is saved.</p>
                    </div>
                </div>
            </div>

            <div class="mt-6 rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-gray-900">Recent Uploads</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                            <tr>
                                <th class="px-6 py-3">File</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3">Uploaded</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($documents as $document)
                                <tr>
                                    <td class="px-6 py-4 font-medium text-gray-900">{{ $document->file_name }}</td>
                                    <td class="px-6 py-4">{{ ucfirst($document->processing_status) }}</td>
                                    <td class="px-6 py-4 text-gray-600">{{ optional($document->uploaded_at)->format('d M Y, h:i A') }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="{{ route('documents.show', $document) }}" target="_blank" class="text-emerald-700 hover:text-emerald-900">View</a>
                                        @if ($document->processing_status === 'processed')
                                            <a href="{{ route('invoices.create', ['document' => $document->id]) }}" class="ml-4 text-emerald-700 hover:text-emerald-900">Verify</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-gray-500">No invoice uploads yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
