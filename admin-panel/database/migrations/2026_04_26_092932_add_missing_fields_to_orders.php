<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            // Earnings fields
            if (!Schema::hasColumn('orders', 'restaurant_earning')) {
                $table->decimal('restaurant_earning', 12, 2)->nullable()->after('total');
            }
            if (!Schema::hasColumn('orders', 'driver_earning')) {
                $table->decimal('driver_earning', 12, 2)->nullable()->after('restaurant_earning');
            }
            if (!Schema::hasColumn('orders', 'admin_commission')) {
                $table->decimal('admin_commission', 12, 2)->nullable()->after('driver_earning');
            }
            
            // Special instructions
            if (!Schema::hasColumn('orders', 'special_instructions')) {
                $table->text('special_instructions')->nullable()->after('cancellation_reason');
            }
            
            // OTP fields
            if (!Schema::hasColumn('orders', 'delivery_otp')) {
                $table->string('delivery_otp', 10)->nullable()->after('special_instructions');
            }
            if (!Schema::hasColumn('orders', 'otp_verified')) {
                $table->boolean('otp_verified')->default(false)->after('delivery_otp');
            }
            if (!Schema::hasColumn('orders', 'otp_verified_at')) {
                $table->timestamp('otp_verified_at')->nullable()->after('otp_verified');
            }
            
            // Return fields
            if (!Schema::hasColumn('orders', 'return_status')) {
                $table->string('return_status')->nullable()->after('otp_verified_at');
            }
            if (!Schema::hasColumn('orders', 'return_reason')) {
                $table->text('return_reason')->nullable()->after('return_status');
            }
            if (!Schema::hasColumn('orders', 'return_amount')) {
                $table->decimal('return_amount', 12, 2)->nullable()->after('return_reason');
            }
            if (!Schema::hasColumn('orders', 'return_processed_at')) {
                $table->timestamp('return_processed_at')->nullable()->after('return_amount');
            }
            
            // Order processing type
            if (!Schema::hasColumn('orders', 'order_processing_type')) {
                $table->string('order_processing_type')->default('after_restaurant_accept')->after('return_processed_at');
            }
            
            // Refund fields
            if (!Schema::hasColumn('orders', 'refund_status')) {
                $table->string('refund_status')->nullable()->after('order_processing_type');
            }
            if (!Schema::hasColumn('orders', 'refund_amount')) {
                $table->decimal('refund_amount', 12, 2)->nullable()->after('refund_status');
            }
            if (!Schema::hasColumn('orders', 'refund_reason')) {
                $table->text('refund_reason')->nullable()->after('refund_amount');
            }
            if (!Schema::hasColumn('orders', 'refund_processed_at')) {
                $table->timestamp('refund_processed_at')->nullable()->after('refund_reason');
            }
        });

        // Add indexes safely (check if they exist first)
        $this->addIndexIfNotExists('orders', 'status');
        $this->addIndexIfNotExists('orders', 'payment_status');
        $this->addIndexIfNotExists('orders', 'refund_status');
        $this->addIndexIfNotExists('orders', 'driver_id');
        $this->addIndexIfNotExists('orders', 'customer_id');
        $this->addIndexIfNotExists('orders', 'restaurant_id');
        $this->addIndexIfNotExists('orders', 'created_at');
        $this->addIndexIfNotExists('orders', 'order_number');
    }

    /**
     * Add index if it doesn't exist
     */
    private function addIndexIfNotExists($table, $column)
    {
        try {
            // For SQLite, check if index exists
            $driver = DB::connection()->getDriverName();
            
            if ($driver === 'sqlite') {
                $indexes = DB::select("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='{$table}'");
                $indexNames = array_column($indexes, 'name');
                $indexName = "{$table}_{$column}_index";
                
                if (!in_array($indexName, $indexNames)) {
                    Schema::table($table, function (Blueprint $table) use ($column) {
                        $table->index($column);
                    });
                }
            } else {
                // For MySQL/PostgreSQL, try-catch approach
                try {
                    Schema::table($table, function (Blueprint $table) use ($column) {
                        $table->index($column);
                    });
                } catch (\Exception $e) {
                    // Index already exists, continue
                }
            }
        } catch (\Exception $e) {
            // Index creation failed, continue
        }
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $columns = [
                'restaurant_earning', 'driver_earning', 'admin_commission',
                'special_instructions', 'delivery_otp', 'otp_verified', 'otp_verified_at',
                'return_status', 'return_reason', 'return_amount', 'return_processed_at',
                'order_processing_type', 'refund_status', 'refund_amount', 'refund_reason',
                'refund_processed_at'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};