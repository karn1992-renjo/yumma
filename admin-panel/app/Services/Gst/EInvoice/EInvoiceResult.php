<?php

namespace App\Services\Gst\EInvoice;

class EInvoiceResult
{
    public function __construct(
        public readonly string $status,   // generated | failed
        public readonly ?string $irn = null,
        public readonly ?string $signedQr = null,
        public readonly ?string $ackNo = null,
        public readonly ?string $ackDate = null,
        public readonly ?string $error = null,
    ) {
    }

    public static function generated(string $irn, ?string $qr = null, ?string $ackNo = null, ?string $ackDate = null): self
    {
        return new self('generated', $irn, $qr, $ackNo, $ackDate);
    }

    public static function failed(string $error): self
    {
        return new self('failed', error: $error);
    }
}
