<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::broker()->sendResetLink(
            $request->only('email'),
            function ($user, $token) {
                $user->notify(new ResetPasswordNotification($token));
            }
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'If your email exists in our system, you will receive a password reset link shortly.',
            ]);
        }

        // Don't reveal whether the email exists — return the same message for security
        return response()->json([
            'message' => 'If your email exists in our system, you will receive a password reset link shortly.',
        ]);
    }
}
