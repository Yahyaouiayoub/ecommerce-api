<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class Promotion extends Model
{
    protected $fillable = [
        'title',
        'subtitle',
        'description',
        'cta_text',
        'cta_url',
        'background_image',
        'mobile_image',
        'background_color',
        'text_color',
        'discount_text',
        'badge',
        'starts_at',
        'ends_at',
        'is_active',
        'priority',
        'position',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority'  => 'integer',
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    // =========================
    // ACCESSORS
    // =========================

    public function getBackgroundImageUrlAttribute(): ?string
    {
        if (!$this->background_image) {
            return null;
        }
        return Storage::url($this->background_image);
    }

    public function getMobileImageUrlAttribute(): ?string
    {
        if (!$this->mobile_image) {
            return null;
        }
        return Storage::url($this->mobile_image);
    }

    /**
     * Whether the promotion is currently scheduled (within start/end dates).
     */
    public function getIsScheduledAttribute(): bool
    {
        $now = now();

        if ($this->starts_at && $this->starts_at->gt($now)) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->lt($now)) {
            return false;
        }

        return true;
    }

    /**
     * Returns a human-readable status label.
     */
    public function getStatusLabelAttribute(): string
    {
        $now = now();

        if (!$this->is_active) {
            return 'Disabled';
        }

        if ($this->starts_at && $this->starts_at->gt($now)) {
            return 'Scheduled';
        }

        if ($this->ends_at && $this->ends_at->lt($now)) {
            return 'Expired';
        }

        return 'Active';
    }

    // =========================
    // SCOPES
    // =========================

    /**
     * Scope to only include promotions that are currently active AND scheduled.
     */
    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    /**
     * Scope to filter by position.
     */
    public function scopePosition(Builder $query, string $position): Builder
    {
        if ($position === 'both') {
            return $query;
        }
        return $query->where(function (Builder $q) use ($position) {
            $q->where('position', $position)->orWhere('position', 'both');
        });
    }

    /**
     * Scope ordered by priority (highest first) and then newest.
     */
    public function scopeSorted(Builder $query): Builder
    {
        return $query->orderBy('priority', 'desc')->orderBy('created_at', 'desc');
    }

    // =========================
    // IMAGE HANDLING
    // =========================

    /**
     * Delete associated image files from storage.
     */
    public function deleteImages(): void
    {
        if ($this->background_image) {
            Storage::delete($this->background_image);
        }
        if ($this->mobile_image) {
            Storage::delete($this->mobile_image);
        }
    }

    protected static function booted(): void
    {
        static::deleting(function (Promotion $promotion) {
            $promotion->deleteImages();
        });
    }
}
