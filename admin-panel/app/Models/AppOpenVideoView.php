<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppOpenVideoView extends Model
{
    public $timestamps = false;

    protected $fillable = ['app_open_video_id', 'user_id', 'skipped', 'viewed_at'];

    protected $casts = ['skipped' => 'boolean', 'viewed_at' => 'datetime'];

    public function video(): BelongsTo
    {
        return $this->belongsTo(AppOpenVideo::class, 'app_open_video_id');
    }
}
