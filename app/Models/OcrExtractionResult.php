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
        'extracted_customer_name',
        'extracted_subtotal',
        'extracted_tax_amount',
        'extracted_discount_amount',
        'extracted_total_amount',
        'extracted_currency_code',
        'extracted_payment_status',
        'confidence_score',
        'is_verified',
    ];

    protected function casts(): array
    {
        return [
            'extracted_invoice_date' => 'date',
            'is_verified' => 'boolean',
        ];
    }

    public function uploadedDocument(): BelongsTo
    {
        return $this->belongsTo(UploadedDocument::class);
    }
}
