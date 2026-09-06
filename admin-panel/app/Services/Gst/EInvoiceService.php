<?php

namespace App\Services\Gst;

use App\Models\AppSetting;
use App\Models\Order;
use App\Services\Gst\EInvoice\EInvoiceProvider;
use App\Services\Gst\EInvoice\EInvoiceResult;
use App\Services\Gst\EInvoice\GspEInvoiceProvider;
use App\Services\Gst\EInvoice\ManualEInvoiceProvider;

class EInvoiceService
{
    public function enabled(): bool
    {
        return (string) AppSetting::getValue('einvoice_enabled', '0') === '1';
    }

    public function providerKey(): string
    {
        return (string) (AppSetting::getValue('einvoice_provider') ?: 'manual');
    }

    public function provider(): EInvoiceProvider
    {
        return $this->providerKey() === 'gsp'
            ? app(GspEInvoiceProvider::class)
            : app(ManualEInvoiceProvider::class);
    }

    /**
     * Automated path -- calls the configured GSP provider.
     */
    public function generate(Order $order): EInvoiceResult
    {
        $result = $this->provider()->generate($order);
        $this->apply($order, $result);

        return $result;
    }

    /**
     * Manual path -- admin pasted the values from the government portal.
     */
    public function manual(Order $order, string $irn, ?string $signedQr = null, ?string $ackNo = null, ?string $ackDate = null): EInvoiceResult
    {
        $irn = trim($irn);
        if ($irn === '') {
            $result = EInvoiceResult::failed('IRN is required.');
        } else {
            $result = EInvoiceResult::generated($irn, $signedQr ? trim($signedQr) : null, $ackNo ?: null, $ackDate ?: null);
        }

        $this->apply($order, $result);

        return $result;
    }

    public function clear(Order $order): void
    {
        $order->forceFill([
            'einvoice_status' => 'cancelled',
            'einvoice_irn' => null,
            'einvoice_qr' => null,
            'einvoice_ack_no' => null,
            'einvoice_acked_at' => null,
        ])->saveQuietly();
    }

    private function apply(Order $order, EInvoiceResult $result): void
    {
        if ($result->status !== 'generated') {
            $order->forceFill(['einvoice_status' => 'failed'])->saveQuietly();

            return;
        }

        $order->forceFill([
            'einvoice_status' => 'generated',
            'einvoice_irn' => $result->irn,
            'einvoice_qr' => $result->signedQr,
            'einvoice_ack_no' => $result->ackNo,
            'einvoice_acked_at' => $result->ackDate ? \Illuminate\Support\Carbon::parse($result->ackDate) : now(),
        ])->saveQuietly();
    }
}
