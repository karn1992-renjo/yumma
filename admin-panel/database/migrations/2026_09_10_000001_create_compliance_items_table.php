<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('authority', 60)->nullable();
            $table->string('frequency', 20)->default('annual'); // monthly|quarterly|annual|one_time
            $table->string('category', 30)->default('other');   // gst|tds|roc|labour|income_tax|licence|cess|other
            $table->json('applies_rule')->nullable();            // entity_type[], has_employees, turnover_min, needs_gst, needs_tds
            $table->string('due_rule')->nullable();              // human/cron-ish hint, e.g. "20th of next month"
            $table->string('status', 20)->default('pending');    // not_applicable|pending|filed|overdue
            $table->string('period', 7)->nullable();             // YYYY-MM for the current open obligation
            $table->date('due_date')->nullable();
            $table->date('filed_on')->nullable();
            $table->string('reference_no', 80)->nullable();
            $table->string('attachment')->nullable();
            $table->string('linked_export', 80)->nullable();     // route hint to the matching export
            $table->text('notes')->nullable();
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });

        $rows = [
            // ---- GST (auto-tracked) ----
            ['GSTR1', 'GSTR-1 — outward supplies', 'GSTN', 'monthly', 'gst', ['needs_gst' => true], '11th of next month', 'admin.accounting.gst'],
            ['GSTR3B', 'GSTR-3B — summary return & payment', 'GSTN', 'monthly', 'gst', ['needs_gst' => true], '20th of next month', 'admin.accounting.gst'],
            ['GSTR8', 'GSTR-8 — TCS collected (Sec 52)', 'GSTN', 'monthly', 'gst', ['needs_gst' => true, 'needs_tcs' => true], '10th of next month', 'admin.accounting.tcs'],
            ['GSTR9', 'GSTR-9 — annual return', 'GSTN', 'annual', 'gst', ['needs_gst' => true], '31 Dec of next FY', 'admin.accounting.gst'],
            ['GSTR9C', 'GSTR-9C — reconciliation statement', 'GSTN', 'annual', 'gst', ['needs_gst' => true, 'turnover_min' => '5cr_10cr'], '31 Dec of next FY', null],

            // ---- TDS ----
            ['TDS26Q', 'Form 26Q — quarterly TDS return', 'Income Tax Dept', 'quarterly', 'tds', ['needs_tds' => true], 'End of month after the quarter', 'admin.accounting.tds'],
            ['TDS_CHALLAN', 'TDS challan deposit (ITNS 281)', 'Income Tax Dept', 'monthly', 'tds', ['needs_tds' => true], '7th of next month', 'admin.accounting.tds'],
            ['FORM16A', 'Form 16A — TDS certificates to deductees', 'Income Tax Dept', 'quarterly', 'tds', ['needs_tds' => true], '15 days after 26Q', 'admin.accounting.tds'],

            // ---- Income tax ----
            ['ADVANCE_TAX', 'Advance tax instalments (Q1–Q4)', 'Income Tax Dept', 'quarterly', 'income_tax', [], '15 Jun / 15 Sep / 15 Dec / 15 Mar', null],
            ['ITR', 'Income-tax return', 'Income Tax Dept', 'annual', 'income_tax', [], '31 Oct (audit) / 31 Jul', null],
            ['TAX_AUDIT', 'Tax audit u/s 44AB', 'Income Tax Dept', 'annual', 'income_tax', ['turnover_min' => '5cr_10cr'], '30 Sep of next FY', null],

            // ---- ROC / entity ----
            ['ROC_AOC4', 'AOC-4 — financial statements', 'MCA / ROC', 'annual', 'roc', ['entity_type' => ['pvt_ltd', 'opc']], '30 days from AGM', null],
            ['ROC_MGT7', 'MGT-7 / MGT-7A — annual return', 'MCA / ROC', 'annual', 'roc', ['entity_type' => ['pvt_ltd', 'opc']], '60 days from AGM', null],
            ['DIR3_KYC', 'DIR-3 KYC — director KYC', 'MCA', 'annual', 'roc', ['entity_type' => ['pvt_ltd', 'opc']], '30 Sep', null],
            ['LLP_FORM8', 'LLP Form 8 — statement of account & solvency', 'MCA', 'annual', 'roc', ['entity_type' => ['llp']], '30 Oct', null],
            ['LLP_FORM11', 'LLP Form 11 — annual return', 'MCA', 'annual', 'roc', ['entity_type' => ['llp']], '30 May', null],

            // ---- Labour (only if employees) ----
            ['PF', 'EPF contribution & ECR filing', 'EPFO', 'monthly', 'labour', ['has_employees' => true], '15th of next month', null],
            ['ESIC', 'ESIC contribution', 'ESIC', 'monthly', 'labour', ['has_employees' => true], '15th of next month', null],
            ['PT', 'Professional Tax — payment & return', 'State', 'monthly', 'labour', ['has_employees' => true], 'Per state schedule', null],

            // ---- Licences ----
            ['FSSAI', 'FSSAI licence renewal', 'FSSAI', 'annual', 'licence', [], '30 days before expiry', null],
            ['SHOPS_ESTAB', 'Shops & Establishment renewal', 'State labour dept', 'annual', 'licence', [], 'Per state schedule', null],

            // ---- Cess ----
            ['GIG_CESS', 'Gig-worker welfare cess remittance', 'State welfare board', 'monthly', 'cess', ['needs_gig_cess' => true], 'Per state schedule', 'admin.accounting.cess'],
        ];

        foreach ($rows as [$code, $name, $authority, $frequency, $category, $rule, $due, $export]) {
            \Illuminate\Support\Facades\DB::table('compliance_items')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $name,
                    'authority' => $authority,
                    'frequency' => $frequency,
                    'category' => $category,
                    'applies_rule' => json_encode((object) $rule),
                    'due_rule' => $due,
                    'linked_export' => $export,
                    'is_system' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_items');
    }
};
