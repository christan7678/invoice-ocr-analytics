<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    public const VERIFIED_STATUSES = [
        'manual',
        'verified',
        'verified_from_ocr',
    ];

    public const PAYMENT_STATUSES = [
        'paid' => 'Paid',
        'unpaid' => 'Unpaid',
        'pending' => 'Pending',
        'partial' => 'Partial',
        'overdue' => 'Overdue',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'company_id',
        'customer_id',
        'uploaded_document_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'service_charge',
        'tax_rate',
        'total_amount',
        'currency_code',
        'exchange_rate_to_myr',
        'total_amount_myr',
        'payment_status',
        'paid_at',
        'verification_status',
        'notes',
        'raw_ocr_text',
        'extraction_confidence_summary',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'extraction_confidence_summary' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function uploadedDocument(): BelongsTo
    {
        return $this->belongsTo(UploadedDocument::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function scopeVerifiedForAnalytics($query)
    {
        return $query->whereIn('verification_status', self::VERIFIED_STATUSES);
    }

    public function paymentStatusLabel(): string
    {
        return self::PAYMENT_STATUSES[$this->payment_status] ?? ucfirst((string) $this->payment_status);
    }

    public function verificationStatusLabel(): string
    {
        return match ($this->verification_status) {
            'manual' => 'Manual Verified',
            'verified' => 'Verified',
            'verified_from_ocr' => 'Verified From OCR',
            'pending_user_verification' => 'Pending User Verification',
            'ocr_extracted' => 'OCR Extracted',
            default => ucfirst(str_replace('_', ' ', (string) $this->verification_status)),
        };
    }
}
