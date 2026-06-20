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
            default           => ucfirst($this->status),
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
    // HELPERS
    // =========================
    /**
     * Generate the next invoice number in sequence.
     * Format: INV-YYYYMM-XXXX (e.g. INV-202606-0001)
     */
    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV-' . now()->format('Ym') . '-';

        $lastInvoice = static::where('invoice_number', 'like', $prefix . '%')
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($lastInvoice) {
            $lastNumber = (int) Str::substr($lastInvoice->invoice_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad((string) $newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Recalculate the payment status based on paid vs total amounts.
     */
    public function recalculateStatus(): static
    {
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
}
