<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Get all settings grouped by their group.
     */
    public function index()
    {
        return response()->json([
            'settings' => Setting::getGrouped(),
            'shipping' => Setting::getShippingSettings(),
            'tax'      => Setting::getTaxSettings(),
            'invoice'  => Setting::getInvoiceSettings(),
        ]);
    }

    /**
     * Update one or more settings.
     * Expects an object of key-value pairs.
     */
    public function update(Request $request)
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable|string',
        ]);

        // Validate numeric values are non-negative
        $numericKeys = ['free_shipping_min_amount', 'standard_shipping_cost', 'tax_rate'];
        foreach ($numericKeys as $key) {
            if (isset($request->settings[$key]) && $request->settings[$key] !== '') {
                $value = (float) $request->settings[$key];
                if ($value < 0) {
                    return response()->json([
                        'message' => 'Validation failed.',
                        'errors' => [
                            $key => ["The {$key} cannot be negative."]
                        ]
                    ], 422);
                }
            }
        }

        // Validate tax rate doesn't exceed 100%
        if (isset($request->settings['tax_rate']) && $request->settings['tax_rate'] !== '') {
            $taxType = $request->settings['tax_type'] ?? 'percentage';
            $taxRate = (float) $request->settings['tax_rate'];
            if ($taxType === 'percentage' && $taxRate > 100) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => [
                        'tax_rate' => ['Tax rate cannot exceed 100%.']
                    ]
                ], 422);
            }
        }

        foreach ($request->settings as $key => $value) {
            Setting::setValue($key, $value !== null ? (string) $value : '');
        }

        return response()->json([
            'message'  => 'Settings updated successfully',
            'settings' => Setting::getGrouped(),
            'shipping' => Setting::getShippingSettings(),
            'tax'      => Setting::getTaxSettings(),
            'invoice'  => Setting::getInvoiceSettings(),
        ]);
    }
}
