<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\CacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class SocialAuthSettingsController extends Controller
{
    private const PROVIDERS = ['google', 'facebook', 'twitter', 'github'];

    /**
     * Get all social auth provider settings.
     * Sensitive values (secrets) are masked.
     */
    public function index()
    {
        $providers = [];
        foreach (self::PROVIDERS as $provider) {
            $providers[$provider] = [
                'enabled'           => (bool) Setting::getValue('social_' . $provider . '_enabled', false),
                'client_id'         => (string) Setting::getValue('social_' . $provider . '_client_id', ''),
                'client_secret'     => '', // Never expose the actual secret
                'client_has_secret' => !empty(Setting::getValue('social_' . $provider . '_client_secret', '')),
                'redirect_uri'      => (string) Setting::getValue('social_' . $provider . '_redirect_uri',
                    config('app.frontend_url', 'http://localhost:3000') . '/api/auth/' . $provider . '/callback'
                ),
            ];
        }

        return response()->json(['providers' => $providers]);
    }

    /**
     * Update social auth provider settings.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'provider'                  => 'required|string|in:' . implode(',', self::PROVIDERS),
            'enabled'                   => 'required|boolean',
            'client_id'                 => 'nullable|string|max:255',
            'client_secret'             => 'nullable|string|max:500',
            'redirect_uri'              => 'nullable|string|max:500',
        ]);

        $provider = $validated['provider'];

        Setting::setValue('social_' . $provider . '_enabled', $validated['enabled'] ? '1' : '0');

        if (!empty($validated['client_id'])) {
            Setting::setValue('social_' . $provider . '_client_id', $validated['client_id']);
        }

        if (!empty($validated['client_secret'])) {
            Setting::setValue('social_' . $provider . '_client_secret', $validated['client_secret']);
        }

        if (!empty($validated['redirect_uri'])) {
            Setting::setValue('social_' . $provider . '_redirect_uri', $validated['redirect_uri']);
        }

        app(CacheService::class)->invalidateSettings();

        return response()->json([
            'message' => 'Social auth settings saved successfully',
        ]);
    }
}
