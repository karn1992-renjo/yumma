<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tax_ledger_entries')) {
            Schema::create('tax_ledger_entries', function (Blueprint $table) {
                $table->id();
                // party = the entity the entry is *about*: a restaurant, a
                // user (driver), or null when it is the platform's own liability.
                $table->string('party_type', 40)->nullable();
                $table->unsignedBigInteger('party_id')->nullable();
                $table->foreignId('order_id')->nullable();
                $table->foreignId('payout_id')->nullable();
                // gst_9_5 | gst_service | gst_commission | tcs | tds_194o | tds_194c
                $table->string('kind', 24)->index();
                $table->string('section', 16)->nullable();
                $table->decimal('taxable_value', 14, 2)->default(0);
                $table->decimal('rate', 6, 3)->default(0);
                $table->decimal('cgst', 12, 2)->default(0);
                $table->decimal('sgst', 12, 2)->default(0);
                $table->decimal('igst', 12, 2)->default(0);
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('fy', 9)->index();       // 2026-27
                $table->string('period', 7)->index();    // 2026-09
                $table->string('status', 12)->default('accrued'); // accrued|deposited|filed
                $table->string('challan_no', 40)->nullable();
                $table->date('challan_date')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['order_id', 'kind']);
                $table->unique(['payout_id', 'kind']);
                $table->index(['party_type', 'party_id', 'fy']);
                $table->index(['kind', 'period']);
            });
        }

        if (! Schema::hasTable('tds_deductee_totals')) {
            Schema::create('tds_deductee_totals', function (Blueprint $table) {
                $table->id();
                $table->string('party_type', 40);
                $table->unsignedBigInteger('party_id');
                $table->string('pan', 15)->nullable();
                $table->string('fy', 9);
                $table->string('section', 12);          // 194O | 194C
                $table->decimal('gross_ytd', 16, 2)->default(0);
                $table->decimal('tds_ytd', 14, 2)->default(0);
                $table->timestamps();

                $table->unique(['party_type', 'party_id', 'fy', 'section'], 'tds_deductee_totals_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_ledger_entries');
        Schema::dropIfExists('tds_deductee_totals');
    }
};
