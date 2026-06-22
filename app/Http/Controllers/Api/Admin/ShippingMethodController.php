<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingMethod;
use Illuminate\Http\Request;

class ShippingMethodController extends Controller
{
    public function index()
    {
        $methods = ShippingMethod::orderBy('sort_order')
            ->orderBy('name')
            ->get();
        return response()->json($methods);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'cost' => 'required|numeric|min:0',
            'estimated_days' => 'nullable|integer|min:1',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $method = ShippingMethod::create([
            'name' => $request->name,
            'description' => $request->description,
            'cost' => $request->cost,
            'estimated_days' => $request->estimated_days,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'message' => 'Shipping method created',
            'shipping_method' => $method,
        ], 201);
    }

    public function update(Request $request, ShippingMethod $shippingMethod)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:500',
            'cost' => 'sometimes|required|numeric|min:0',
            'estimated_days' => 'nullable|integer|min:1',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $shippingMethod->update($request->only([
            'name', 'description', 'cost', 'estimated_days', 'sort_order', 'is_active',
        ]));

        return response()->json([
            'message' => 'Shipping method updated',
            'shipping_method' => $shippingMethod->fresh(),
        ]);
    }

    public function destroy(ShippingMethod $shippingMethod)
    {
        $shippingMethod->delete();

        return response()->json([
            'message' => 'Shipping method deleted',
        ]);
    }
}
