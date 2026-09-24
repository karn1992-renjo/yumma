<?php

namespace App\Support;

/**
 * US state names/abbreviations for the sales-tax settings page — reference
 * data only. The five states with NO state-level sales tax (AK, DE, MT, NH,
 * OR) are included with a 0 suggested rate so the admin sees them listed
 * rather than silently missing.
 */
class UsStates
{
    public const STATES = [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
        'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
        'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho',
        'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
        'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
        'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
        'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
        'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
        'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
        'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
        'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
        'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
        'WI' => 'Wisconsin', 'WY' => 'Wyoming', 'DC' => 'District of Columbia',
    ];

    /** State-level base rate suggestions -- see UsSalesTaxStrategy docblock: state-only, no local surtax. */
    public const SUGGESTED_RATES = [
        'AL' => 4.0, 'AK' => 0.0, 'AZ' => 5.6, 'AR' => 6.5, 'CA' => 7.25, 'CO' => 2.9,
        'CT' => 6.35, 'DE' => 0.0, 'FL' => 6.0, 'GA' => 4.0, 'HI' => 4.0, 'ID' => 6.0,
        'IL' => 6.25, 'IN' => 7.0, 'IA' => 6.0, 'KS' => 6.5, 'KY' => 6.0, 'LA' => 4.45,
        'ME' => 5.5, 'MD' => 6.0, 'MA' => 6.25, 'MI' => 6.0, 'MN' => 6.875, 'MS' => 7.0,
        'MO' => 4.225, 'MT' => 0.0, 'NE' => 5.5, 'NV' => 6.85, 'NH' => 0.0, 'NJ' => 6.625,
        'NM' => 4.875, 'NY' => 4.0, 'NC' => 4.75, 'ND' => 5.0, 'OH' => 5.75, 'OK' => 4.5,
        'OR' => 0.0, 'PA' => 6.0, 'RI' => 7.0, 'SC' => 6.0, 'SD' => 4.2, 'TN' => 7.0,
        'TX' => 6.25, 'UT' => 4.85, 'VT' => 6.0, 'VA' => 4.3, 'WA' => 6.5, 'WV' => 6.0,
        'WI' => 5.0, 'WY' => 4.0, 'DC' => 6.0,
    ];
}
