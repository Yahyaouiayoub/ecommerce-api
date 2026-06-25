<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;

class SettingsController extends Controller
{
    /**
     * Return public settings (shipping, tax, company info) — no auth required.
     */
    public function public()
    {
        return response()->json([
            'shipping'         => Setting::getShippingSettings(),
            'tax'              => Setting::getTaxSettings(),
            'logo_url'         => Setting::getValue('logo_url', ''),
            'company_name'     => Setting::getValue('company_name', 'Lumen Store'),
            'company_address'  => Setting::getValue('company_address', '123 Commerce Street'),
            'company_city'     => Setting::getValue('company_city', 'Casablanca'),
            'company_country'  => Setting::getValue('company_country', 'Morocco'),
            'company_phone'    => Setting::getValue('company_phone', '+212 5XX-XXXXXX'),
            'company_email'    => Setting::getValue('company_email', 'contact@lumenstore.com'),
        ]);
    }
}
