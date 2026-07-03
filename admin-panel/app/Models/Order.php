<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;
use App\Models\AppSetting;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Services\BranchManagementService;

class Order extends Model
{
    protected $table = 'orders';
    
    protected $fillable = [
        'order_number',
        'customer_id',
        'restaurant_id',
        'branch_id',
        'driver_id',
        'order_type',
        'items',
        'subtotal',
        'delivery_fee',
        'platform_fee',
        'tax',
        'discount',
        'total',
        'payment_method',
        'delivery_payment_mode',
        'cod_reconciliation_status',
        'payment_status',
        'payment_id',
        'cash_collected_amount',
        'cash_collected_at',
        'cod_deposited_at',
        'online_payment_verified_at',
        'status',
        'customer_address',
        'customer_phone',
        'customer_name',
        'delivery_address',
        'delivery_lat',
        'delivery_lng',
        'scheduled_time',
        'confirmed_at',
        'preparation_time_minutes',
        'preparing_at',
        'ready_at',
        'reached_at',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',
        'special_instructions',
        'restaurant_earning',
        'driver_earning',
        'driver_delivery_base',
        'admin_commission',
        'admin_delivery_commission',
        'admin_delivery_commission_type',
        'admin_delivery_commission_value',
        'driver_deduction',
        'driver_deduction_type',
        'driver_deduction_value',
        'batch_bonus',
        'payment_gateway_fee',
        'gst_on_commission',
        'platform_commission',
        'restaurant_commission_type',
        'restaurant_commission_value',
        'branch_commission',
        'branch_commission_settled',
        'payout_processed',
        'payout_processed_at',
        'payout_status',
        'payout_released_at',
        'restaurant_payout_id',
        'driver_payout_id',
        'delivery_otp',
        'otp_verified',
        'otp_verified_at',
        'return_status',
        'return_reason',
        'return_amount',
        'return_processed_at',
        'order_processing_type',
        'refund_status',
        'refund_amount',
        'refund_reason',
        'refund_processed_at',
        'restaurant_rating',
        'driver_rating',
        'item_rating',
        'service_rating',
        'restaurant_feedback',
        'driver_feedback',
        'item_feedback',
        'service_feedback',
        'feedback_submitted_at',
        'driver_assignment_attempts',
        'rejected_driver_ids',
        'driver_assigned_at',
        'driver_accepted_at',
        'route_batch_id'
    ];
    
    protected $casts = [
        'items' => 'array',
        'customer_address' => 'array',
        'scheduled_time' => 'datetime',
        'confirmed_at' => 'datetime',
        'preparation_time_minutes' => 'integer',
        'preparing_at' => 'datetime',
        'ready_at' => 'datetime',
        'reached_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'cash_collected_at' => 'datetime',
        'cod_deposited_at' => 'datetime',
        'online_payment_verified_at' => 'datetime',
        'delivery_lat' => 'decimal:8',
        'delivery_lng' => 'decimal:8',
        'otp_verified' => 'boolean',
        'otp_verified_at' => 'datetime',
        'return_processed_at' => 'datetime',
        'refund_processed_at' => 'datetime',
        'feedback_submitted_at' => 'datetime',
        'restaurant_rating' => 'integer',
        'driver_rating' => 'integer',
        'item_rating' => 'integer',
        'service_rating' => 'integer',
        'subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'restaurant_earning' => 'decimal:2',
        'driver_earning' => 'decimal:2',
        'driver_delivery_base' => 'decimal:2',
        'admin_commission' => 'decimal:2',
        'admin_delivery_commission' => 'decimal:2',
        'admin_delivery_commission_value' => 'decimal:2',
        'driver_deduction' => 'decimal:2',
        'driver_deduction_value' => 'decimal:2',
        'batch_bonus' => 'decimal:2',
        'payment_gateway_fee' => 'decimal:2',
        'gst_on_commission' => 'decimal:2',
        'platform_commission' => 'decimal:2',
        'restaurant_commission_value' => 'decimal:2',
        'branch_commission' => 'decimal:2',
        'branch_commission_settled' => 'boolean',
        'payout_processed' => 'boolean',
        'payout_processed_at' => 'datetime',
        'payout_released_at' => 'datetime',
        'cash_collected_amount' => 'decimal:2',
        'return_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'driver_assignment_attempts' => 'integer',
        'rejected_driver_ids' => 'array',
        'driver_assigned_at' => 'datetime',
        'driver_accepted_at' => 'datetime'
    ];
    
    /**
     * Boot the model
     */
    public static function boot()
    {
        parent::boot();
        
        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber();
            }

            if (empty($order->branch_id) && $order->restaurant_id) {
                $restaurant = Restaurant::find($order->restaurant_id);
                $branch = $restaurant ? app(BranchManagementService::class)->resolveBranchForRestaurant($restaurant) : null;

                if ($branch) {
                    $order->branch_id = $branch->id;
                    app(BranchManagementService::class)->calculateOrderCommission($order, $branch);
                }
            }
        });

        static::updated(function ($order) {
            if ($order->wasChanged('status') && $order->status === 'delivered') {
                app(BranchManagementService::class)->stampOrder($order);
                app(\App\Services\PayoutCalculationService::class)->processOrderEarnings($order->fresh());
                app(BranchManagementService::class)->creditCompletedOrder($order->fresh());
            }
        });
    }
    
    /**
     * Generate unique order number
     */
    public static function generateOrderNumber()
    {
        $prefix = 'ORD';
        $date = now()->format('Ymd');
        $random = random_int(1000, 9999);
        $orderNumber = $prefix . $date . $random;
        
        while (self::where('order_number', $orderNumber)->exists()) {
            $random = random_int(1000, 9999);
            $orderNumber = $prefix . $date . $random;
        }
        
        return $orderNumber;
    }
    
    /**
     * Relationships
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function setCustomerPhoneAttribute($value): void
    {
        $this->attributes['customer_phone'] = PhoneNumber::normalize(
            $value,
            AppSetting::getValue('default_mobile_country_code', '+91')
        );
    }
    
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function restaurantPayout(): BelongsTo
    {
        return $this->belongsTo(Payout::class, 'restaurant_payout_id');
    }

    public function driverPayout(): BelongsTo
    {
        return $this->belongsTo(Payout::class, 'driver_payout_id');
    }
    
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
    
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
    
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(OrderChatMessage::class);
    }
    
    public function refundPolicy(): BelongsTo
    {
        return $this->belongsTo(RefundPolicy::class);
    }
    
    /**
     * Get all order statuses
     */
    public static function getStatuses(): array
    {
        return [
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'preparing' => 'Preparing',
            'ready_for_pickup' => 'Ready for Pickup',
            'reached_pickup' => 'Reached Pickup',
            'picked_up' => 'Picked Up',
            'on_the_way' => 'On The Way',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled'
        ];
    }
    
    /**
     * Get payment statuses
     */
    public static function getPaymentStatuses(): array
    {
        return [
            'pending' => 'Pending',
            'success' => 'Success',
            'failed' => 'Failed',
            'refunded' => 'Refunded'
        ];
    }
    
    /**
     * Get refund statuses
     */
    public static function getRefundStatuses(): array
    {
        return [
            'pending' => 'Pending',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'rejected' => 'Rejected'
        ];
    }
    
    /**
     * Get payment methods
     */
    public static function getPaymentMethods(): array
    {
        return [
            'cod' => 'Cash on Delivery',
            'card' => 'Credit/Debit Card',
            'upi' => 'UPI',
            'wallet' => 'Wallet',
            'razorpay' => 'Razorpay',
            'stripe' => 'Stripe',
            'cashfree' => 'Cashfree'
        ];
    }
    
    /**
     * Generate delivery OTP
     */
    public function generateDeliveryOtp(): int
    {
        $this->delivery_otp = random_int(1000, 9999);
        $this->save();
        return $this->delivery_otp;
    }
    
    /**
     * Verify delivery OTP
     */
    public function verifyDeliveryOtp($otp): bool
    {
        if ($this->delivery_otp == $otp && !$this->otp_verified) {
            $this->otp_verified = true;
            $this->otp_verified_at = now();
            $this->save();
            return true;
        }
        return false;
    }
    
    /**
     * Request return for order
     */
    public function requestReturn(string $reason): void
    {
        $this->return_status = 'requested';
        $this->return_reason = $reason;
        $this->save();
    }
    
    /**
     * Process return
     */
    public function processReturn(float $amount): void
    {
        $this->return_status = 'completed';
        $this->return_amount = $amount;
        $this->return_processed_at = now();
        $this->save();
    }
    
    /**
     * Request refund
     */
    public function requestRefund(string $reason): void
    {
        $this->refund_status = 'pending';
        $this->refund_reason = $reason;
        $this->save();
    }
    
    /**
     * Process refund
     */
    public function processRefund(float $amount): void
    {
        $this->refund_status = 'completed';
        $this->refund_amount = $amount;
        $this->refund_processed_at = now();
        $this->payment_status = 'refunded';
        $this->save();
    }
    
    /**
     * Reject refund
     */
    public function rejectRefund(string $reason): void
    {
        $this->refund_status = 'rejected';
        $this->refund_reason = $reason;
        $this->save();
    }
    
    /**
     * Check if order can be cancelled
     */
    public function isCancellable(): bool
    {
        return $this->status === 'pending'
            && $this->created_at
            && $this->created_at->gte(now()->subMinutes(2));
    }
    
    /**
     * Check if order can be refunded
     */
    public function isRefundable(): bool
    {
        return $this->payment_status === 'success' && 
               $this->status !== 'cancelled' && 
               is_null($this->refund_status);
    }
    
    /**
     * Check if order can be returned
     */
    public function isReturnable(): bool
    {
        return $this->status === 'delivered' && 
               is_null($this->return_status) && 
               $this->delivered_at->diffInDays(now()) <= 7;
    }
    
    /**
     * Get status badge HTML
     */
    public function getStatusBadgeAttribute(): string
    {
        $badges = [
            'pending' => '<span class="badge bg-warning">Pending</span>',
            'confirmed' => '<span class="badge bg-info">Confirmed</span>',
            'preparing' => '<span class="badge bg-primary">Preparing</span>',
            'ready_for_pickup' => '<span class="badge bg-secondary">Ready for Pickup</span>',
            'picked_up' => '<span class="badge bg-dark">Picked Up</span>',
            'on_the_way' => '<span class="badge bg-info">On The Way</span>',
            'delivered' => '<span class="badge bg-success">Delivered</span>',
            'cancelled' => '<span class="badge bg-danger">Cancelled</span>'
        ];
        
        return $badges[$this->status] ?? '<span class="badge bg-secondary">' . $this->status . '</span>';
    }
    
    /**
     * Get payment status badge
     */
    public function getPaymentStatusBadgeAttribute(): string
    {
        $badges = [
            'pending' => '<span class="badge bg-warning">Pending</span>',
            'success' => '<span class="badge bg-success">Success</span>',
            'failed' => '<span class="badge bg-danger">Failed</span>',
            'refunded' => '<span class="badge bg-info">Refunded</span>'
        ];
        
        return $badges[$this->payment_status] ?? '<span class="badge bg-secondary">' . $this->payment_status . '</span>';
    }
    
    /**
     * Get refund status badge
     */
    public function getRefundStatusBadgeAttribute(): string
    {
        if (!$this->refund_status) {
            return '<span class="badge bg-secondary">N/A</span>';
        }
        
        $badges = [
            'pending' => '<span class="badge bg-warning">Pending</span>',
            'processing' => '<span class="badge bg-info">Processing</span>',
            'completed' => '<span class="badge bg-success">Completed</span>',
            'rejected' => '<span class="badge bg-danger">Rejected</span>'
        ];
        
        return $badges[$this->refund_status] ?? '<span class="badge bg-secondary">' . $this->refund_status . '</span>';
    }
    
    /**
     * Get items count
     */
    public function getItemsCountAttribute(): int
    {
        $items = is_array($this->items) ? $this->items : json_decode($this->items, true);
        return count($items ?? []);
    }
    
    /**
     * Get order total formatted
     */
    public function getTotalFormattedAttribute(): string
    {
        return AppSetting::getValue('currency_symbol', '₹') . number_format($this->total, AppSetting::currencyDecimals());
    }

    /**
     * Get delivery address full
     */
    public function getDeliveryAddressFullAttribute(): string
    {
        return $this->delivery_address ?? 'Address not provided';
    }
    
    /**
     * Scope for pending orders
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
    
    /**
     * Scope for confirmed orders
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }
    
    /**
     * Scope for preparing orders
     */
    public function scopePreparing($query)
    {
        return $query->where('status', 'preparing');
    }
    
    /**
     * Scope for delivered orders
     */
    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }
    
    /**
     * Scope for cancelled orders
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }
    
    /**
     * Scope for today's orders
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }
    
    /**
     * Scope for orders in date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
    
    /**
     * Scope for orders by restaurant
     */
    public function scopeByRestaurant($query, $restaurantId)
    {
        return $query->where('restaurant_id', $restaurantId);
    }
    
    /**
     * Scope for orders by driver
     */
    public function scopeByDriver($query, $driverId)
    {
        return $query->where('driver_id', $driverId);
    }
    
    /**
     * Scope for orders by customer
     */
    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }
    
    /**
     * Scope for paid orders
     */
    public function scopePaid($query)
    {
        return $query->where('payment_status', 'success');
    }

    public function isVisibleToRestaurant(): bool
    {
        if ($this->payment_status === 'success') {
            return true;
        }

        $method = strtolower((string) ($this->delivery_payment_mode ?: $this->payment_method));

        return in_array($method, ['cod', 'cash', 'cash_on_delivery'], true);
    }

    public function scopeVisibleToRestaurant($query)
    {
        return $query->where(function ($builder) {
            $builder->where('payment_status', 'success')
                ->orWhereIn('payment_method', ['cod', 'cash', 'cash_on_delivery'])
                ->orWhereIn('delivery_payment_mode', ['cod', 'cash', 'cash_on_delivery']);
        });
    }
    
    /**
     * Get earnings for restaurant
     */
    public function getRestaurantEarningsAttribute()
    {
        if ($this->restaurant_earning) {
            return $this->restaurant_earning;
        }
        
        return app(\App\Services\PayoutCalculationService::class)
            ->calculateRestaurantEarning($this)['restaurant_earning'];
    }
    
    /**
     * Get earnings for driver
     */
    public function getDriverEarningsAttribute()
    {
        if ($this->driver_earning) {
            return $this->driver_earning;
        }
        
        return app(\App\Services\PayoutCalculationService::class)
            ->calculateDriverEarning($this)['driver_earning'];
    }
}
