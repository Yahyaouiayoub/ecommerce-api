<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'value',
        'is_active',
        'is_auto_apply',
        'starts_at',
        'expires_at',
        'min_order_amount',
        'max_discount_amount',
        'usage_limit',
        'per_customer_limit',
        'applies_to',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_auto_apply' => 'boolean',
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // =========================
    // RELATIONSHIPS
    // =========================
    public function products()
    {
        return $this->belongsToMany(Product::class, 'coupon_product');
    }

    public function usages()
    {
        return $this->hasMany(CouponUsage::class);
    }

    // =========================
    // SCOPES
    // =========================
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValidNow($query)
    {
        return $query->active()
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            });
    }

    public function scopeCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    public function scopeAutoApply($query)
    {
        return $query->where('is_auto_apply', true);
    }

    // =========================
    // HELPERS
    // =========================
    public function isValid(): bool
    {
        if (!$this->is_active) return false;

        if ($this->starts_at && now()->lt($this->starts_at)) return false;
        if ($this->expires_at && now()->gt($this->expires_at)) return false;

        if ($this->usage_limit && $this->usages()->count() >= $this->usage_limit) return false;

        return true;
    }

    public function isValidForUser(?int $userId, ?string $guestEmail = null): bool
    {
        if (!$this->isValid()) return false;

        if ($userId) {
            $userUsageCount = $this->usages()->where('user_id', $userId)->count();
        } elseif ($guestEmail) {
            $userUsageCount = $this->usages()->where('guest_email', $guestEmail)->count();
        } else {
            return false; // cannot validate without user or guest email
        }

        return $userUsageCount < $this->per_customer_limit;
    }

    public function appliesToProduct(int $productId): bool
    {
        if ($this->applies_to === 'all') return true;

        return $this->products()->where('product_id', $productId)->exists();
    }

    public function calculateDiscount(float $subtotal): float
    {
        if ($subtotal <= 0) return 0;

        if ($this->min_order_amount && $subtotal < $this->min_order_amount) return 0;

        $discount = $this->type === 'percentage'
            ? round($subtotal * ($this->value / 100), 2)
            : $this->value;

        // Cap by max_discount_amount if set
        if ($this->max_discount_amount && $discount > $this->max_discount_amount) {
            $discount = (float) $this->max_discount_amount;
        }

        // Never discount more than the subtotal
        return min($discount, $subtotal);
    }

    public function getRemainingUsesAttribute(): ?int
    {
        if ($this->usage_limit === null) return null;

        $used = $this->usages()->count();
        return max(0, $this->usage_limit - $used);
    }

    public function getIsExpiredAttribute(): bool
    {
        if (!$this->expires_at) return false;
        return now()->gt($this->expires_at);
    }

    public function getIsStartedAttribute(): bool
    {
        if (!$this->starts_at) return true;
        return now()->gte($this->starts_at);
    }
}
