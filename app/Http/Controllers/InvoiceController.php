<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\OcrExtractionResult;
use App\Models\UploadedDocument;
use App\Services\InvoiceCalculationService;
use App\Services\InvoiceRiskAnalyzer;
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
    public function create(Request $request, InvoiceRiskAnalyzer $riskAnalyzer): View
    {
        $company = $request->user()->company;
        $document = null;
        $extraction = null;
        $riskAlerts = [];

        if ($request->filled('document')) {
            $document = UploadedDocument::with('ocrExtractionResult')
                ->where('company_id', $company->id)
                ->findOrFail($request->integer('document'));
            $extraction = $document->ocrExtractionResult;
            $riskAlerts = $riskAnalyzer->forExtraction(array_merge(
                $this->extractionPayload($extraction),
                ['raw_ocr_text' => $document->ocr_text]
            ), $company->id);
        }

        return view('invoices.create', [
            'company' => $company,
            'customers' => $company->customers()->orderBy('customer_name')->get(),
            'currencies' => Currency::common(),
            'paymentStatuses' => Invoice::PAYMENT_STATUSES,
            'document' => $document,
            'extraction' => $extraction,
            'invoice' => null,
            'riskAlerts' => $riskAlerts,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, InvoiceCalculationService $calculator, InvoiceRiskAnalyzer $riskAnalyzer): RedirectResponse
    {
        $company = $request->user()->company;
        $validated = $this->validatedInvoice($request, $company->id);
        $customer = $this->customerFromRequest($validated, $company->id);
        $calculation = $calculator->calculate($validated);
        $document = isset($validated['uploaded_document_id'])
            ? UploadedDocument::with('ocrExtractionResult')->where('company_id', $company->id)->find($validated['uploaded_document_id'])
            : null;
        $riskAlerts = $riskAnalyzer->forInvoiceData($validated, $company->id, null, $document?->ocr_text, $calculation['warnings']);
        $confidenceSummary = $this->confidenceSummary($document?->ocrExtractionResult, $calculation['warnings'], $riskAlerts);

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'uploaded_document_id' => $validated['uploaded_document_id'] ?? null,
            'invoice_number' => $validated['invoice_number'],
            'invoice_date' => $validated['invoice_date'],
            'due_date' => $validated['due_date'] ?? null,
            'subtotal' => $calculation['subtotal'],
            'tax_rate' => $calculation['tax_rate'],
            'tax_amount' => $calculation['tax_amount'],
            'discount_amount' => $calculation['discount_amount'],
            'service_charge' => $calculation['service_charge'],
            'total_amount' => $calculation['total_amount'],
            'currency_code' => strtoupper($validated['currency_code']),
            'exchange_rate_to_myr' => $validated['exchange_rate_to_myr'],
            'total_amount_myr' => round($calculation['total_amount'] * $validated['exchange_rate_to_myr'], 2),
            'payment_status' => $validated['payment_status'],
            'paid_at' => $validated['paid_at'] ?? null,
            'verification_status' => isset($validated['uploaded_document_id']) ? 'verified_from_ocr' : 'manual',
            'notes' => $validated['notes'] ?? null,
            'raw_ocr_text' => $document?->ocr_text,
            'extraction_confidence_summary' => $confidenceSummary,
        ]);

        $this->syncItems($invoice, $calculation['items']);

        if ($invoice->uploadedDocument?->ocrExtractionResult) {
            $invoice->uploadedDocument->ocrExtractionResult->update(['is_verified' => true]);
        }

        return redirect()->route('invoices.show', $invoice)->with('status', 'Invoice saved.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Invoice $invoice, InvoiceRiskAnalyzer $riskAnalyzer): View
    {
        $this->authorizeCompany($request, $invoice);

        return view('invoices.show', [
            'invoice' => $invoice->load(['customer', 'items', 'uploadedDocument']),
            'riskAlerts' => $riskAnalyzer->forSavedInvoice($invoice->load(['customer', 'items'])),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Invoice $invoice, InvoiceRiskAnalyzer $riskAnalyzer): View
    {
        $this->authorizeCompany($request, $invoice);
        $company = $request->user()->company;
        $invoice->load(['customer', 'items', 'uploadedDocument.ocrExtractionResult']);

        return view('invoices.edit', [
            'company' => $company,
            'customers' => $company->customers()->orderBy('customer_name')->get(),
            'currencies' => Currency::common(),
            'paymentStatuses' => Invoice::PAYMENT_STATUSES,
            'invoice' => $invoice,
            'document' => $invoice->uploadedDocument,
            'extraction' => $invoice->uploadedDocument?->ocrExtractionResult,
            'riskAlerts' => $riskAnalyzer->forSavedInvoice($invoice),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Invoice $invoice, InvoiceCalculationService $calculator, InvoiceRiskAnalyzer $riskAnalyzer): RedirectResponse
    {
        $this->authorizeCompany($request, $invoice);
        $company = $request->user()->company;
        $validated = $this->validatedInvoice($request, $company->id, $invoice->id);
        $customer = $this->customerFromRequest($validated, $company->id);
        $calculation = $calculator->calculate($validated);
        $document = isset($validated['uploaded_document_id'])
            ? UploadedDocument::with('ocrExtractionResult')->where('company_id', $company->id)->find($validated['uploaded_document_id'])
            : $invoice->uploadedDocument;
        $riskAlerts = $riskAnalyzer->forInvoiceData($validated, $company->id, $invoice->id, $document?->ocr_text, $calculation['warnings']);
        $confidenceSummary = $this->confidenceSummary($document?->ocrExtractionResult, $calculation['warnings'], $riskAlerts);

        $invoice->update([
            'customer_id' => $customer->id,
            'uploaded_document_id' => $validated['uploaded_document_id'] ?? $invoice->uploaded_document_id,
            'invoice_number' => $validated['invoice_number'],
            'invoice_date' => $validated['invoice_date'],
            'due_date' => $validated['due_date'] ?? null,
            'subtotal' => $calculation['subtotal'],
            'tax_rate' => $calculation['tax_rate'],
            'tax_amount' => $calculation['tax_amount'],
            'discount_amount' => $calculation['discount_amount'],
            'service_charge' => $calculation['service_charge'],
            'total_amount' => $calculation['total_amount'],
            'currency_code' => strtoupper($validated['currency_code']),
            'exchange_rate_to_myr' => $validated['exchange_rate_to_myr'],
            'total_amount_myr' => round($calculation['total_amount'] * $validated['exchange_rate_to_myr'], 2),
            'payment_status' => $validated['payment_status'],
            'paid_at' => $validated['paid_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'raw_ocr_text' => $document?->ocr_text ?? $invoice->raw_ocr_text,
            'extraction_confidence_summary' => $confidenceSummary,
        ]);

        $this->syncItems($invoice, $calculation['items']);

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
            'subtotal' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'service_charge' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_status' => ['required', Rule::in(array_keys(Invoice::PAYMENT_STATUSES))],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['nullable', 'string', 'max:191'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.line_total' => ['nullable', 'numeric', 'min:0'],
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

    private function syncItems(Invoice $invoice, array $items): void
    {
        $invoice->items()->delete();

        foreach ($items as $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $lineTotal = (float) ($item['line_total'] ?? round($quantity * $unitPrice, 2));
            $tax = (float) ($item['tax_amount'] ?? 0);
            $discount = (float) ($item['discount_amount'] ?? 0);

            $invoice->items()->create([
                'item_name' => $item['item_name'],
                'description' => $item['description'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => $item['tax_rate'] ?? null,
                'tax_amount' => $tax,
                'discount_amount' => $discount,
                'line_total' => $lineTotal,
                'total_price' => round(max($lineTotal + $tax - $discount, 0), 2),
            ]);
        }
    }

    private function extractionPayload(?OcrExtractionResult $extraction): array
    {
        if (! $extraction) {
            return [];
        }

        return [
            'invoice_number' => $extraction->extracted_invoice_number,
            'invoice_date' => $extraction->extracted_invoice_date?->toDateString(),
            'due_date' => $extraction->extracted_due_date?->toDateString(),
            'customer_name' => $extraction->extracted_customer_name,
            'customer_email' => $extraction->extracted_customer_email,
            'customer_phone' => $extraction->extracted_customer_phone,
            'customer_address' => $extraction->extracted_customer_address,
            'subtotal' => $extraction->extracted_subtotal,
            'tax_rate' => $extraction->extracted_tax_rate,
            'tax_amount' => $extraction->extracted_tax_amount,
            'discount_amount' => $extraction->extracted_discount_amount,
            'service_charge' => $extraction->extracted_service_charge,
            'total_amount' => $extraction->extracted_total_amount,
            'currency_code' => $extraction->extracted_currency_code ?? 'MYR',
            'payment_status' => $extraction->extracted_payment_status ?? 'pending',
            'items' => $extraction->extracted_items ?? [],
            'warnings' => $extraction->extraction_warnings ?? [],
        ];
    }

    private function confidenceSummary(?OcrExtractionResult $extraction, array $calculationWarnings, array $riskAlerts): array
    {
        return [
            'field_confidences' => $extraction?->field_confidences ?? [],
            'extraction_warnings' => $extraction?->extraction_warnings ?? [],
            'calculation_warnings' => $calculationWarnings,
            'risk_alerts' => $riskAlerts,
            'confidence_score' => $extraction?->confidence_score,
        ];
    }

    private function authorizeCompany(Request $request, Invoice $invoice): void
    {
        abort_unless($invoice->company_id === $request->user()->company->id, 403);
    }
}
