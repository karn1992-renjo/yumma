<?php

namespace App\Services\Tax;

use App\Models\Order;

/**
 * GST TCS (Sec 52). The ECO collects 0.5% (0.25% CGST + 0.25% SGST) on the
 * net taxable value of supplies made through it by a registered restaurant
 * *where the restaurant, not the platform, is liable for GST* — i.e. NOT the
 * Sec 9(5) food value. Under pure 9(5) + food-only menus this base is ~0.
 */
class TcsService
{
    public function __construct(private readonly TaxConfig $config = new TaxConfig())
    {
    }

    /**
     * @param  iterable<Order>  $orders  the orders in this restaurant settlement
     */
    public function forRestaurant(TaxEntity $r, iterable $orders): TaxDeduction
    {
        if (! $this->config->tcsEnabled() || ! $r->gstin) {
            return TaxDeduction::none('52');
        }

        $base = 0.0;
        foreach ($orders as $order) {
            $bd = is_array($order->tax_breakdown) ? $order->tax_breakdown : [];
            foreach (($bd['lines'] ?? []) as $line) {
                if (($line['liability'] ?? 'restaurant') === 'restaurant') {
                    $base += (float) ($line['taxable_value'] ?? 0);
                }
            }
        }

        $base = round($base, 2);
        if ($base <= 0) {
            return TaxDeduction::none('52', 'no restaurant-liable supply');
        }

        $rate = $this->config->tcsRate();
        $amount = round($base * $rate / 100, 2);

        return new TaxDeduction($amount, $rate, '52', $base, 0.0);
    }
}
