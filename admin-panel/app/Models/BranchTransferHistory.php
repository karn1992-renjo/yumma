<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BranchTransferHistory extends Model
{
    protected $table = 'branch_transfer_history';

    protected $fillable = [
        'from_branch_id',
        'to_branch_id',
        'transferable_type',
        'transferable_id',
        'transferred_by',
        'reason',
        'meta',
    ];

    protected $casts = ['meta' => 'array'];

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function transferable(): MorphTo
    {
        return $this->morphTo();
    }
}
