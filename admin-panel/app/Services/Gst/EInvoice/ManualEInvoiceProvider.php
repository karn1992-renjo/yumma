<?php

namespace App\Services\Gst\EInvoice;

use App\Models\Order;

/**
 * No API. The admin pastes the IRN + signed QR from the government e-invoice
 * portal; {@see EInvoiceService::manual()} feeds those values straight into
 * an EInvoiceResult, so "generate" is not applicable here.
 */
class ManualEInvoiceProvider implements EInvoiceProvider
{
    public function generate(Order $order): EInvoiceResult
    {
        return EInvoiceResult::failed('Manual e-invoicing: paste the IRN and signed QR from the government portal.');
    }
}
