<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OcrExtractionResult extends Model
{
    protected $fillable = [
        'uploaded_document_id',
        'extracted_invoice_number',
        'extracted_invoice_date',
        'extracted_due_date',
        'extracted_customer_name',
        'extracted_customer_email',
        'extracted_customer_phone',
        'extracted_customer_address',
        'extracted_subtotal',
        'extracted_tax_amount',
        'extracted_tax_rate',
        'extracted_discount_amount',
        'extracted_service_charge',
        'extracted_total_amount',
        'extracted_currency_code',
        'extracted_payment_status',
        'extracted_items',
        'extraction_warnings',
        'field_confidences',
        'calculation_summary',
        'confidence_score',
        'is_verified',
    ];

    protected function casts(): array
    {
        return [
            'extracted_invoice_date' => 'date',
            'extracted_due_date' => 'date',
            'extracted_items' => 'array',
            'extraction_warnings' => 'array',
            'field_confidences' => 'array',
            'calculation_summary' => 'array',
            'is_verified' => 'boolean',
        ];
    }

    public function uploadedDocument(): BelongsTo
    {
        return $this->belongsTo(UploadedDocument::class);
    }
}
