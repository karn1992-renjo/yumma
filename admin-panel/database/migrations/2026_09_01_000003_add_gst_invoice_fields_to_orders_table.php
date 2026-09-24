<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $add = function (string $name, callable $definition) use ($table) {
                if (! Schema::hasColumn('orders', $name)) {
                    $definition($table);
                }
            };

            // GST computation snapshot -- the exact split that was charged.
            $add('tax_breakdown', fn ($t) => $t->json('tax_breakdown')->nullable()->after('tax'));
            $add('cgst_amount', fn ($t) => $t->decimal('cgst_amount', 12, 2)->nullable()->after('tax_breakdown'));
            $add('sgst_amount', fn ($t) => $t->decimal('sgst_amount', 12, 2)->nullable()->after('cgst_amount'));
            $add('igst_amount', fn ($t) => $t->decimal('igst_amount', 12, 2)->nullable()->after('sgst_amount'));
            $add('place_of_supply', fn ($t) => $t->string('place_of_supply', 100)->nullable()->after('igst_amount'));
            $add('supplier_gstin', fn ($t) => $t->string('supplier_gstin', 20)->nullable()->after('place_of_supply'));
            $add('buyer_gstin', fn ($t) => $t->string('buyer_gstin', 20)->nullable()->after('supplier_gstin'));

            // Invoice identity -- allocated once, immutable.
            $add('invoice_number', fn ($t) => $t->string('invoice_number', 64)->nullable()->after('buyer_gstin'));
            $add('invoice_series', fn ($t) => $t->string('invoice_series', 32)->nullable()->after('invoice_number'));
            $add('invoice_type', fn ($t) => $t->string('invoice_type', 20)->nullable()->after('invoice_series'));
            $add('invoice_date', fn ($t) => $t->timestamp('invoice_date')->nullable()->after('invoice_type'));

            // E-invoice (IRN / signed QR) -- manual or GSP.
            $add('einvoice_status', fn ($t) => $t->string('einvoice_status', 16)->default('none')->after('invoice_date'));
            $add('einvoice_irn', fn ($t) => $t->string('einvoice_irn', 128)->nullable()->after('einvoice_status'));
            $add('einvoice_qr', fn ($t) => $t->text('einvoice_qr')->nullable()->after('einvoice_irn'));
            $add('einvoice_ack_no', fn ($t) => $t->string('einvoice_ack_no', 64)->nullable()->after('einvoice_qr'));
            $add('einvoice_acked_at', fn ($t) => $t->timestamp('einvoice_acked_at')->nullable()->after('einvoice_ack_no'));
        });

        if (! Schema::hasColumn('orders', 'invoice_number')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->index('invoice_number');
            $table->index(['invoice_type', 'created_at']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            foreach ([
                'tax_breakdown', 'cgst_amount', 'sgst_amount', 'igst_amount', 'place_of_supply',
                'supplier_gstin', 'buyer_gstin', 'invoice_number', 'invoice_series', 'invoice_type',
                'invoice_date', 'einvoice_status', 'einvoice_irn', 'einvoice_qr', 'einvoice_ack_no',
                'einvoice_acked_at',
            ] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
