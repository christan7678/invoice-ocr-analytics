<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'service_charge')) {
                $table->decimal('service_charge', 14, 2)->default(0)->after('discount_amount');
            }

            if (! Schema::hasColumn('invoices', 'tax_rate')) {
                $table->decimal('tax_rate', 8, 2)->nullable()->after('service_charge');
            }

            if (! Schema::hasColumn('invoices', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('payment_status');
            }

            if (! Schema::hasColumn('invoices', 'raw_ocr_text')) {
                $table->longText('raw_ocr_text')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('invoices', 'extraction_confidence_summary')) {
                $table->json('extraction_confidence_summary')->nullable()->after('raw_ocr_text');
            }
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_items', 'tax_rate')) {
                $table->decimal('tax_rate', 8, 2)->nullable()->after('unit_price');
            }

            if (! Schema::hasColumn('invoice_items', 'line_total')) {
                $table->decimal('line_total', 14, 2)->nullable()->after('discount_amount');
            }
        });

        Schema::table('ocr_extraction_results', function (Blueprint $table) {
            if (! Schema::hasColumn('ocr_extraction_results', 'extracted_due_date')) {
                $table->date('extracted_due_date')->nullable()->after('extracted_invoice_date');
            }

            if (! Schema::hasColumn('ocr_extraction_results', 'extracted_customer_email')) {
                $table->string('extracted_customer_email', 191)->nullable()->after('extracted_customer_name');
            }

            if (! Schema::hasColumn('ocr_extraction_results', 'extracted_customer_phone')) {
                $table->string('extracted_customer_phone', 50)->nullable()->after('extracted_customer_email');
            }

            if (! Schema::hasColumn('ocr_extraction_results', 'extracted_customer_address')) {
                $table->text('extracted_customer_address')->nullable()->after('extracted_customer_phone');
            }

            if (! Schema::hasColumn('ocr_extraction_results', 'extracted_tax_rate')) {
                $table->decimal('extracted_tax_rate', 8, 2)->nullable()->after('extracted_tax_amount');
            }

            if (! Schema::hasColumn('ocr_extraction_results', 'extracted_service_charge')) {
                $table->decimal('extracted_service_charge', 14, 2)->nullable()->after('extracted_discount_amount');
            }

            if (! Schema::hasColumn('ocr_extraction_results', 'extracted_items')) {
                $table->json('extracted_items')->nullable()->after('extracted_payment_status');
            }

            if (! Schema::hasColumn('ocr_extraction_results', 'extraction_warnings')) {
                $table->json('extraction_warnings')->nullable()->after('extracted_items');
            }

            if (! Schema::hasColumn('ocr_extraction_results', 'field_confidences')) {
                $table->json('field_confidences')->nullable()->after('extraction_warnings');
            }

            if (! Schema::hasColumn('ocr_extraction_results', 'calculation_summary')) {
                $table->json('calculation_summary')->nullable()->after('field_confidences');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ocr_extraction_results', function (Blueprint $table) {
            foreach ([
                'extracted_due_date',
                'extracted_customer_email',
                'extracted_customer_phone',
                'extracted_customer_address',
                'extracted_tax_rate',
                'extracted_service_charge',
                'extracted_items',
                'extraction_warnings',
                'field_confidences',
                'calculation_summary',
            ] as $column) {
                if (Schema::hasColumn('ocr_extraction_results', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            foreach (['tax_rate', 'line_total'] as $column) {
                if (Schema::hasColumn('invoice_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            foreach (['service_charge', 'tax_rate', 'paid_at', 'raw_ocr_text', 'extraction_confidence_summary'] as $column) {
                if (Schema::hasColumn('invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
