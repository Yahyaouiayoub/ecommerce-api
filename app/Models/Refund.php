<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'refund_number',
        'status',
        'reason',
        'description',
        'refund_amount',
        'internal_notes',
        'guest_email',
        'guest_name',
        'approved_at',
        'rejected_at',
        'completed_at',
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // =========================
    // RELATIONSHIPS
    // =========================
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(RefundItem::class);
    }

    public function images()
    {
        return $this->hasMany(RefundImage::class);
    }

    // =========================
    // ACCESSORS
    // =========================
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending'   => 'Pending',
            'approved'  => 'Approved',
            'rejected'  => 'Rejected',
            'completed' => 'Completed',
            default     => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending'   => 'amber',
            'approved'  => 'blue',
            'rejected'  => 'red',
            'completed' => 'emerald',
            default     => 'gray',
        };
    }

    public function getRefundAmountFormattedAttribute(): string
    {
        return number_format($this->refund_amount, 2) . ' MAD';
    }

    // =========================
    // SCOPES
    // =========================
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByStatus($query, string $status)
    {
        if ($status) {
            return $query->where('status', $status);
        }
        return $query;
    }

    public function scopeSearch($query, ?string $search)
    {
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('refund_number', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('order', function ($oq) use ($search) {
                      $oq->where('order_number', 'like', "%{$search}%");
                  })
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }
        return $query;
    }

    // =========================
    // HELPERS
    // =========================
    public static function generateRefundNumber(): string
    {
        $prefix = 'RFD-';
        $lastRefund = static::where('refund_number', 'like', $prefix . now()->format('Ym') . '-%')
            ->orderBy('refund_number', 'desc')
            ->first();

        if ($lastRefund) {
            $parts = explode('-', $lastRefund->refund_number);
            $lastNumber = (int) end($parts);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . now()->format('Ym') . '-' . str_pad((string) $newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get the display name for the requester (user or guest).
     */
    public function getRequesterNameAttribute(): string
    {
        return $this->user?->full_name ?? $this->guest_name ?? 'Guest';
    }

    /**
     * Get the display email for the requester.
     */
    public function getRequesterEmailAttribute(): string
    {
        return $this->user?->email ?? $this->guest_email ?? '';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
