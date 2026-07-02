<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use Illuminate\Http\Request;

class SocialAccountController extends Controller
{
    /**
     * Get all linked social accounts for the authenticated user.
     */
    public function index(Request $request)
    {
        $accounts = $request->user()->socialAccounts()
            ->select(['id', 'provider', 'provider_id', 'created_at'])
            ->get()
            ->map(function ($account) {
                return [
                    'id'             => $account->id,
                    'provider'       => $account->provider,
                    'provider_label' => $this->providerLabel($account->provider),
                    'provider_id'    => $account->provider_id,
                    'linked_at'      => $account->created_at,
                ];
            });

        return response()->json([
            'accounts' => $accounts,
        ]);
    }

    /**
     * Unlink a social account.
     */
    public function destroy(Request $request, string $id)
    {
        $account = SocialAccount::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $providerLabel = $this->providerLabel($account->provider);

        $account->delete();

        return response()->json([
            'message' => "{$providerLabel} account has been unlinked.",
        ]);
    }

    /**
     * Get a human-readable label for the provider.
     */
    private function providerLabel(string $provider): string
    {
        return match ($provider) {
            'google'   => 'Google',
            'facebook' => 'Facebook',
            'twitter'  => 'X (Twitter)',
            'github'   => 'GitHub',
            default    => ucfirst($provider),
        };
    }
}
