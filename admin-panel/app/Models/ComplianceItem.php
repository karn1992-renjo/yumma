<?php

namespace App\Models;

use App\Services\Tax\TaxConfig;
use Illuminate\Database\Eloquent\Model;

/**
 * One statutory obligation. The seeded set is filtered at read time against the
 * business's entity type / employee / GST / TDS / cess profile so the admin only
 * ever sees what actually applies.
 */
class ComplianceItem extends Model
{
    protected $fillable = [
        'code', 'name', 'authority', 'frequency', 'category', 'applies_rule',
        'due_rule', 'status', 'period', 'due_date', 'filed_on', 'reference_no',
        'attachment', 'linked_export', 'notes', 'is_system',
    ];

    protected $casts = [
        'applies_rule' => 'array',
        'due_date' => 'date',
        'filed_on' => 'date',
        'is_system' => 'boolean',
    ];

    /** Does this item apply to the current business profile? */
    public function appliesToBusiness(TaxConfig $config): bool
    {
        $rule = $this->applies_rule ?: [];

        if (! empty($rule['entity_type']) && ! in_array($config->entityType(), (array) $rule['entity_type'], true)) {
            return false;
        }
        if (! empty($rule['has_employees']) && ! $config->hasEmployees()) {
            return false;
        }
        if (! empty($rule['needs_gst']) && ! $config->gstRegistered()) {
            return false;
        }
        if (! empty($rule['needs_tds']) && ! $config->tdsRegistered()) {
            return false;
        }
        if (! empty($rule['needs_tcs']) && ! $config->tcsRegistered()) {
            return false;
        }
        if (! empty($rule['needs_gig_cess']) && ! $config->gigCessEnabled()) {
            return false;
        }
        if (! empty($rule['turnover_min'])) {
            $bands = ['below_1cr' => 0, '1cr_5cr' => 1, '5cr_10cr' => 2, 'above_10cr' => 3];
            $have = $bands[(string) \App\Models\AppSetting::getValue('business_turnover_band', 'below_1cr')] ?? 0;
            $need = $bands[(string) $rule['turnover_min']] ?? 0;
            if ($have < $need) {
                return false;
            }
        }

        return true;
    }
}
