<?php

namespace App\Services\Tax\Strategies\Concerns;

/**
 * Shared by every non-India strategy (UK/GCC/EU/AU-SG/US/Generic) since they
 * all persist the same `rate_summary: [{rate, taxable_value, tax}]` shape in
 * their `detailed` breakdown — only the tax name, registration label/key,
 * and total-tax key genuinely differ per strategy.
 */
trait BuildsGenericInvoiceSummary
{
    protected function genericInvoiceSummary(
        array $detailed,
        string $taxName,
        string $registrationLabel,
        ?string $registrationKey,
        string $totalKey,
        array $notes = [],
    ): array {
        $lines = collect($detailed['rate_summary'] ?? [])->map(fn ($row) => [
            'rate' => (float) ($row['rate'] ?? 0),
            'taxable_value' => (float) ($row['taxable_value'] ?? 0),
            'tax' => (float) ($row['tax'] ?? 0),
        ])->all();

        return [
            'tax_name' => $taxName,
            'registration_label' => $registrationLabel,
            'registration_number' => $registrationKey ? ($detailed[$registrationKey] ?? null) : null,
            'lines' => $lines,
            'total_tax' => (float) ($detailed[$totalKey] ?? 0),
            'inclusive' => (bool) ($detailed['prices_inclusive'] ?? false),
            'notes' => $notes,
        ];
    }
}
