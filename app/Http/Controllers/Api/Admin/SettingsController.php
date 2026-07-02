<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\CacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    /**
     * Get all settings grouped by their group. (cached)
     */
    public function index(?CacheService $cacheService = null)
    {
        $cacheService ??= app(CacheService::class);

        $data = $cacheService->rememberAdminSettings(function () {
            return [
                'settings' => Setting::getGrouped(),
                'shipping' => Setting::getShippingSettings(),
                'tax'      => Setting::getTaxSettings(),
                'invoice'  => Setting::getInvoiceSettings(),
                'logo_url' => Setting::getValue('logo_url', ''),
            ];
        });

        return response()->json($data);
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

        // Invalidate settings cache
        app(CacheService::class)->invalidateSettings();

        return response()->json([
            'message'  => 'Settings updated successfully',
            'settings' => Setting::getGrouped(),
            'shipping' => Setting::getShippingSettings(),
            'tax'      => Setting::getTaxSettings(),
            'invoice'  => Setting::getInvoiceSettings(),
            'logo_url' => Setting::getValue('logo_url', ''),
        ]);
    }

    /**
     * Upload a logo image.
     */
    public function uploadLogo(Request $request)
    {
        app(CacheService::class)->invalidateSettings();
        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
        ]);

        // Delete old logo if exists
        $oldLogo = Setting::getValue('logo_url');
        if ($oldLogo && Storage::disk('public')->exists($oldLogo)) {
            Storage::disk('public')->delete($oldLogo);
        }

        $path = $request->file('logo')->store('logos', 'public');

        Setting::setValue('logo_url', $path);

        $url = Storage::disk('public')->url($path);

        return response()->json([
            'message' => 'Logo uploaded successfully',
            'logo_url' => $url,
            'logo_path' => $path,
        ]);
    }

    /**
     * Delete the logo (reset to default).
     */
    public function deleteLogo()
    {
        app(CacheService::class)->invalidateSettings();
        $logoPath = Setting::getValue('logo_url');

        if ($logoPath && Storage::disk('public')->exists($logoPath)) {
            Storage::disk('public')->delete($logoPath);
        }

        Setting::setValue('logo_url', '');

        return response()->json([
            'message' => 'Logo removed successfully',
        ]);
    }
}
