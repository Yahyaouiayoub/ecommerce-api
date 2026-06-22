<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;

class SettingsController extends Controller
{
    /**
     * Return public settings (shipping & tax info) — no auth required.
     */
    public function public()
    {
        return response()->json([
            'shipping' => Setting::getShippingSettings(),
            'tax'      => Setting::getTaxSettings(),
        ]);
    }
}
