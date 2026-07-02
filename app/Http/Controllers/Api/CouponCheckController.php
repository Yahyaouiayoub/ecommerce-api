<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Setting;
use App\Services\CouponService;
use Illuminate\Http\Request;

class CouponCheckController extends Controller
{
    protected CouponService $couponService;

    public function __construct(CouponService $couponService)
    {
        $this->couponService = $couponService;
    }

    /**
     * POST /coupon/check
     * Validate a coupon code and return the discount details.
     * If no code is provided, automatically detect the best auto-apply coupon.
     * Does NOT apply the coupon — the frontend uses this to show the discount preview.
     */
    public function check(Request $request)
    {
        // Return early if coupons are globally disabled
        if (!(bool) Setting::getValue('coupons_enabled', true)) {
            return response()->json([
                'valid' => false,
                'message' => 'Coupons are currently disabled.',
            ], 422);
        }

        $request->validate([
            'code' => 'nullable|string|max:50',
            'subtotal' => 'required|numeric|min:0',
        ]);

        $user = $request->user();
        $guestEmail = $request->input('guest_email');

        // If no code provided, find the best auto-apply coupon
        if (!$request->code) {
            // Get cart product IDs to check against product-specific coupons
            $cartProductIds = $this->getCartProductIds($request);

            $result = $this->couponService->findBestAutoApply(
                (float) $request->subtotal,
                $cartProductIds,
                $user,
                $guestEmail
            );

            if (!$result) {
                return response()->json([
                    'valid' => false,
                    'auto_apply_checked' => true,
                    'message' => 'No auto-apply coupon available for your cart.',
                ]);
            }

            return response()->json([
                'valid' => true,
                'is_auto_apply' => true,
                'message' => $result['message'],
                'discount' => $result['discount'],
                'coupon' => [
                    'code' => $result['coupon']->code,
                    'type' => $result['coupon']->type,
                    'value' => (float) $result['coupon']->value,
                ],
            ]);
        }

        // Otherwise validate the specific coupon code
        $result = $this->couponService->validate(
            $request->code,
            (float) $request->subtotal,
            $user,
            $guestEmail
        );

        if (!$result['valid']) {
            return response()->json([
                'valid' => false,
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'message' => $result['message'],
            'discount' => $result['discount'],
            'coupon' => [
                'code' => $result['coupon']->code,
                'type' => $result['coupon']->type,
                'value' => (float) $result['coupon']->value,
            ],
        ]);
    }

    /**
     * Extract product IDs from the current user's cart.
     */
    private function getCartProductIds(Request $request): array
    {
        $user = auth('sanctum')->user();
        $sessionId = $request->header('X-Session-Id');

        $query = Cart::query();
        if ($user) {
            $query->where('user_id', $user->id);
        } elseif ($sessionId) {
            $query->where('session_id', $sessionId);
        } else {
            return [];
        }

        return $query->pluck('product_id')->toArray();
    }
}
