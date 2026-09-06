<?php

namespace App\Services\Tax;

/**
 * A resolved tax party (platform / branch / restaurant / driver) with just
 * the fields the compliance engine needs.
 */
class TaxEntity
{
    public function __construct(
        public readonly string $partyType,   // '' for the platform, else FQCN
        public readonly ?int $partyId,
        public readonly string $name,
        public readonly ?string $gstin,
        public readonly ?string $pan,
        public readonly ?string $tan,
        public readonly ?string $state,
        public readonly ?string $stateCode,
        public readonly string $deducteeType = 'individual', // individual | company
    ) {
    }

    public function isPlatform(): bool
    {
        return $this->partyType === '';
    }

    public function hasPan(): bool
    {
        return $this->pan !== null && strlen(trim($this->pan)) >= 10;
    }
}
