<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ocr_extraction_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_document_id')->constrained()->cascadeOnDelete();
            $table->string('extracted_invoice_number', 191)->nullable();
            $table->date('extracted_invoice_date')->nullable();
            $table->string('extracted_customer_name', 191)->nullable();
            $table->decimal('extracted_subtotal', 14, 2)->nullable();
            $table->decimal('extracted_tax_amount', 14, 2)->nullable();
            $table->decimal('extracted_discount_amount', 14, 2)->nullable();
            $table->decimal('extracted_total_amount', 14, 2)->nullable();
            $table->string('extracted_currency_code', 3)->nullable();
            $table->string('extracted_payment_status', 30)->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ocr_extraction_results');
    }
};
