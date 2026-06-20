<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'full_name',
        'email',
        'phone',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'is_default',
        'label',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    // =========================
    // RELATIONSHIPS
    // =========================
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // =========================
    // HELPERS
    // =========================
    public function getFullAddressAttribute(): string
    {
        $parts = [$this->address_line1];

        if ($this->address_line2) {
            $parts[] = $this->address_line2;
        }

        $parts[] = "{$this->city}, {$this->country}";

        return implode(', ', $parts);
    }
}
