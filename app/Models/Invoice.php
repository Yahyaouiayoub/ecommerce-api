<?php

namespace App\Models;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'invoice_number',
        'total_amount',
        'paid_amount',
        'status',
        'due_date',
        'notes',
        'billing_name',
        'billing_email',
        'billing_phone',
        'billing_address',
        'payment_method',
        'issued_at',
        'paid_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_date' => 'date',
        'issued_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    // =========================
    // RELATIONSHIPS
    // =========================
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    // =========================
    // ACCESSORS
    // =========================
    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float) $this->total_amount - (float) $this->paid_amount);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'unpaid'          => 'Unpaid',
            'partially_paid'  => 'Partially Paid',
            'paid'            => 'Paid',
            'pending'         => 'Pending',
            'failed'          => 'Failed',
            'refunded'        => 'Refunded',
            'cancelled'       => 'Cancelled',
            default           => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'unpaid'          => 'amber',
            'partially_paid'  => 'blue',
            'paid'            => 'emerald',
            'pending'         => 'slate',
            'failed'          => 'red',
            'refunded'        => 'purple',
            'cancelled'       => 'gray',
            default           => 'gray',
        };
    }

    public function getTotalFormattedAttribute(): string
    {
        return number_format($this->total_amount, 2) . ' MAD';
    }

    public function getPaidFormattedAttribute(): string
    {
        return number_format($this->paid_amount, 2) . ' MAD';
    }

    public function getRemainingFormattedAttribute(): string
    {
        return number_format($this->remaining_amount, 2) . ' MAD';
    }

    // =========================
    // SCOPES
    // =========================
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPaymentMethod($query, ?string $method)
    {
        if ($method) {
            return $query->where('payment_method', $method);
        }
        return $query;
    }

    public function scopeByDateRange($query, ?string $from, ?string $to)
    {
        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }
        return $query;
    }

    public function scopeSearch($query, ?string $search)
    {
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('order', function ($oq) use ($search) {
                      $oq->where('order_number', 'like', "%{$search}%")
                         ->orWhereHas('user', function ($uq) use ($search) {
                             $uq->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                         });
                  });
            });
        }
        return $query;
    }

    // =========================
    // HELPERS
    // =========================
    /**
     * Generate the next invoice number in sequence.
     * Uses settings for prefix and formatting.
     */
    public static function generateInvoiceNumber(): string
    {
        $prefix = Setting::getValue('invoice_prefix', 'INV-');
        $format = Setting::getValue('invoice_number_format', 'YEAR_MONTH_SEQ');

        $number = match ($format) {
            'YEAR_MONTH_SEQ' => $prefix . now()->format('Ym') . '-' . self::nextSequence($prefix . now()->format('Ym') . '-'),
            'YEAR_SEQ'       => $prefix . now()->format('Y') . '-' . self::nextSequence($prefix . now()->format('Y') . '-'),
            'MONTH_SEQ'      => $prefix . now()->format('m') . '-' . self::nextSequence($prefix . now()->format('m') . '-'),
            'SEQ'            => $prefix . self::nextSequence($prefix),
            default          => $prefix . now()->format('Ym') . '-' . self::nextSequence($prefix . now()->format('Ym') . '-'),
        };

        return $number;
    }

    private static function nextSequence(string $pattern): string
    {
        $lastInvoice = static::where('invoice_number', 'like', $pattern . '%')
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($lastInvoice) {
            $parts = explode('-', $lastInvoice->invoice_number);
            $lastNumber = (int) end($parts);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return str_pad((string) $newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Recalculate the payment status based on paid vs total amounts.
     */
    public function recalculateStatus(): static
    {
        // Don't auto-change if manually set to refunded/cancelled/failed/pending
        if (in_array($this->status, ['refunded', 'cancelled', 'failed', 'pending'])) {
            return $this;
        }

        if ((float) $this->paid_amount <= 0) {
            $this->status = 'unpaid';
            $this->paid_at = null;
        } elseif ((float) $this->paid_amount >= (float) $this->total_amount) {
            $this->status = 'paid';
            $this->paid_at = $this->paid_at ?? now();
        } else {
            $this->status = 'partially_paid';
            $this->paid_at = null;
        }

        return $this;
    }

    /**
     * Register a payment against this invoice.
     * Creates a Payment record and updates the invoice's paid_amount and status.
     */
    public function registerPayment(float $amount, string $paymentMethod = 'cod', string $paymentType = 'full', ?string $notes = null): Payment
    {
        $this->paid_amount += $amount;
        $this->recalculateStatus();
        $this->save();

        $payment = Payment::create([
            'order_id'       => $this->order_id,
            'invoice_id'     => $this->id,
            'amount'         => $amount,
            'currency'       => 'MAD',
            'payment_method' => $paymentMethod,
            'payment_type'   => $paymentType,
            'status'         => 'paid',
            'paid_at'        => now(),
        ]);

        return $payment;
    }

    /**
     * Mark this invoice as refunded.
     */
    public function markAsRefunded(): static
    {
        if ($this->status !== 'paid' && $this->status !== 'partially_paid') {
            return $this;
        }

        $this->status = 'refunded';
        $this->save();

        return $this;
    }

    /**
     * Mark this invoice as failed.
     */
    public function markAsFailed(): static
    {
        $this->status = 'failed';
        $this->save();

        return $this;
    }

    /**
     * Mark this invoice as cancelled.
     */
    public function markAsCancelled(): static
    {
        if ($this->paid_amount > 0) {
            return $this;
        }

        $this->status = 'cancelled';
        $this->save();

        return $this;
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isPartiallyPaid(): bool
    {
        return $this->status === 'partially_paid';
    }

    public function isUnpaid(): bool
    {
        return $this->status === 'unpaid';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Build the billing address display string.
     */
    public function getBillingAddressDisplayAttribute(): string
    {
        $parts = [];

        if ($this->billing_name) $parts[] = $this->billing_name;
        if ($this->billing_address) $parts[] = $this->billing_address;
        if ($this->billing_email) $parts[] = $this->billing_email;
        if ($this->billing_phone) $parts[] = $this->billing_phone;

        return implode("\n", $parts);
    }
}
