<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchZone extends Model
{
    protected $fillable = ['branch_id', 'delivery_area_id', 'name', 'country', 'state', 'city', 'area', 'pincode', 'polygon', 'is_active'];

    protected $casts = [
        'polygon' => 'array',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function deliveryArea(): BelongsTo
    {
        return $this->belongsTo(DeliveryArea::class);
    }
}
