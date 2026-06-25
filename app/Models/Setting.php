<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    /**
     * Get a setting value by key with a default fallback.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Set a setting value by key.
     */
    public static function setValue(string $key, mixed $value): void
    {
        // Determine group from key prefix so getGrouped() lookups still work
        $group = self::inferGroup($key);

        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group]
        );
    }

    /**
     * Infer a settings group from the key name.
     */
    private static function inferGroup(string $key): ?string
    {
        if (str_starts_with($key, 'shipping_') || str_starts_with($key, 'free_shipping_') || str_starts_with($key, 'standard_shipping_')) {
            return 'shipping';
        }
        if (str_starts_with($key, 'tax_')) {
            return 'tax';
        }
        if (str_starts_with($key, 'invoice_') || str_starts_with($key, 'company_')) {
            return 'invoice';
        }
        if (str_starts_with($key, 'logo_')) {
            return 'general';
        }
        return null;
    }

    /**
     * Get all settings grouped by their group.
     */
    public static function getGrouped(): array
    {
        $settings = static::all();
        $grouped = [];

        foreach ($settings as $setting) {
            $grouped[$setting->group][$setting->key] = $setting->value;
        }

        return $grouped;
    }

    /**
     * Get all shipping settings as an array.
     */
    public static function getShippingSettings(): array
    {
        $settings = static::where('group', 'shipping')->get()->keyBy('key')->map->value->toArray();

        return [
            'enabled'             => (bool) ($settings['shipping_enabled'] ?? true),
            'free_shipping'       => (bool) ($settings['free_shipping_enabled'] ?? true),
            'free_shipping_min'   => (float) ($settings['free_shipping_min_amount'] ?? 75),
            'standard_cost'       => (float) ($settings['standard_shipping_cost'] ?? 8),
            'message'             => (string) ($settings['shipping_message'] ?? 'Free shipping on orders over $75'),
        ];
    }

    /**
     * Get all tax settings as an array.
     */
    public static function getTaxSettings(): array
    {
        $settings = static::where('group', 'tax')->get()->keyBy('key')->map->value->toArray();

        return [
            'enabled'  => (bool) ($settings['tax_enabled'] ?? true),
            'rate'     => (float) ($settings['tax_rate'] ?? 8),
            'type'     => (string) ($settings['tax_type'] ?? 'percentage'),
            'label'    => (string) ($settings['tax_label'] ?? 'Estimated tax'),
        ];
    }

    /**
     * Get all invoice settings as an array.
     */
    public static function getInvoiceSettings(): array
    {
        $settings = static::where('group', 'invoice')->get()->keyBy('key')->map->value->toArray();

        return [
            'auto_generate'        => (bool) ($settings['invoice_auto_generate'] ?? true),
            'prefix'               => (string) ($settings['invoice_prefix'] ?? 'INV-'),
            'number_format'        => (string) ($settings['invoice_number_format'] ?? 'YEAR_MONTH_SEQ'),
            'company_name'         => (string) ($settings['company_name'] ?? 'Lumen Store'),
            'company_address'      => (string) ($settings['company_address'] ?? '123 Commerce Street'),
            'company_city'         => (string) ($settings['company_city'] ?? 'Casablanca'),
            'company_country'      => (string) ($settings['company_country'] ?? 'Morocco'),
            'company_phone'        => (string) ($settings['company_phone'] ?? '+212 5XX-XXXXXX'),
            'company_email'        => (string) ($settings['company_email'] ?? 'contact@lumenstore.com'),
            'payment_terms'        => (int) ($settings['invoice_payment_terms_days'] ?? 30),
            'footer_notes'         => (string) ($settings['invoice_footer_notes'] ?? 'Thank you for your business!'),
        ];
    }
}
