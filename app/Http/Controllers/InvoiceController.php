<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\UploadedDocument;
use App\Support\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $company = $request->user()->company;
        $query = $company->invoices()->with(['customer', 'uploadedDocument'])->latest('invoice_date');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($query) use ($search) {
                $query->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('customer_name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('invoice_date', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('invoice_date', '<=', $request->date('date_to'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->string('payment_status'));
        }

        return view('invoices.index', [
            'invoices' => $query->paginate(10)->withQueryString(),
            'customers' => $company->customers()->orderBy('customer_name')->get(),
            'paymentStatuses' => Invoice::PAYMENT_STATUSES,
            'filters' => $request->only(['search', 'date_from', 'date_to', 'customer_id', 'payment_status']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): View
    {
        $company = $request->user()->company;
        $document = null;
        $extraction = null;

        if ($request->filled('document')) {
            $document = UploadedDocument::with('ocrExtractionResult')
                ->where('company_id', $company->id)
                ->findOrFail($request->integer('document'));
            $extraction = $document->ocrExtractionResult;
        }

        return view('invoices.create', [
            'company' => $company,
            'customers' => $company->customers()->orderBy('customer_name')->get(),
            'currencies' => Currency::common(),
            'paymentStatuses' => Invoice::PAYMENT_STATUSES,
            'document' => $document,
            'extraction' => $extraction,
            'invoice' => null,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $company = $request->user()->company;
        $validated = $this->validatedInvoice($request, $company->id);
        $customer = $this->customerFromRequest($validated, $company->id);
        $totals = $this->calculateTotals($validated['items']);

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'uploaded_document_id' => $validated['uploaded_document_id'] ?? null,
            'invoice_number' => $validated['invoice_number'],
            'invoice_date' => $validated['invoice_date'],
            'due_date' => $validated['due_date'] ?? null,
            'subtotal' => $totals['subtotal'],
            'tax_amount' => $totals['tax_amount'],
            'discount_amount' => $totals['discount_amount'],
            'total_amount' => $totals['total_amount'],
            'currency_code' => strtoupper($validated['currency_code']),
            'exchange_rate_to_myr' => $validated['exchange_rate_to_myr'],
            'total_amount_myr' => round($totals['total_amount'] * $validated['exchange_rate_to_myr'], 2),
            'payment_status' => $validated['payment_status'],
            'verification_status' => isset($validated['uploaded_document_id']) ? 'verified_from_ocr' : 'manual',
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->syncItems($invoice, $validated['items']);

        if ($invoice->uploadedDocument?->ocrExtractionResult) {
            $invoice->uploadedDocument->ocrExtractionResult->update(['is_verified' => true]);
        }

        return redirect()->route('invoices.show', $invoice)->with('status', 'Invoice saved.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Invoice $invoice): View
    {
        $this->authorizeCompany($request, $invoice);

        return view('invoices.show', [
            'invoice' => $invoice->load(['customer', 'items', 'uploadedDocument']),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Invoice $invoice): View
    {
        $this->authorizeCompany($request, $invoice);
        $company = $request->user()->company;

        return view('invoices.edit', [
            'company' => $company,
            'customers' => $company->customers()->orderBy('customer_name')->get(),
            'currencies' => Currency::common(),
            'paymentStatuses' => Invoice::PAYMENT_STATUSES,
            'invoice' => $invoice->load(['customer', 'items', 'uploadedDocument.ocrExtractionResult']),
            'document' => $invoice->uploadedDocument,
            'extraction' => $invoice->uploadedDocument?->ocrExtractionResult,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeCompany($request, $invoice);
        $company = $request->user()->company;
        $validated = $this->validatedInvoice($request, $company->id, $invoice->id);
        $customer = $this->customerFromRequest($validated, $company->id);
        $totals = $this->calculateTotals($validated['items']);

        $invoice->update([
            'customer_id' => $customer->id,
            'uploaded_document_id' => $validated['uploaded_document_id'] ?? $invoice->uploaded_document_id,
            'invoice_number' => $validated['invoice_number'],
            'invoice_date' => $validated['invoice_date'],
            'due_date' => $validated['due_date'] ?? null,
            'subtotal' => $totals['subtotal'],
            'tax_amount' => $totals['tax_amount'],
            'discount_amount' => $totals['discount_amount'],
            'total_amount' => $totals['total_amount'],
            'currency_code' => strtoupper($validated['currency_code']),
            'exchange_rate_to_myr' => $validated['exchange_rate_to_myr'],
            'total_amount_myr' => round($totals['total_amount'] * $validated['exchange_rate_to_myr'], 2),
            'payment_status' => $validated['payment_status'],
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->syncItems($invoice, $validated['items']);

        return redirect()->route('invoices.show', $invoice)->with('status', 'Invoice updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeCompany($request, $invoice);
        $invoice->delete();

        return redirect()->route('invoices.index')->with('status', 'Invoice deleted.');
    }

    private function validatedInvoice(Request $request, int $companyId, ?int $ignoreInvoiceId = null): array
    {
        $validated = $request->validate([
            'uploaded_document_id' => ['nullable', 'integer', Rule::exists('uploaded_documents', 'id')->where('company_id', $companyId)],
            'invoice_number' => [
                'required',
                'string',
                'max:191',
                Rule::unique('invoices', 'invoice_number')->where('company_id', $companyId)->ignore($ignoreInvoiceId),
            ],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'customer_name' => ['required', 'string', 'max:191'],
            'customer_email' => ['nullable', 'email', 'max:191'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_address' => ['nullable', 'string', 'max:1000'],
            'currency_code' => ['required', 'string', 'size:3'],
            'exchange_rate_to_myr' => ['required', 'numeric', 'min:0.000001', 'max:999999'],
            'payment_status' => ['required', Rule::in(array_keys(Invoice::PAYMENT_STATUSES))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['nullable', 'string', 'max:191'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $validated['items'] = collect($validated['items'])
            ->filter(fn ($item) => filled($item['item_name'] ?? null))
            ->values()
            ->all();

        if (count($validated['items']) === 0) {
            throw ValidationException::withMessages([
                'items' => 'At least one invoice item is required.',
            ]);
        }

        $validated['currency_code'] = strtoupper($validated['currency_code']);

        return $validated;
    }

    private function customerFromRequest(array $validated, int $companyId): Customer
    {
        $customer = Customer::firstOrNew([
            'company_id' => $companyId,
            'customer_name' => $validated['customer_name'],
        ]);

        $customer->fill([
            'customer_email' => $validated['customer_email'] ?? $customer->customer_email,
            'customer_phone' => $validated['customer_phone'] ?? $customer->customer_phone,
            'address' => $validated['customer_address'] ?? $customer->address,
        ])->save();

        return $customer;
    }

    private function calculateTotals(array $items): array
    {
        $subtotal = 0;
        $tax = 0;
        $discount = 0;
        $total = 0;

        foreach ($items as $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $itemTax = (float) ($item['tax_amount'] ?? 0);
            $itemDiscount = (float) ($item['discount_amount'] ?? 0);
            $lineBase = $quantity * $unitPrice;
            $lineTotal = $lineBase + $itemTax - $itemDiscount;

            $subtotal += $lineBase;
            $tax += $itemTax;
            $discount += $itemDiscount;
            $total += $lineTotal;
        }

        return [
            'subtotal' => round($subtotal, 2),
            'tax_amount' => round($tax, 2),
            'discount_amount' => round($discount, 2),
            'total_amount' => round(max($total, 0), 2),
        ];
    }

    private function syncItems(Invoice $invoice, array $items): void
    {
        $invoice->items()->delete();

        foreach ($items as $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $tax = (float) ($item['tax_amount'] ?? 0);
            $discount = (float) ($item['discount_amount'] ?? 0);

            $invoice->items()->create([
                'item_name' => $item['item_name'],
                'description' => $item['description'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_amount' => $tax,
                'discount_amount' => $discount,
                'total_price' => round(max(($quantity * $unitPrice) + $tax - $discount, 0), 2),
            ]);
        }
    }

    private function authorizeCompany(Request $request, Invoice $invoice): void
    {
        abort_unless($invoice->company_id === $request->user()->company->id, 403);
    }
}
