<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Running per-financial-year totals per deductee (restaurant / driver),
 * used for the Sec 194-O / 194-C thresholds and no-PAN rate logic.
 */
class TdsDeducteeTotal extends Model
{
    protected $fillable = [
        'party_type', 'party_id', 'pan', 'fy', 'section', 'gross_ytd', 'tds_ytd',
    ];

    protected $casts = [
        'gross_ytd' => 'decimal:2',
        'tds_ytd' => 'decimal:2',
    ];
}
