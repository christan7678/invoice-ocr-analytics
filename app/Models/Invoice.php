<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
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
        'total_amount',
        'currency_code',
        'exchange_rate_to_myr',
        'total_amount_myr',
        'payment_status',
        'verification_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
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

    public function paymentStatusLabel(): string
    {
        return self::PAYMENT_STATUSES[$this->payment_status] ?? ucfirst((string) $this->payment_status);
    }
}
