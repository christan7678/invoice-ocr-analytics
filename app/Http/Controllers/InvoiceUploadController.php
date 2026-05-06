<?php

namespace App\Http\Controllers;

use App\Models\UploadedDocument;
use App\Services\InvoiceFieldExtractor;
use App\Services\OcrProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class InvoiceUploadController extends Controller
{
    public function index(Request $request): View
    {
        return view('invoice_upload.index', [
            'documents' => $request->user()->company->uploadedDocuments()
                ->latest()
                ->take(10)
                ->get(),
        ]);
    }

    public function store(Request $request, OcrProcessor $ocr, InvoiceFieldExtractor $extractor): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $company = $request->user()->company;
        $file = $validated['invoice_file'];
        $path = $file->store('invoice-documents');

        $document = UploadedDocument::create([
            'user_id' => $request->user()->id,
            'company_id' => $company->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $file->getClientOriginalExtension(),
            'processing_status' => 'uploaded',
            'uploaded_at' => now(),
        ]);

        try {
            $ocrResult = $ocr->extract(Storage::disk('local')->path($path));
            $fields = $extractor->extract($ocrResult['text'] ?? '');

            $document->update([
                'ocr_text' => $ocrResult['text'] ?? '',
                'processing_status' => 'processed',
                'processing_message' => 'OCR completed. Please verify extracted values before saving.',
            ]);

            $document->ocrExtractionResult()->create([
                'extracted_invoice_number' => $fields['invoice_number'],
                'extracted_invoice_date' => $fields['invoice_date'],
                'extracted_customer_name' => $fields['customer_name'],
                'extracted_subtotal' => $fields['subtotal'],
                'extracted_tax_amount' => $fields['tax_amount'],
                'extracted_discount_amount' => $fields['discount_amount'],
                'extracted_total_amount' => $fields['total_amount'],
                'extracted_currency_code' => $fields['currency_code'],
                'extracted_payment_status' => $fields['payment_status'],
                'confidence_score' => $ocrResult['confidence'] ?? $fields['confidence_score'],
            ]);
        } catch (\Throwable $exception) {
            $document->update([
                'processing_status' => 'failed',
                'processing_message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('invoice-upload.index')
                ->with('status', 'File uploaded, but OCR failed: '.$exception->getMessage());
        }

        return redirect()
            ->route('invoices.create', ['document' => $document->id])
            ->with('status', 'OCR completed. Verify the extracted invoice before saving.');
    }

    public function show(Request $request, UploadedDocument $document)
    {
        abort_unless($document->company_id === $request->user()->company->id, 403);

        return response()->file(Storage::disk('local')->path($document->file_path));
    }
}
