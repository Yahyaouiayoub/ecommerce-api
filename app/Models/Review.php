<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'user_id',
        'order_id',
        'rating',
        'comment',
        'status',
        'is_featured',
        'is_featured_active',
        'featured_order',
    ];

    protected function casts(): array
    {
        return [
            'rating'            => 'integer',
            'status'            => 'string',
            'is_featured'       => 'boolean',
            'is_featured_active'=> 'boolean',
            'featured_order'    => 'integer',
        ];
    }

    // =========================
    // STATUS HELPERS
    // =========================

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    public function scopeModerated(Builder $query): Builder
    {
        return $query->whereIn('status', ['approved', 'rejected']);
    }

    // =========================
    // RELATIONSHIPS
    // =========================
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // =========================
    // SCOPES
    // =========================

    /**
     * Scope to include only featured and active reviews.
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query
            ->where('is_featured', true)
            ->where('is_featured_active', true);
    }

    /**
     * Scope ordered by featured_order (ascending), then by newest.
     */
    public function scopeFeaturedSorted(Builder $query): Builder
    {
        return $query->orderBy('featured_order', 'asc')->orderBy('created_at', 'desc');
    }

    // =========================
    // HELPERS
    // =========================

    /**
     * Whether the review has a verified purchase.
     */
    public function getIsVerifiedPurchaseAttribute(): bool
    {
        return $this->order_id !== null && $this->order()->exists();
    }
}
