@php
    $sourceItems = old('items');

    if (! $sourceItems) {
        $sourceItems = $invoice?->items?->map(fn ($item) => [
            'item_name' => $item->item_name,
            'description' => $item->description,
            'quantity' => (float) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'tax_amount' => (float) $item->tax_amount,
            'discount_amount' => (float) $item->discount_amount,
        ])->values()->all();
    }

    if (! $sourceItems) {
        $sourceItems = [[
            'item_name' => 'Sales invoice item',
            'description' => '',
            'quantity' => 1,
            'unit_price' => (float) ($extraction?->extracted_total_amount ?? 0),
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]];
    }

    $selectedCurrency = old('currency_code', $invoice->currency_code ?? $extraction?->extracted_currency_code ?? $company->default_currency_code ?? 'MYR');
@endphp

<div class="py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ $action }}" x-data="invoiceForm({{ Js::from($sourceItems) }}, '{{ old('exchange_rate_to_myr', $invoice->exchange_rate_to_myr ?? 1) }}', '{{ $selectedCurrency }}', '{{ route('exchange-rate.show') }}')" class="space-y-6">
            @csrf
            @if ($method !== 'POST')
                @method($method)
            @endif

            @if ($document)
                <input type="hidden" name="uploaded_document_id" value="{{ $document->id }}">
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
                            <x-text-input id="invoice_number" name="invoice_number" class="mt-1 block w-full" value="{{ old('invoice_number', $invoice->invoice_number ?? $extraction?->extracted_invoice_number ?? '') }}" required />
                            <x-input-error :messages="$errors->get('invoice_number')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="invoice_date" value="Invoice Date" />
                            <x-text-input id="invoice_date" name="invoice_date" type="date" class="mt-1 block w-full" value="{{ old('invoice_date', optional($invoice?->invoice_date)->format('Y-m-d') ?? optional($extraction?->extracted_invoice_date)->format('Y-m-d')) }}" required />
                            <x-input-error :messages="$errors->get('invoice_date')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="due_date" value="Due Date" />
                            <x-text-input id="due_date" name="due_date" type="date" class="mt-1 block w-full" value="{{ old('due_date', optional($invoice?->due_date)->format('Y-m-d')) }}" />
                        </div>

                        <div>
                            <x-input-label for="payment_status" value="Payment Status" />
                            <select id="payment_status" name="payment_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                @foreach ($paymentStatuses as $value => $label)
                                    <option value="{{ $value }}" @selected(old('payment_status', $invoice->payment_status ?? $extraction?->extracted_payment_status ?? 'pending') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
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
                            <p class="mt-1 text-2xl font-semibold text-gray-900" x-text="money(total() * rate, 'RM')"></p>
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
                        <datalist id="customer-options">
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->customer_name }}">{{ $customer->customer_email }}</option>
                            @endforeach
                        </datalist>
                    </div>

                    <div>
                        <x-input-label for="customer_email" value="Customer Email" />
                        <x-text-input id="customer_email" name="customer_email" type="email" class="mt-1 block w-full" value="{{ old('customer_email', $invoice?->customer?->customer_email ?? '') }}" />
                    </div>

                    <div>
                        <x-input-label for="customer_phone" value="Customer Phone" />
                        <x-text-input id="customer_phone" name="customer_phone" class="mt-1 block w-full" value="{{ old('customer_phone', $invoice?->customer?->customer_phone ?? '') }}" />
                    </div>

                    <div>
                        <x-input-label for="customer_address" value="Customer Address" />
                        <textarea id="customer_address" name="customer_address" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('customer_address', $invoice?->customer?->address ?? '') }}</textarea>
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Invoice Items</h3>
                        <p class="mt-1 text-sm text-gray-500">If OCR cannot read detailed line items, use one summary item that matches the invoice total.</p>
                    </div>
                    <button type="button" @click="addItem()" class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100">Add Item</button>
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
                                <div class="lg:col-span-3">
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
                        <p class="mt-1 text-xl font-semibold" x-text="money(subtotal(), '')"></p>
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
                    <textarea id="notes" name="notes" rows="5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('notes', $invoice->notes ?? '') }}</textarea>
                </div>

                @if ($document)
                    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-semibold text-gray-900">OCR Reference</h3>
                            <a href="{{ route('documents.show', $document) }}" target="_blank" class="text-sm font-semibold text-emerald-700 hover:text-emerald-900">View Original</a>
                        </div>
                        <pre class="mt-4 max-h-64 overflow-auto whitespace-pre-wrap rounded-lg bg-gray-950 p-4 text-xs text-gray-100">{{ $document->ocr_text ?: 'No OCR text available.' }}</pre>
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
    function invoiceForm(initialItems, initialRate, initialCurrency, rateUrl) {
        return {
            items: initialItems,
            rate: Number(initialRate || 1),
            currency: initialCurrency || 'MYR',
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
                this.items.push({ item_name: '', description: '', quantity: 1, unit_price: 0, tax_amount: 0, discount_amount: 0 });
            },
            removeItem(index) {
                if (this.items.length > 1) {
                    this.items.splice(index, 1);
                }
            },
            lineBase(item) {
                return Number(item.quantity || 0) * Number(item.unit_price || 0);
            },
            lineTotal(item) {
                return Math.max(this.lineBase(item) + Number(item.tax_amount || 0) - Number(item.discount_amount || 0), 0);
            },
            subtotal() {
                return this.items.reduce((sum, item) => sum + this.lineBase(item), 0);
            },
            taxTotal() {
                return this.items.reduce((sum, item) => sum + Number(item.tax_amount || 0), 0);
            },
            discountTotal() {
                return this.items.reduce((sum, item) => sum + Number(item.discount_amount || 0), 0);
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
