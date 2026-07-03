<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrinterSetting extends Model
{
    protected $table = 'printer_settings';
    
    protected $fillable = [
        'restaurant_id',
        'printer_name',
        'printer_type',
        'ip_address',
        'port',
        'usb_path',
        'bluetooth_mac',
        'paper_size',
        'is_default',
        'is_active'
    ];
    
    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'port' => 'integer',
        'paper_size' => 'integer',
    ];
    
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
    
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
    
    public function getConnectionStringAttribute(): string
    {
        switch ($this->printer_type) {
            case 'network':
                return $this->ip_address . ':' . $this->port;
            case 'bluetooth':
                return $this->bluetooth_mac ?? 'Not configured';
            case 'usb':
                return $this->usb_path ?? '/dev/usb/lp0';
            default:
                return 'Unknown';
        }
    }
    
    public function getStatusBadgeAttribute(): string
    {
        if (!$this->is_active) {
            return '<span class="badge bg-secondary">Inactive</span>';
        }
        return '<span class="badge bg-success">Active</span>';
    }
}