<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingMethod extends Model
{
    protected $fillable = [
        'name',
        'description',
        'cost',
        'estimated_days',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'estimated_days' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get active shipping methods ordered by sort_order.
     */
    public static function getActive(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get the shipping cost, taking free shipping settings into account.
     */
    public function getEffectiveCost(float $subtotal): float
    {
        $shippingSettings = Setting::getShippingSettings();
        if ($shippingSettings['free_shipping'] && $subtotal >= $shippingSettings['free_shipping_min']) {
            return 0;
        }
        return (float) $this->cost;
    }
}
