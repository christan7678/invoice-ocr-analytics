<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UploadedDocument extends Model
{
    protected $fillable = [
        'user_id',
        'company_id',
        'file_name',
        'file_path',
        'file_type',
        'ocr_text',
        'processing_status',
        'processing_message',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function ocrExtractionResult(): HasOne
    {
        return $this->hasOne(OcrExtractionResult::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }
}
