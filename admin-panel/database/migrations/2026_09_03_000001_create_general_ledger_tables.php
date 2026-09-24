<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chart_of_accounts')) {
            Schema::create('chart_of_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('code', 12)->unique();
                $table->string('name');
                $table->string('type', 12);      // asset|liability|equity|income|expense
                $table->string('subtype', 32)->nullable();
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index('type');
            });
        }

        if (! Schema::hasTable('journal_entries')) {
            Schema::create('journal_entries', function (Blueprint $table) {
                $table->id();
                $table->string('entry_no', 32)->nullable()->index();
                $table->date('date')->index();
                $table->string('narration')->nullable();
                $table->string('source_type', 40)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('kind', 32)->nullable();   // posting-hook tag, for idempotency
                $table->string('fy', 9)->index();
                $table->string('period', 7)->index();     // YYYY-MM
                $table->string('status', 10)->default('posted'); // posted|void
                $table->boolean('is_manual')->default(false);
                $table->unsignedBigInteger('posted_by')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['source_type', 'source_id', 'kind'], 'journal_source_kind_unique');
            });
        }

        if (! Schema::hasTable('journal_lines')) {
            Schema::create('journal_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
                $table->foreignId('account_id')->constrained('chart_of_accounts');
                $table->decimal('debit', 16, 2)->default(0);
                $table->decimal('credit', 16, 2)->default(0);
                $table->string('party_type', 40)->nullable();
                $table->unsignedBigInteger('party_id')->nullable();
                $table->string('memo')->nullable();
                $table->timestamps();

                $table->index(['account_id']);
                $table->index(['party_type', 'party_id']);
            });
        }

        if (! Schema::hasTable('accounting_periods')) {
            Schema::create('accounting_periods', function (Blueprint $table) {
                $table->id();
                $table->string('fy', 9);
                $table->string('period', 7);
                $table->string('status', 8)->default('open'); // open|closed
                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('closed_by')->nullable();
                $table->timestamps();

                $table->unique(['fy', 'period']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('chart_of_accounts');
    }
};
