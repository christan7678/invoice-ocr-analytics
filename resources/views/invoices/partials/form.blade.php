@php
    $sourceItems = old('items');

    if (! $sourceItems) {
        $sourceItems = $invoice?->items?->map(fn ($item) => [
            'item_name' => $item->item_name,
            'description' => $item->description,
            'quantity' => (float) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'tax_rate' => $item->tax_rate === null ? null : (float) $item->tax_rate,
            'tax_amount' => (float) $item->tax_amount,
            'discount_amount' => (float) $item->discount_amount,
            'line_total' => (float) ($item->line_total ?? $item->total_price),
        ])->values()->all();
    }

    if (! $sourceItems && $extraction?->extracted_items) {
        $sourceItems = collect($extraction->extracted_items)->map(fn ($item) => [
            'item_name' => $item['item_name'] ?? 'Sales invoice item',
            'description' => $item['description'] ?? '',
            'quantity' => (float) ($item['quantity'] ?? 1),
            'unit_price' => (float) ($item['unit_price'] ?? 0),
            'tax_rate' => isset($item['tax_rate']) ? (float) $item['tax_rate'] : null,
            'tax_amount' => (float) ($item['tax_amount'] ?? 0),
            'discount_amount' => (float) ($item['discount_amount'] ?? 0),
            'line_total' => (float) ($item['line_total'] ?? (($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0))),
        ])->values()->all();
    }

    if (! $sourceItems) {
        $sourceItems = [[
            'item_name' => 'Sales invoice item',
            'description' => '',
            'quantity' => 1,
            'unit_price' => (float) ($extraction?->extracted_total_amount ?? 0),
            'tax_rate' => null,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'line_total' => (float) ($extraction?->extracted_total_amount ?? 0),
        ]];
    }

    $selectedCurrency = old('currency_code', $invoice?->currency_code ?? $extraction?->extracted_currency_code ?? $company->default_currency_code ?? 'MYR');
    $initialSubtotal = old('subtotal', $invoice?->subtotal ?? $extraction?->extracted_subtotal ?? collect($sourceItems)->sum('line_total'));
    $initialTaxRate = old('tax_rate', $invoice?->tax_rate ?? $extraction?->extracted_tax_rate ?? '');
    $initialTaxAmount = old('tax_amount', $invoice?->tax_amount ?? $extraction?->extracted_tax_amount ?? 0);
    $initialDiscount = old('discount_amount', $invoice?->discount_amount ?? $extraction?->extracted_discount_amount ?? 0);
    $initialServiceCharge = old('service_charge', $invoice?->service_charge ?? $extraction?->extracted_service_charge ?? 0);
    $initialTotal = old('total_amount', $invoice?->total_amount ?? $extraction?->extracted_total_amount ?? 0);
    $fieldConfidences = $extraction?->field_confidences ?? ($invoice?->extraction_confidence_summary['field_confidences'] ?? []);
    $extractionWarnings = $extraction?->extraction_warnings ?? ($invoice?->extraction_confidence_summary['extraction_warnings'] ?? []);
    $storedCalculationWarnings = $invoice?->extraction_confidence_summary['calculation_warnings'] ?? [];
    $riskAlerts = $riskAlerts ?? ($invoice?->extraction_confidence_summary['risk_alerts'] ?? []);
@endphp

<div class="py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ $action }}" x-data="invoiceForm({{ Js::from($sourceItems) }}, {{ Js::from([
            'subtotal' => (float) $initialSubtotal,
            'tax_rate' => $initialTaxRate === '' ? null : (float) $initialTaxRate,
            'tax_amount' => (float) $initialTaxAmount,
            'discount_amount' => (float) $initialDiscount,
            'service_charge' => (float) $initialServiceCharge,
            'total_amount' => (float) $initialTotal,
            'rate' => (float) old('exchange_rate_to_myr', $invoice?->exchange_rate_to_myr ?? 1),
            'currency' => $selectedCurrency,
        ]) }}, '{{ route('exchange-rate.show') }}')" class="space-y-6">
            @csrf
            @if ($method !== 'POST')
                @method($method)
            @endif

            @if ($document)
                <input type="hidden" name="uploaded_document_id" value="{{ $document->id }}">
            @endif

            @if ($document)
                <section class="rounded-lg border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="text-sm font-semibold uppercase text-emerald-700">Pending User Verification</p>
                            <h3 class="mt-1 text-lg font-semibold text-emerald-950">Please review the OCR result before saving.</h3>
                            <p class="mt-2 text-sm leading-6 text-emerald-900">Different companies use different invoice formats, so some fields may be missing or incorrectly detected.</p>
                            <p class="mt-2 text-sm leading-6 text-emerald-900">OCR helps reduce manual typing, but invoice layouts are different across companies. Please confirm the extracted data before saving so that reports and insights remain accurate.</p>
                        </div>
                        <div class="rounded-md bg-white/80 px-4 py-3 text-sm text-emerald-900">
                            <p class="font-semibold">Status</p>
                            <p class="mt-1">{{ Illuminate\Support\Str::headline($document->processing_status) }}</p>
                            <p class="mt-2 text-xs text-emerald-700">This OCR result is not used in dashboard, reports, or insights until you save it as a verified invoice.</p>
                        </div>
                    </div>
                </section>
            @endif

            @if ($fieldConfidences || $extractionWarnings || $storedCalculationWarnings || $riskAlerts)
                <section class="rounded-lg border border-amber-200 bg-amber-50 p-5 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-amber-950">Verification Warnings And Confidence</h3>
                            <p class="mt-1 text-sm text-amber-800">These alerts are advisory. Correct the fields below before saving the verified invoice.</p>
                        </div>
                        @if ($fieldConfidences)
                            <div class="grid gap-2 sm:grid-cols-2 lg:min-w-96">
                                @foreach ($fieldConfidences as $field => $confidence)
                                    <div class="rounded-md bg-white/80 px-3 py-2 text-xs">
                                        <span class="font-semibold text-gray-700">{{ Illuminate\Support\Str::headline($field) }}:</span>
                                        <span class="text-gray-900">{{ $confidence }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if ($extractionWarnings || $storedCalculationWarnings || $riskAlerts)
                        <div class="mt-4 grid gap-3 lg:grid-cols-2">
                            @foreach ($extractionWarnings as $warning)
                                <div class="rounded-md border border-amber-200 bg-white px-4 py-3 text-sm text-amber-900">{{ $warning }}</div>
                            @endforeach
                            @foreach ($storedCalculationWarnings as $warning)
                                <div class="rounded-md border border-amber-200 bg-white px-4 py-3 text-sm text-amber-900">{{ $warning }}</div>
                            @endforeach
                            @foreach ($riskAlerts as $alert)
                                <div class="rounded-md border bg-white px-4 py-3 text-sm {{ ($alert['severity'] ?? '') === 'High' ? 'border-red-200 text-red-800' : 'border-amber-200 text-amber-900' }}">
                                    <span class="font-semibold">{{ $alert['severity'] ?? 'Warning' }}:</span> {{ $alert['message'] ?? $alert }}
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif

            <div class="grid gap-6 lg:grid-cols-3">
                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm lg:col-span-2">
                    <div class="flex items-center justify-between border-b border-gray-200 pb-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Invoice Details</h3>
                            <p class="mt-1 text-sm text-gray-500">Review OCR values or enter invoice data manually.</p>
                        </div>
                        <span class="rounded-md bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                            {{ $document ? 'Verify before save' : 'Manual invoice' }}
                        </span>
                    </div>

                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <div>
                            <x-input-label for="invoice_number" value="Invoice Number" />
                            <x-text-input id="invoice_number" name="invoice_number" class="mt-1 block w-full" value="{{ old('invoice_number', $invoice?->invoice_number ?? $extraction?->extracted_invoice_number ?? '') }}" required />
                            <p class="mt-1 text-xs text-gray-500">Confidence: {{ $fieldConfidences['invoice_number'] ?? 'Manual' }}</p>
                            <x-input-error :messages="$errors->get('invoice_number')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="invoice_date" value="Invoice Date" />
                            <x-text-input id="invoice_date" name="invoice_date" type="date" class="mt-1 block w-full" value="{{ old('invoice_date', optional($invoice?->invoice_date)->format('Y-m-d') ?? optional($extraction?->extracted_invoice_date)->format('Y-m-d')) }}" required />
                            <p class="mt-1 text-xs text-gray-500">Confidence: {{ $fieldConfidences['invoice_date'] ?? 'Manual' }}</p>
                            <x-input-error :messages="$errors->get('invoice_date')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="due_date" value="Due Date" />
                            <x-text-input id="due_date" name="due_date" type="date" class="mt-1 block w-full" value="{{ old('due_date', optional($invoice?->due_date)->format('Y-m-d') ?? optional($extraction?->extracted_due_date)->format('Y-m-d')) }}" />
                        </div>

                        <div>
                            <x-input-label for="payment_status" value="Payment Status" />
                            <select id="payment_status" name="payment_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                @foreach ($paymentStatuses as $value => $label)
                                    <option value="{{ $value }}" @selected(old('payment_status', $invoice?->payment_status ?? $extraction?->extracted_payment_status ?? 'pending') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-input-label for="paid_at" value="Paid Date" />
                            <x-text-input id="paid_at" name="paid_at" type="date" class="mt-1 block w-full" value="{{ old('paid_at', optional($invoice?->paid_at)->format('Y-m-d')) }}" />
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">Currency Conversion</h3>
                    <p class="mt-1 text-sm text-gray-500">Reports are converted to RM using this rate.</p>

                    <div class="mt-5 space-y-4">
                        <div>
                            <x-input-label for="currency_code" value="Invoice Currency" />
                            <input id="currency_code" name="currency_code" list="currency-options" maxlength="3" x-model="currency" class="mt-1 block w-full rounded-md border-gray-300 uppercase shadow-sm focus:border-emerald-500 focus:ring-emerald-500" required>
                            <datalist id="currency-options">
                                @foreach ($currencies as $code => $name)
                                    <option value="{{ $code }}">{{ $name }}</option>
                                @endforeach
                            </datalist>
                        </div>

                        <div>
                            <x-input-label for="exchange_rate_to_myr" value="Exchange Rate To MYR" />
                            <x-text-input id="exchange_rate_to_myr" name="exchange_rate_to_myr" type="number" step="0.000001" min="0.000001" x-model.number="rate" class="mt-1 block w-full" required />
                            <div class="mt-2 flex items-center justify-between gap-3">
                                <p class="text-xs text-gray-500">Use 1.000000 for MYR, or fetch latest rate.</p>
                                <button type="button" @click="fetchRate()" class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">Fetch Rate</button>
                            </div>
                            <p class="mt-2 text-xs" :class="rateStatusType === 'error' ? 'text-red-600' : 'text-gray-500'" x-text="rateStatus"></p>
                        </div>

                        <div class="rounded-lg bg-gray-50 p-4">
                            <p class="text-sm text-gray-500">Estimated Report Total</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-900" x-text="money(Number(amounts.total_amount || 0) * rate, 'RM')"></p>
                        </div>
                    </div>
                </section>
            </div>

            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">Customer Details</h3>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="customer_name" value="Customer Name" />
                        <x-text-input id="customer_name" name="customer_name" list="customer-options" class="mt-1 block w-full" value="{{ old('customer_name', $invoice?->customer?->customer_name ?? $extraction?->extracted_customer_name ?? '') }}" required />
                        <p class="mt-1 text-xs text-gray-500">Confidence: {{ $fieldConfidences['customer_name'] ?? 'Manual' }}</p>
                        <datalist id="customer-options">
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->customer_name }}">{{ $customer->customer_email }}</option>
                            @endforeach
                        </datalist>
                    </div>

                    <div>
                        <x-input-label for="customer_email" value="Customer Email" />
                        <x-text-input id="customer_email" name="customer_email" type="email" class="mt-1 block w-full" value="{{ old('customer_email', $invoice?->customer?->customer_email ?? $extraction?->extracted_customer_email ?? '') }}" />
                    </div>

                    <div>
                        <x-input-label for="customer_phone" value="Customer Phone" />
                        <x-text-input id="customer_phone" name="customer_phone" class="mt-1 block w-full" value="{{ old('customer_phone', $invoice?->customer?->customer_phone ?? $extraction?->extracted_customer_phone ?? '') }}" />
                    </div>

                    <div>
                        <x-input-label for="customer_address" value="Customer Address" />
                        <textarea id="customer_address" name="customer_address" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('customer_address', $invoice?->customer?->address ?? $extraction?->extracted_customer_address ?? '') }}</textarea>
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Invoice Amounts</h3>
                        <p class="mt-1 text-sm text-gray-500">Review OCR totals. You can recalculate from item rows or manually override before saving.</p>
                    </div>
                    <button type="button" @click="recalculateTotals()" class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100">Recalculate Totals</button>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-6">
                    <div>
                        <x-input-label for="subtotal" value="Subtotal" />
                        <input id="subtotal" name="subtotal" type="number" step="0.01" min="0" x-model.number="amounts.subtotal" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    <div>
                        <x-input-label for="discount_amount" value="Discount" />
                        <input id="discount_amount" name="discount_amount" type="number" step="0.01" min="0" x-model.number="amounts.discount_amount" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    <div>
                        <x-input-label for="service_charge" value="Service Charge" />
                        <input id="service_charge" name="service_charge" type="number" step="0.01" min="0" x-model.number="amounts.service_charge" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    <div>
                        <x-input-label for="tax_rate" value="Tax Rate %" />
                        <input id="tax_rate" name="tax_rate" type="number" step="0.01" min="0" max="100" x-model.number="amounts.tax_rate" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    <div>
                        <x-input-label for="tax_amount" value="Tax Amount" />
                        <input id="tax_amount" name="tax_amount" type="number" step="0.01" min="0" x-model.number="amounts.tax_amount" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <p class="mt-1 text-xs text-gray-500">Confidence: {{ $fieldConfidences['tax_amount'] ?? 'Manual' }}</p>
                    </div>
                    <div>
                        <x-input-label for="total_amount" value="Grand Total" />
                        <input id="total_amount" name="total_amount" type="number" step="0.01" min="0" x-model.number="amounts.total_amount" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <p class="mt-1 text-xs text-gray-500">Confidence: {{ $fieldConfidences['grand_total'] ?? 'Manual' }}</p>
                        <x-input-error :messages="$errors->get('total_amount')" class="mt-2" />
                    </div>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-lg bg-gray-50 p-4">
                        <p class="text-sm text-gray-500">Item Row Sum</p>
                        <p class="mt-1 text-xl font-semibold" x-text="money(itemSubtotal(), '')"></p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-4">
                        <p class="text-sm text-gray-500">Expected Total</p>
                        <p class="mt-1 text-xl font-semibold" x-text="money(expectedTotal(), '')"></p>
                    </div>
                    <div class="rounded-lg bg-emerald-50 p-4">
                        <p class="text-sm text-emerald-700">Converted MYR</p>
                        <p class="mt-1 text-xl font-semibold text-emerald-900" x-text="money(Number(amounts.total_amount || 0) * rate, 'RM')"></p>
                    </div>
                </div>

                <template x-if="Math.abs(expectedTotal() - Number(amounts.total_amount || 0)) > 0.05">
                    <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        Grand total does not match subtotal - discount + service charge + tax. Verify before saving.
                    </div>
                </template>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Invoice Items</h3>
                        <p class="mt-1 text-sm text-gray-500">If OCR cannot read detailed line items, use one summary item that matches the invoice total.</p>
                        <p class="mt-1 text-xs text-gray-500">Item row confidence: {{ $fieldConfidences['item_rows'] ?? 'Manual' }}</p>
                    </div>
                    <div class="flex gap-2">
                        @if ($document)
                            <button type="button" @click="resetToOcrItems()" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reset OCR Items</button>
                        @endif
                        <button type="button" @click="addItem()" class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100">Add Item</button>
                    </div>
                </div>

                <x-input-error :messages="$errors->get('items')" class="mt-3" />

                <div class="mt-5 space-y-4">
                    <template x-for="(item, index) in items" :key="index">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                            <div class="grid gap-4 lg:grid-cols-12">
                                <div class="lg:col-span-3">
                                    <label class="text-xs font-semibold uppercase text-gray-500">Item Name</label>
                                    <input :name="`items[${index}][item_name]`" x-model="item.item_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                </div>
                                <div class="lg:col-span-2">
                                    <label class="text-xs font-semibold uppercase text-gray-500">Description</label>
                                    <input :name="`items[${index}][description]`" x-model="item.description" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                </div>
                                <div class="lg:col-span-1">
                                    <label class="text-xs font-semibold uppercase text-gray-500">Qty</label>
                                    <input type="number" step="0.01" min="0" :name="`items[${index}][quantity]`" x-model.number="item.quantity" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                </div>
                                <div class="lg:col-span-1">
                                    <label class="text-xs font-semibold uppercase text-gray-500">Unit</label>
                                    <input type="number" step="0.01" min="0" :name="`items[${index}][unit_price]`" x-model.number="item.unit_price" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                </div>
                                <div class="lg:col-span-1">
                                    <label class="text-xs font-semibold uppercase text-gray-500">Line</label>
                                    <input type="number" step="0.01" min="0" :name="`items[${index}][line_total]`" x-model.number="item.line_total" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                </div>
                                <div class="lg:col-span-1">
                                    <label class="text-xs font-semibold uppercase text-gray-500">Tax %</label>
                                    <input type="number" step="0.01" min="0" max="100" :name="`items[${index}][tax_rate]`" x-model.number="item.tax_rate" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                </div>
                                <div class="lg:col-span-1">
                                    <label class="text-xs font-semibold uppercase text-gray-500">Tax</label>
                                    <input type="number" step="0.01" min="0" :name="`items[${index}][tax_amount]`" x-model.number="item.tax_amount" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                </div>
                                <div class="lg:col-span-1">
                                    <label class="text-xs font-semibold uppercase text-gray-500">Disc.</label>
                                    <input type="number" step="0.01" min="0" :name="`items[${index}][discount_amount]`" x-model.number="item.discount_amount" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                </div>
                                <div class="lg:col-span-1">
                                    <label class="text-xs font-semibold uppercase text-gray-500">Total</label>
                                    <p class="mt-3 text-sm font-semibold text-gray-900" x-text="money(lineTotal(item), '')"></p>
                                </div>
                                <div class="flex items-end lg:col-span-1">
                                    <button type="button" @click="removeItem(index)" class="rounded-md px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Remove</button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-lg bg-gray-50 p-4">
                        <p class="text-sm text-gray-500">Subtotal</p>
                        <p class="mt-1 text-xl font-semibold" x-text="money(itemSubtotal(), '')"></p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-4">
                        <p class="text-sm text-gray-500">Tax - Discount</p>
                        <p class="mt-1 text-xl font-semibold" x-text="money(taxTotal() - discountTotal(), '')"></p>
                    </div>
                    <div class="rounded-lg bg-emerald-50 p-4">
                        <p class="text-sm text-emerald-700">Invoice Total</p>
                        <p class="mt-1 text-xl font-semibold text-emerald-900" x-text="money(total(), '')"></p>
                    </div>
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <x-input-label for="notes" value="Notes" />
                    <textarea id="notes" name="notes" rows="5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('notes', $invoice?->notes ?? '') }}</textarea>
                </div>

                @if ($document)
                    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-semibold text-gray-900">OCR Reference</h3>
                            <a href="{{ route('documents.show', $document) }}" target="_blank" class="text-sm font-semibold text-emerald-700 hover:text-emerald-900">View Original</a>
                        </div>
                        <details class="mt-4" open>
                            <summary class="cursor-pointer text-sm font-semibold text-gray-700">Raw OCR Text</summary>
                            <p class="mt-2 text-xs text-gray-500">Use this raw OCR text as reference when correcting the fields.</p>
                            <pre class="mt-3 max-h-64 overflow-auto whitespace-pre-wrap rounded-lg bg-gray-950 p-4 text-xs text-gray-100">{{ $document->ocr_text ?: 'No OCR text available.' }}</pre>
                        </details>
                    </div>
                @endif
            </section>

            <div class="flex justify-end gap-3">
                <a href="{{ route('invoices.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">Cancel</a>
                <x-primary-button>Save Verified Invoice</x-primary-button>
            </div>
        </form>
    </div>
</div>

<script>
    function invoiceForm(initialItems, initialAmounts, rateUrl) {
        return {
            items: initialItems,
            originalItems: JSON.parse(JSON.stringify(initialItems)),
            amounts: {
                subtotal: Number(initialAmounts.subtotal || 0),
                tax_rate: initialAmounts.tax_rate === null ? null : Number(initialAmounts.tax_rate || 0),
                tax_amount: Number(initialAmounts.tax_amount || 0),
                discount_amount: Number(initialAmounts.discount_amount || 0),
                service_charge: Number(initialAmounts.service_charge || 0),
                total_amount: Number(initialAmounts.total_amount || 0),
            },
            rate: Number(initialAmounts.rate || 1),
            currency: initialAmounts.currency || 'MYR',
            rateStatus: '',
            rateStatusType: 'idle',
            async fetchRate() {
                const from = String(this.currency || '').trim().toUpperCase();

                if (from.length !== 3) {
                    this.rateStatus = 'Enter a 3-letter currency code first.';
                    this.rateStatusType = 'error';
                    return;
                }

                this.rateStatus = 'Fetching latest rate...';
                this.rateStatusType = 'idle';

                try {
                    const response = await fetch(`${rateUrl}?from=${encodeURIComponent(from)}`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.message || 'Unable to fetch exchange rate.');
                    }

                    this.rate = Number(data.rate);
                    this.rateStatus = `${data.source} rate on ${data.date}: 1 ${data.base} = ${Number(data.rate).toFixed(6)} MYR`;
                    this.rateStatusType = 'success';
                } catch (error) {
                    this.rateStatus = error.message;
                    this.rateStatusType = 'error';
                }
            },
            addItem() {
                this.items.push({ item_name: '', description: '', quantity: 1, unit_price: 0, tax_rate: null, tax_amount: 0, discount_amount: 0, line_total: 0 });
            },
            removeItem(index) {
                if (this.items.length > 1) {
                    this.items.splice(index, 1);
                }
            },
            resetToOcrItems() {
                this.items = JSON.parse(JSON.stringify(this.originalItems));
                this.recalculateTotals();
            },
            recalculateLine(item) {
                item.line_total = this.lineBase(item);
            },
            recalculateTotals() {
                this.amounts.subtotal = this.itemSubtotal();

                if (Number(this.amounts.tax_rate || 0) > 0) {
                    this.amounts.tax_amount = Number((this.amounts.subtotal * (Number(this.amounts.tax_rate) / 100)).toFixed(2));
                } else if (this.taxTotal() > 0) {
                    this.amounts.tax_amount = this.taxTotal();
                }

                if (this.discountTotal() > 0) {
                    this.amounts.discount_amount = this.discountTotal();
                }

                this.amounts.total_amount = this.expectedTotal();
            },
            lineBase(item) {
                return Number(item.quantity || 0) * Number(item.unit_price || 0);
            },
            lineTotal(item) {
                const base = Number(item.line_total || 0) > 0 ? Number(item.line_total || 0) : this.lineBase(item);
                return Math.max(base + Number(item.tax_amount || 0) - Number(item.discount_amount || 0), 0);
            },
            itemSubtotal() {
                return this.items.reduce((sum, item) => {
                    const explicit = Number(item.line_total || 0);
                    return sum + (explicit > 0 ? explicit : this.lineBase(item));
                }, 0);
            },
            taxTotal() {
                return this.items.reduce((sum, item) => sum + Number(item.tax_amount || 0), 0);
            },
            discountTotal() {
                return this.items.reduce((sum, item) => sum + Number(item.discount_amount || 0), 0);
            },
            expectedTotal() {
                return Math.max(
                    Number(this.amounts.subtotal || 0)
                    - Number(this.amounts.discount_amount || 0)
                    + Number(this.amounts.service_charge || 0)
                    + Number(this.amounts.tax_amount || 0),
                    0
                );
            },
            total() {
                return this.items.reduce((sum, item) => sum + this.lineTotal(item), 0);
            },
            money(value, prefix) {
                return `${prefix}${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            },
        };
    }
</script>
