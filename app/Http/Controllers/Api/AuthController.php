<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // =========================
    // REGISTER
    // =========================
    public function register(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'session_id' => 'nullable|string',
        ]);

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'client',
        ]);

        // Merge guest cart if exists
        if ($request->session_id) {
            $this->mergeGuestCart($user, $request->session_id);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    // =========================
    // LOGIN
    // =========================
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'session_id' => 'nullable|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Merge guest cart if exists
        if ($request->session_id) {
            $this->mergeGuestCart($user, $request->session_id);
        }

        // Delete old tokens
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }

    // =========================
    // LOGOUT
    // =========================
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    // =========================
    // GET USER (ME)
    // =========================
    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    // =========================
    // UPDATE PROFILE
    // =========================
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|string|max:20',
            'address' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:255',
            'country' => 'sometimes|string|max:255',
        ]);

        $user->update($request->only([
            'first_name',
            'last_name',
            'email',
            'phone',
            'address',
            'city',
            'country'
        ]));

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }

    // =========================
    // MERGE GUEST CART
    // =========================
    private function mergeGuestCart($user, $sessionId)
    {
        $guestCart = Cart::where('session_id', $sessionId)->get();

        foreach ($guestCart as $item) {
            $userCart = Cart::where('user_id', $user->id)
                ->where('product_id', $item->product_id)
                ->first();

            if ($userCart) {
                $userCart->quantity += $item->quantity;
                $userCart->save();
                $item->delete();
            } else {
                $item->update([
                    'user_id' => $user->id,
                    'session_id' => null
                ]);
            }
        }
    }
}
