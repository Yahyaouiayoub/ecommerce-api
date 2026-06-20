<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    // =========================
    // GET USER ADDRESSES
    // =========================
    public function index(Request $request)
    {
        $addresses = Address::where('user_id', $request->user()->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($addresses);
    }

    // =========================
    // CREATE ADDRESS
    // =========================
    public function store(Request $request)
    {
        $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'required|string|max:255',
            'label' => 'nullable|string|max:50',
            'is_default' => 'boolean',
        ]);

        $user = $request->user();

        // If setting as default, unset any existing default
        if ($request->boolean('is_default')) {
            Address::where('user_id', $user->id)->update(['is_default' => false]);
        }

        // First address is always default
        $isFirst = Address::where('user_id', $user->id)->count() === 0;

        $address = Address::create([
            'user_id' => $user->id,
            'full_name' => $request->full_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address_line1' => $request->address_line1,
            'address_line2' => $request->address_line2,
            'city' => $request->city,
            'state' => $request->state,
            'postal_code' => $request->postal_code,
            'country' => $request->country,
            'label' => $request->label,
            'is_default' => $request->boolean('is_default') || $isFirst,
        ]);

        return response()->json([
            'message' => 'Address created successfully',
            'address' => $address,
        ], 201);
    }

    // =========================
    // UPDATE ADDRESS
    // =========================
    public function update(Request $request, $id)
    {
        $address = Address::where('user_id', $request->user()->id)->findOrFail($id);

        $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address_line1' => 'sometimes|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'sometimes|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'sometimes|string|max:255',
            'label' => 'nullable|string|max:50',
            'is_default' => 'boolean',
        ]);

        // If setting as default, unset any existing default
        if ($request->boolean('is_default')) {
            Address::where('user_id', $request->user()->id)
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        }

        $address->update($request->only([
            'full_name',
            'email',
            'phone',
            'address_line1',
            'address_line2',
            'city',
            'state',
            'postal_code',
            'country',
            'label',
            'is_default',
        ]));

        return response()->json([
            'message' => 'Address updated successfully',
            'address' => $address,
        ]);
    }

    // =========================
    // DELETE ADDRESS
    // =========================
    public function destroy(Request $request, $id)
    {
        $address = Address::where('user_id', $request->user()->id)->findOrFail($id);
        $address->delete();

        return response()->json([
            'message' => 'Address deleted successfully',
        ]);
    }

    // =========================
    // SET DEFAULT ADDRESS
    // =========================
    public function setDefault(Request $request, $id)
    {
        $user = $request->user();

        $address = Address::where('user_id', $user->id)->findOrFail($id);

        // Unset all other defaults
        Address::where('user_id', $user->id)
            ->where('id', '!=', $address->id)
            ->update(['is_default' => false]);

        $address->update(['is_default' => true]);

        return response()->json([
            'message' => 'Default address updated',
            'address' => $address,
        ]);
    }
}
