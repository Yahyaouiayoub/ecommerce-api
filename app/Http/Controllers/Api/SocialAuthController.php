<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    private const SUPPORTED_PROVIDERS = ['google', 'facebook', 'twitter', 'github'];

    /**
     * Redirect the user to the OAuth provider.
     */
    public function redirect(string $provider)
    {
        if (!in_array($provider, self::SUPPORTED_PROVIDERS)) {
            return response()->json(['message' => 'Unsupported provider.'], 422);
        }

        // Load provider config from dashboard settings if configured
        $this->applyProviderConfig($provider);

        $redirectUrl = Socialite::driver($provider)
            ->stateless()
            ->redirect()
            ->getTargetUrl();

        return response()->json(['redirect_url' => $redirectUrl]);
    }

    /**
     * Handle the OAuth callback from the provider.
     * Redirects to the frontend with token/success in URL hash.
     */
    public function callback(string $provider, Request $request)
    {
        if (!in_array($provider, self::SUPPORTED_PROVIDERS)) {
            $this->redirectToFrontend(false, 'Unsupported provider.');
        }

        // Check for error from provider
        if ($request->has('error')) {
            $errorMsg = $request->input('error_description', $request->input('error', 'Authentication cancelled'));
            $this->redirectToFrontend(false, 'Social authentication failed: ' . $errorMsg);
        }

        try {
            $this->applyProviderConfig($provider);

            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Exception $e) {
            $this->redirectToFrontend(false, 'Failed to authenticate with ' . ucfirst($provider) . ': ' . $e->getMessage());
        }

        if (!$socialUser->getEmail()) {
            $this->redirectToFrontend(false, ucfirst($provider) . ' did not return an email address.');
        }

        // Check if this social account is already linked
        $existingAccount = SocialAccount::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($existingAccount) {
            $user = $existingAccount->user;
        } else {
            $user = User::where('email', $socialUser->getEmail())->first();

            if ($user) {
                $this->createSocialAccount($user, $provider, $socialUser);
            } else {
                $name = $socialUser->getName() ?? $socialUser->getNickname() ?? $socialUser->getEmail();
                $nameParts = explode(' ', $name, 2);
                $firstName = $nameParts[0] ?? 'User';
                $lastName = $nameParts[1] ?? '';

                $user = User::create([
                    'first_name'        => $firstName,
                    'last_name'         => $lastName,
                    'email'             => $socialUser->getEmail(),
                    'password'          => bcrypt(str()->random(32)),
                    'role'              => 'client',
                    'avatar'            => $socialUser->getAvatar(),
                    'email_verified_at' => now(),
                ]);

                $this->createSocialAccount($user, $provider, $socialUser);
            }
        }

        // Generate token and login
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;
        $user->update(['last_login_at' => now()]);

        $this->redirectToFrontend(true, null, $token, $user);
    }

    /**
     * Redirect to the frontend OAuth callback page with result.
     */
    private function redirectToFrontend(bool $success, ?string $error = null, ?string $token = null, ?User $user = null): never
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        $params = [
            'success' => $success ? 'true' : 'false',
        ];

        if ($error) {
            $params['error'] = $error;
        }

        if ($token) {
            $params['token'] = $token;
        }

        if ($user) {
            $params['role'] = $user->role;
        }

        $redirectUrl = $frontendUrl . '/auth/callback?' . http_build_query($params);

        abort(redirect()->away($redirectUrl));
    }

    /**
     * Get the enabled social providers for the frontend.
     */
    public function providers()
    {
        $providers = [];
        foreach (self::SUPPORTED_PROVIDERS as $provider) {
            $enabled = Setting::getValue('social_' . $provider . '_enabled', false);
            if ($enabled) {
                $providers[] = [
                    'name'    => $provider,
                    'label'   => ucfirst($provider === 'twitter' ? 'X' : $provider),
                ];
            }
        }
        return response()->json(['providers' => $providers]);
    }

    /**
     * Apply the provider configuration from dashboard settings.
     */
    private function applyProviderConfig(string $provider): void
    {
        $clientId = Setting::getValue('social_' . $provider . '_client_id', '');
        $clientSecret = Setting::getValue('social_' . $provider . '_client_secret', '');
        $redirectUri = Setting::getValue('social_' . $provider . '_redirect_uri', '');

        if (empty($clientId) || empty($clientSecret)) {
            // Fall back to .env config
            return;
        }

        $config = [
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'redirect'      => $redirectUri,
        ];

        Config::set('services.' . $provider, $config);
    }

    /**
     * Create a social account record.
     */
    private function createSocialAccount(User $user, string $provider, $socialUser): SocialAccount
    {
        return SocialAccount::create([
            'user_id'              => $user->id,
            'provider'             => $provider,
            'provider_id'          => (string) $socialUser->getId(),
            'provider_token'       => $socialUser->token,
            'provider_refresh_token' => $socialUser->refreshToken,
        ]);
    }
}
