<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShippingMethod;

class ShippingMethodController extends Controller
{
    /**
     * Public listing of active shipping methods.
     */
    public function index()
    {
        return response()->json(ShippingMethod::getActive());
    }
}
