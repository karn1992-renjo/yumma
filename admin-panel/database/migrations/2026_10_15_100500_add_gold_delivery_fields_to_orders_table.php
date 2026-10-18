<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('gold_delivery_waived')->default(false)->after('delivery_discount');
            // What the customer's delivery fee would have been without Gold --
            // tracked for admin-subsidy accounting only. Restaurant and driver
            // earnings are computed exactly as if the order weren't on Gold;
            // this amount is never deducted from either side (see
            // OrderController::buildPricingSummary()).
            $table->decimal('gold_delivery_waiver_amount', 10, 2)->nullable()->after('gold_delivery_waived');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['gold_delivery_waived', 'gold_delivery_waiver_amount']);
        });
    }
};
