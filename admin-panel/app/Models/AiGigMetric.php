<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiGigMetric extends Model
{
    protected $fillable = ['ai_decision_id', 'subject_id', 'subject_type', 'budget', 'actual_spend', 'incremental_contribution', 'roi', 'metrics', 'status'];

    protected $casts = ['metrics' => 'array', 'budget' => 'decimal:2', 'actual_spend' => 'decimal:2', 'incremental_contribution' => 'decimal:2', 'roi' => 'decimal:4'];
}
