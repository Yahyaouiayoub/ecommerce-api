<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Cart;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

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

        // Claim any guest orders made with this email
        $this->claimGuestOrders($user, $request->email);

        // Merge guest cart if exists
        if ($request->session_id) {
            $this->mergeGuestCart($user, $request->session_id);
        }

        // Send email verification notification
        try {
            $user->notify(new VerifyEmailNotification());
        } catch (\Exception $e) {
            // Don't block registration if email sending fails
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

        // Claim any guest orders made with this email
        $this->claimGuestOrders($user, $request->email);

        // Merge guest cart if exists
        if ($request->session_id) {
            $this->mergeGuestCart($user, $request->session_id);
        }

        // If 2FA is enabled, issue a short-lived challenge token instead
        if ($user->two_factor_enabled) {
            $challengeToken = $user->createToken('2fa-challenge', ['2fa-challenge'], now()->addMinutes(10));

            return response()->json([
                'message' => 'Two-factor authentication required.',
                'two_factor_required' => true,
                'challenge_token' => $challengeToken->plainTextToken,
                'user' => $user,
            ]);
        }

        // Delete old tokens
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        // Track last login
        $user->update(['last_login_at' => now()]);

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
    // LIST ACTIVE SESSIONS (API tokens)
    // =========================
    public function sessions(Request $request)
    {
        $tokens = $request->user()->tokens()
            ->orderBy('created_at', 'desc')
            ->get(['id', 'name', 'created_at', 'last_used_at']);

        // Mark the current session
        $currentTokenId = $request->user()->currentAccessToken()->id;

        return response()->json([
            'sessions' => $tokens->map(function ($token) use ($currentTokenId) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'is_current' => $token->id === $currentTokenId,
                    'created_at' => $token->created_at,
                    'last_used_at' => $token->last_used_at,
                ];
            }),
        ]);
    }

    // =========================
    // REVOKE SESSION (API token)
    // =========================
    public function revokeSession(Request $request, $id)
    {
        $token = $request->user()->tokens()->findOrFail($id);

        // Don't allow revoking the current session
        if ($token->id === $request->user()->currentAccessToken()->id) {
            return response()->json([
                'message' => 'Cannot revoke your current session.',
            ], 422);
        }

        $token->delete();

        return response()->json([
            'message' => 'Session revoked successfully.',
        ]);
    }

    // =========================
    // VERIFY 2FA & COMPLETE LOGIN
    // =========================
    public function verifyTwoFactor(Request $request)
    {
        $request->validate([
            'challenge_token' => 'required|string',
            'code' => 'required|string',
        ]);

        // Find the user by challenge token
        $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($request->challenge_token);

        if (!$personalAccessToken || !$personalAccessToken->can('2fa-challenge')) {
            return response()->json(['message' => 'Invalid or expired challenge token.'], 422);
        }

        $user = $personalAccessToken->tokenable;

        if (!$user->two_factor_secret) {
            return response()->json(['message' => '2FA is not set up.'], 422);
        }

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($user->two_factor_secret, $request->code);

        // Also check recovery codes
        if (!$valid && $user->two_factor_recovery_codes) {
            $codes = json_decode($user->two_factor_recovery_codes, true) ?? [];
            $index = array_search($request->code, $codes);
            if ($index !== false) {
                $valid = true;
                // Remove used recovery code
                unset($codes[$index]);
                $user->update(['two_factor_recovery_codes' => json_encode(array_values($codes))]);
            }
        }

        if (!$valid) {
            return response()->json(['message' => 'Invalid verification code.'], 422);
        }

        // Delete the challenge token
        $personalAccessToken->delete();

        // Delete old tokens and create new full-access token
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        // Track last login
        $user->update(['last_login_at' => now()]);

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }

    // =========================
    // CHANGE PASSWORD
    // =========================
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'message' => 'Password changed successfully.',
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

    // =========================
    // CLAIM GUEST ORDERS
    // =========================
    private function claimGuestOrders($user, $email)
    {
        // Attach any orders placed with this email as a guest to the user
        \App\Models\Order::whereNull('user_id')
            ->where('guest_email', $email)
            ->update(['user_id' => $user->id, 'session_id' => null]);
    }
}
