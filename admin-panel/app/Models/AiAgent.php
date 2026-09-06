<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiAgent extends Model
{
    protected $fillable = ['key', 'name', 'description', 'is_enabled', 'default_model', 'max_auto_risk', 'config'];

    protected $casts = [
        'is_enabled' => 'boolean',
        'config' => 'array',
    ];

    public function tools(): HasMany
    {
        return $this->hasMany(AiAgentTool::class);
    }
}
