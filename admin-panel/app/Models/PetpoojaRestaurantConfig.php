<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PetpoojaRestaurantConfig extends Model
{
    protected $fillable = [
        'restaurant_id',
        'rest_id',
        'app_key_encrypted',
        'app_secret_encrypted',
        'access_token_encrypted',
        'environment',
        'is_active',
        'is_menu_managed_by_pos',
        'webhook_shared_secret',
        'last_menu_synced_at',
        'last_menu_sync_status',
        'last_menu_sync_error',
    ];

    protected $casts = [
        'app_key_encrypted' => 'encrypted',
        'app_secret_encrypted' => 'encrypted',
        'access_token_encrypted' => 'encrypted',
        'is_active' => 'boolean',
        'is_menu_managed_by_pos' => 'boolean',
        'last_menu_synced_at' => 'datetime',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function hasCredentials(): bool
    {
        return filled($this->rest_id)
            && filled($this->app_key_encrypted)
            && filled($this->app_secret_encrypted)
            && filled($this->access_token_encrypted);
    }
}
