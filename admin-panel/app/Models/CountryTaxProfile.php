<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CountryTaxProfile extends Model
{
    protected $fillable = [
        'country_code',
        'country_name',
        'currency_code',
        'tax_name',
        'is_active',
        'config',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'array',
    ];

    public function configValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }
}
