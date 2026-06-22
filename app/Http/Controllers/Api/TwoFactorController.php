<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    /**
     * Generate a new 2FA secret and return the QR code URL + secret.
     * Does NOT enable 2FA yet — the user must first verify via confirm().
     */
    public function enable(Request $request)
    {
        $user = $request->user();

        // If 2FA is already enabled, return status
        if ($user->two_factor_enabled) {
            return response()->json([
                'enabled' => true,
                'message' => 'Two-factor authentication is already enabled.',
            ]);
        }

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey(32);

        // Store secret temporarily (not enabled yet)
        $user->update(['two_factor_secret' => $secret]);

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name', 'Lumen'),
            $user->email,
            $secret,
        );

        return response()->json([
            'enabled' => false,
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
        ]);
    }

    /**
     * Confirm 2FA setup by verifying a TOTP code from the authenticator app.
     * On success, enables 2FA and generates recovery codes.
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if (!$user->two_factor_secret) {
            return response()->json([
                'message' => 'No 2FA secret found. Call enable first.',
            ], 422);
        }

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($user->two_factor_secret, $request->code);

        if (!$valid) {
            return response()->json([
                'message' => 'Invalid verification code. Please try again.',
            ], 422);
        }

        // Generate recovery codes (10 codes)
        $recoveryCodes = [];
        for ($i = 0; $i < 10; $i++) {
            $recoveryCodes[] = bin2hex(random_bytes(5));
        }

        $user->update([
            'two_factor_enabled' => true,
            'two_factor_recovery_codes' => json_encode($recoveryCodes),
        ]);

        return response()->json([
            'message' => 'Two-factor authentication enabled successfully.',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Disable 2FA. Requires the current password for security.
     */
    public function disable(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
            ], 422);
        }

        $user->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_enabled' => false,
        ]);

        return response()->json([
            'message' => 'Two-factor authentication disabled.',
        ]);
    }

    /**
     * Get the current 2FA status (enabled/disabled).
     */
    public function status(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'enabled' => $user->two_factor_enabled,
        ]);
    }

    /**
     * Regenerate recovery codes (requires current password).
     */
    public function regenerateRecoveryCodes(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
            ], 422);
        }

        if (!$user->two_factor_enabled) {
            return response()->json([
                'message' => '2FA is not enabled.',
            ], 422);
        }

        $recoveryCodes = [];
        for ($i = 0; $i < 10; $i++) {
            $recoveryCodes[] = bin2hex(random_bytes(5));
        }

        $user->update([
            'two_factor_recovery_codes' => json_encode($recoveryCodes),
        ]);

        return response()->json([
            'message' => 'Recovery codes regenerated.',
            'recovery_codes' => $recoveryCodes,
        ]);
    }
}
