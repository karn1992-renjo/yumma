<?php

namespace App\Services\Tax;

use App\Models\AppSetting;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;

class TaxEntityResolver
{
    /**
     * The platform / e-commerce operator -- the Sec 9(5) GST payer, TCS
     * collector and income-tax TDS deductor.
     */
    public function ecoEntity(): TaxEntity
    {
        $get = static fn (string ...$keys) => (string) collect($keys)
            ->map(fn ($k) => AppSetting::getValue($k))
            ->first(fn ($v) => filled($v));

        return new TaxEntity(
            partyType: '',
            partyId: null,
            name: $get('business_legal_name', 'invoice_company_name', 'app_name', 'site_name') ?: config('app.name', 'Platform'),
            gstin: $get('business_gstin', 'invoice_company_tax_id') ?: null,
            pan: $get('business_pan') ?: null,
            tan: $get('business_tan') ?: null,
            state: $get('business_state') ?: null,
            stateCode: $get('business_state_code') ?: null,
            deducteeType: 'company',
        );
    }

    /**
     * The supplier of record for an order's food: the Branch when Branch
     * Management is active, otherwise the Restaurant. This is also the
     * Sec 194-O e-commerce participant.
     */
    public function supplierFor(Order $order): TaxEntity
    {
        if ($order->branch_id && ($branch = Branch::find($order->branch_id))) {
            return new TaxEntity(
                partyType: Branch::class,
                partyId: $branch->id,
                name: (string) $branch->name,
                gstin: $branch->gst_number ?: null,
                pan: $branch->pan_number ?: null,
                tan: $branch->tan ?: null,
                state: $branch->state ?: null,
                stateCode: null,
                deducteeType: 'company',
            );
        }

        $r = $order->relationLoaded('restaurant') ? $order->restaurant : Restaurant::find($order->restaurant_id);

        return new TaxEntity(
            partyType: Restaurant::class,
            partyId: $r?->id,
            name: (string) ($r?->name ?? 'Restaurant'),
            gstin: $r?->gstin ?: null,
            pan: $r?->pan ?: null,
            tan: null,
            state: $r?->state ?: null,
            stateCode: $r?->state_code ?: null,
            deducteeType: $r?->tax_deductee_type ?: 'individual',
        );
    }

    public function restaurantEntity(Restaurant $r): TaxEntity
    {
        return new TaxEntity(
            partyType: Restaurant::class,
            partyId: $r->id,
            name: (string) $r->name,
            gstin: $r->gstin ?: null,
            pan: $r->pan ?: null,
            tan: null,
            state: $r->state ?: null,
            stateCode: $r->state_code ?: null,
            deducteeType: $r->tax_deductee_type ?: 'individual',
        );
    }

    public function driverEntity(User $driver): TaxEntity
    {
        return new TaxEntity(
            partyType: User::class,
            partyId: $driver->id,
            name: (string) $driver->name,
            gstin: null,
            pan: $driver->pan ?: null,
            tan: null,
            state: null,
            stateCode: null,
            deducteeType: $driver->tax_deductee_type ?: 'individual',
        );
    }
}
