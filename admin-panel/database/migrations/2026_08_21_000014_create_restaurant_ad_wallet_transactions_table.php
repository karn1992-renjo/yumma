<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('restaurant_ad_wallet_transactions')) {
            Schema::create('restaurant_ad_wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_ad_wallet_id')
                    ->constrained('restaurant_ad_wallets', 'id', 'ad_wallet_transactions_wallet_fk')
                    ->cascadeOnDelete();
                $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
                $table->string('type', 32);
                $table->decimal('amount', 12, 2);
                $table->decimal('balance_after', 12, 2);
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('description')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['restaurant_id', 'created_at']);
                $table->index(['reference_type', 'reference_id'], 'ad_wallet_tx_reference_index');
            });

            return;
        }

        // Table already exists from an earlier partial run of this migration
        // (e.g. it originally failed on the "reference_type_reference_id"
        // index for exceeding MySQL's 64-char identifier limit, after the
        // CREATE TABLE + first index had already committed). Re-running
        // `migrate` must be able to finish the job from wherever it stopped,
        // not just re-check the one thing this branch used to check.
        $hasWalletForeignKey = collect(Schema::getForeignKeys('restaurant_ad_wallet_transactions'))
            ->contains(fn (array $foreignKey) => in_array('restaurant_ad_wallet_id', $foreignKey['columns'] ?? [], true));

        if (! $hasWalletForeignKey) {
            Schema::table('restaurant_ad_wallet_transactions', function (Blueprint $table) {
                $table->foreign('restaurant_ad_wallet_id', 'ad_wallet_transactions_wallet_fk')
                    ->references('id')
                    ->on('restaurant_ad_wallets')
                    ->cascadeOnDelete();
            });
        }

        $hasReferenceIndex = collect(Schema::getIndexes('restaurant_ad_wallet_transactions'))
            ->contains(fn (array $index) => $index['name'] === 'ad_wallet_tx_reference_index');

        if (! $hasReferenceIndex) {
            Schema::table('restaurant_ad_wallet_transactions', function (Blueprint $table) {
                $table->index(['reference_type', 'reference_id'], 'ad_wallet_tx_reference_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_ad_wallet_transactions');
    }
};
