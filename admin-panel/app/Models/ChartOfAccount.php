<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    protected $fillable = ['code', 'name', 'type', 'subtype', 'parent_id', 'is_system', 'is_active', 'meta'];

    protected $casts = ['is_system' => 'boolean', 'is_active' => 'boolean', 'meta' => 'array'];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'account_id');
    }

    /** Debit-positive account? (assets & expenses increase on the debit side.) */
    public function isDebitNormal(): bool
    {
        return in_array($this->type, ['asset', 'expense'], true);
    }

    public static function byCode(string $code): ?self
    {
        return static::query()->where('code', $code)->first();
    }
}
