<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    protected $fillable = [
        'entry_no', 'date', 'narration', 'source_type', 'source_id', 'kind',
        'fy', 'period', 'status', 'is_manual', 'posted_by', 'meta',
    ];

    protected $casts = ['date' => 'date', 'is_manual' => 'boolean', 'meta' => 'array'];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function isBalanced(): bool
    {
        return round((float) $this->lines->sum('debit') - (float) $this->lines->sum('credit'), 2) === 0.0;
    }
}
