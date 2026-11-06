<?php

namespace App\Models;

use App\Services\MediaStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A video the restaurant app plays when it opens, once per person, skippable. Uploaded from the admin panel. */
class AppOpenVideo extends Model
{
    protected $fillable = ['title', 'audience', 'video_path', 'skip_after_seconds', 'is_active', 'starts_at', 'ends_at', 'sort_order'];

    protected $casts = [
        'is_active' => 'boolean',
        'skip_after_seconds' => 'integer',
        'sort_order' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function views(): HasMany
    {
        return $this->hasMany(AppOpenVideoView::class);
    }

    public function scopeCurrent(Builder $query, string $audience = 'restaurant'): Builder
    {
        return $query->where('audience', $audience)->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function url(): string
    {
        return MediaStorage::url($this->video_path);
    }

    /**
     * Identifies this exact video file. The file name is unique per upload, so replacing the file gives a new key and
     * the app plays it again for everyone, while an untouched video never replays.
     */
    public function versionKey(): string
    {
        return $this->id . ':' . substr(md5((string) $this->video_path), 0, 10);
    }
}
