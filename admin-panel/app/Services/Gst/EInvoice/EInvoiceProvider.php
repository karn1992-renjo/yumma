<?php

namespace App\Services\Gst\EInvoice;

use App\Models\Order;

interface EInvoiceProvider
{
    /**
     * Register the order with the IRP and return the IRN / signed QR.
     * Implementations must not throw for expected failures -- return
     * EInvoiceResult::failed() instead.
     */
    public function generate(Order $order): EInvoiceResult;
}
