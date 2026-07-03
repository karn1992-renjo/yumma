<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutHistory extends Model
{
    protected $table = 'payout_histories';
    
    protected $fillable = [
        'payable_type', 'payable_id', 'amount', 'period_type',
        'period_start', 'period_end', 'status', 'transaction_id',
        'breakdown', 'processed_at'
    ];
    
    protected $casts = [
        'amount' => 'decimal:2',
        'breakdown' => 'array',
        'period_start' => 'date',
        'period_end' => 'date',
        'processed_at' => 'datetime',
    ];
    
    public function payable()
    {
        return $this->morphTo();
    }
    
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
    
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
    
    public function scopeByPeriod($query, $type, $start, $end)
    {
        return $query->where('period_type', $type)
            ->where('period_start', '>=', $start)
            ->where('period_end', '<=', $end);
    }
    
    public function getPayableNameAttribute()
    {
        if ($this->payable_type === Restaurant::class && $this->payable) {
            return $this->payable->name;
        }
        if ($this->payable_type === User::class && $this->payable) {
            return $this->payable->name;
        }
        return 'N/A';
    }
    
    public function getTypeAttribute()
    {
        if ($this->payable_type === Restaurant::class) {
            return 'restaurant';
        }
        if ($this->payable_type === User::class) {
            return 'driver';
        }
        return 'unknown';
    }
    
    public function getStatusBadgeAttribute()
    {
        $badges = [
            'pending' => '<span class="badge bg-warning">Pending</span>',
            'processing' => '<span class="badge bg-info">Processing</span>',
            'completed' => '<span class="badge bg-success">Completed</span>',
            'failed' => '<span class="badge bg-danger">Failed</span>'
        ];
        
        return $badges[$this->status] ?? '<span class="badge bg-secondary">Unknown</span>';
    }
}