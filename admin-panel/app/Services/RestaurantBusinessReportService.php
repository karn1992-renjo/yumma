<?php

namespace App\Services;

use App\Mail\RestaurantBusinessReportMail;
use App\Models\AppSetting;
use App\Models\Order;
use App\Models\Restaurant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

class RestaurantBusinessReportService
{
    public function sendScheduled(?string $frequency = null, bool $force = false): array
    {
        if (! $this->enabled()) {
            return ['sent' => 0, 'skipped' => 0, 'message' => 'Restaurant business reports are disabled.'];
        }

        $frequency = $frequency ?: $this->frequency();

        if (! $force && ! $this->due($frequency)) {
            return ['sent' => 0, 'skipped' => 0, 'message' => 'Restaurant business report already sent for this period.'];
        }

        [$start, $end, $label] = $this->periodFor($frequency);
        $sent = 0;
        $skipped = 0;

        Restaurant::query()
            ->with('owner')
            ->where('is_verified', true)
            ->orderBy('id')
            ->chunkById(100, function ($restaurants) use (&$sent, &$skipped, $start, $end, $frequency, $label) {
                foreach ($restaurants as $restaurant) {
                    $email = $this->recipientEmail($restaurant);
                    if (! $email) {
                        $skipped++;
                        continue;
                    }

                    $report = $this->build($restaurant, $start, $end, $frequency, $label);
                    Mail::to($email)->send(new RestaurantBusinessReportMail($restaurant, $report));
                    $sent++;
                }
            });

        AppSetting::updateOrCreate(
            ['key' => 'restaurant_business_report_last_sent_at'],
            ['value' => now()->toDateTimeString(), 'type' => 'string']
        );

        AppSetting::updateOrCreate(
            ['key' => 'restaurant_business_report_last_sent_key'],
            ['value' => $this->periodKey($frequency), 'type' => 'string']
        );

        return ['sent' => $sent, 'skipped' => $skipped, 'message' => 'Restaurant business reports sent.'];
    }

    public function build(Restaurant $restaurant, CarbonImmutable $start, CarbonImmutable $end, string $frequency, string $label): array
    {
        $orders = Order::query()
            ->visibleToRestaurant()
            ->with(['orderItems.menuItem'])
            ->where('restaurant_id', $restaurant->id)
            ->whereBetween('created_at', [$start, $end])
            ->latest('created_at')
            ->get();

        $salesOrders = $orders->where('status', 'delivered');
        $totalOrders = $orders->count();
        $deliveredOrders = $salesOrders->count();
        $cancelledOrders = $orders->where('status', 'cancelled')->count();
        $grossSales = (float) $salesOrders->sum('total');
        $itemSales = (float) $salesOrders->sum('subtotal');
        $discounts = (float) $salesOrders->sum('discount');
        $tax = (float) $salesOrders->sum('tax');
        $commission = (float) $salesOrders->sum('admin_commission');
        $netEarnings = (float) $salesOrders->sum('restaurant_earning');

        if ($netEarnings <= 0) {
            $netEarnings = max(0, $grossSales - $commission);
        }

        return [
            'frequency' => $frequency,
            'period_label' => $label,
            'period_start' => $start,
            'period_end' => $end,
            'total_orders' => $totalOrders,
            'delivered_orders' => $deliveredOrders,
            'cancelled_orders' => $cancelledOrders,
            'gross_sales' => $grossSales,
            'item_sales' => $itemSales,
            'discounts' => $discounts,
            'tax' => $tax,
            'commission' => $commission,
            'net_earnings' => $netEarnings,
            'average_order_value' => $deliveredOrders > 0 ? $grossSales / $deliveredOrders : 0,
            'top_orders' => $salesOrders
                ->sortByDesc(fn ($order) => (float) $order->total)
                ->take(5)
                ->values(),
            'orders' => $orders->values(),
        ];
    }

    public function enabled(): bool
    {
        return filter_var(AppSetting::getValue('restaurant_business_report_enabled', '0'), FILTER_VALIDATE_BOOLEAN);
    }

    public function frequency(): string
    {
        $frequency = (string) AppSetting::getValue('restaurant_business_report_frequency', 'weekly');

        return in_array($frequency, ['daily', 'weekly', 'monthly'], true) ? $frequency : 'weekly';
    }

    public function periodFor(string $frequency): array
    {
        $now = CarbonImmutable::now();

        return match ($frequency) {
            'daily' => [
                $now->subDay()->startOfDay(),
                $now->subDay()->endOfDay(),
                $now->subDay()->format('d M Y'),
            ],
            'monthly' => [
                $now->subMonthNoOverflow()->startOfMonth(),
                $now->subMonthNoOverflow()->endOfMonth(),
                $now->subMonthNoOverflow()->format('F Y'),
            ],
            default => [
                $now->subWeek()->startOfWeek(),
                $now->subWeek()->endOfWeek(),
                $now->subWeek()->startOfWeek()->format('d M') . ' - ' . $now->subWeek()->endOfWeek()->format('d M Y'),
            ],
        };
    }

    private function recipientEmail(Restaurant $restaurant): ?string
    {
        return $restaurant->owner?->email ?: $restaurant->email;
    }

    private function due(string $frequency): bool
    {
        return AppSetting::getValue('restaurant_business_report_last_sent_key') !== $this->periodKey($frequency);
    }

    private function periodKey(string $frequency): string
    {
        [$start] = $this->periodFor($frequency);

        return $frequency . ':' . $start->toDateString();
    }
}