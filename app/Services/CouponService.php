<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CouponService
{
    /**
     * Validate a coupon code for the given context.
     *
     * @return array{valid: bool, message: string, coupon?: Coupon, discount?: float}
     */
    public function validate(string $code, float $subtotal, ?User $user = null, ?string $guestEmail = null): array
    {
        // Return early if coupons are globally disabled
        if (!(bool) Setting::getValue('coupons_enabled', true)) {
            return ['valid' => false, 'message' => 'Coupons are currently disabled.'];
        }

        $coupon = Coupon::with('products')->where('code', $code)->first();

        if (!$coupon) {
            return ['valid' => false, 'message' => 'Coupon code not found.'];
        }

        // Check if active and within date range
        if (!$coupon->isValid()) {
            if ($coupon->isExpired) {
                return ['valid' => false, 'message' => 'This coupon has expired.'];
            }
            if (!$coupon->is_started) {
                return ['valid' => false, 'message' => 'This coupon is not yet active.'];
            }
            if (!$coupon->is_active) {
                return ['valid' => false, 'message' => 'This coupon is no longer active.'];
            }
            if ($coupon->usage_limit && $coupon->usages()->count() >= $coupon->usage_limit) {
                return ['valid' => false, 'message' => 'This coupon has reached its usage limit.'];
            }
            return ['valid' => false, 'message' => 'This coupon is not valid.'];
        }

        // Check minimum order amount
        if ($coupon->min_order_amount && $subtotal < $coupon->min_order_amount) {
            return [
                'valid' => false,
                'message' => 'Minimum order amount of ' . number_format($coupon->min_order_amount, 2) . ' required for this coupon.',
            ];
        }

        // Check per-customer usage limit
        if ($user) {
            $userUsageCount = CouponUsage::where('coupon_id', $coupon->id)
                ->where('user_id', $user->id)
                ->count();
        } elseif ($guestEmail) {
            $userUsageCount = CouponUsage::where('coupon_id', $coupon->id)
                ->where('guest_email', $guestEmail)
                ->count();
        } else {
            $userUsageCount = 0;
        }

        if ($userUsageCount >= $coupon->per_customer_limit) {
            return ['valid' => false, 'message' => 'You have already used this coupon the maximum number of times.'];
        }

        // Calculate discount
        $discount = $coupon->calculateDiscount($subtotal);

        if ($discount <= 0) {
            return ['valid' => false, 'message' => 'This coupon does not apply to your current order.'];
        }

        return [
            'valid' => true,
            'message' => 'Coupon applied successfully!',
            'coupon' => $coupon,
            'discount' => $discount,
        ];
    }

    /**
     * Find the best auto-apply coupon for the given context.
     *
     * @return array{valid: bool, message: string, coupon?: Coupon, discount?: float}|null
     */
    public function findBestAutoApply(float $subtotal, array $cartProductIds = [], ?User $user = null, ?string $guestEmail = null): ?array
    {
        // Return early if coupons are globally disabled
        if (!(bool) Setting::getValue('coupons_enabled', true)) {
            return null;
        }

        $coupons = Coupon::with('products')
            ->autoApply()
            ->validNow()
            ->get();

        if ($coupons->isEmpty()) {
            return null;
        }

        $bestResult = null;
        $bestDiscount = 0;

        foreach ($coupons as $coupon) {
            // Check if coupon applies to at least one product in the cart
            if ($coupon->applies_to === 'specific') {
                $applicableProducts = $coupon->products->pluck('id')->toArray();
                $hasMatch = !empty(array_intersect($cartProductIds, $applicableProducts));
                if (!$hasMatch) continue;
            }

            // Check usage limit
            if (!$coupon->isValid()) continue;

            // Check per-customer limit
            if ($user) {
                $userUsageCount = CouponUsage::where('coupon_id', $coupon->id)
                    ->where('user_id', $user->id)
                    ->count();
            } elseif ($guestEmail) {
                $userUsageCount = CouponUsage::where('coupon_id', $coupon->id)
                    ->where('guest_email', $guestEmail)
                    ->count();
            } else {
                $userUsageCount = 0;
            }

            if ($userUsageCount >= $coupon->per_customer_limit) continue;

            // Check minimum order amount
            if ($coupon->min_order_amount && $subtotal < $coupon->min_order_amount) continue;

            // Calculate discount
            $discount = $coupon->calculateDiscount($subtotal);
            if ($discount <= 0) continue;

            // Pick the coupon that gives the highest discount
            if ($discount > $bestDiscount) {
                $bestDiscount = $discount;
                $bestResult = [
                    'valid' => true,
                    'message' => 'Coupon applied automatically!',
                    'coupon' => $coupon,
                    'discount' => $discount,
                    'is_auto_apply' => true,
                ];
            }
        }

        return $bestResult;
    }

    /**
     * Record coupon usage after order is placed.
     */
    public function recordUsage(Coupon $coupon, Order $order, ?User $user = null): CouponUsage
    {
        return CouponUsage::create([
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'user_id' => $user?->id,
            'guest_email' => $order->guest_email,
            'discount_amount' => $order->discount_amount,
        ]);
    }

    /**
     * Get coupon usage statistics for dashboard.
     */
    public function getDashboardStats(): array
    {
        $totalCoupons = Coupon::count();
        $activeCoupons = Coupon::active()->count();
        $validNowCoupons = Coupon::validNow()->count();

        $totalDiscountGiven = (float) CouponUsage::sum('discount_amount');
        $totalUsageCount = CouponUsage::count();

        // Most used coupons
        $mostUsed = Coupon::withCount('usages')
            ->orderByDesc('usages_count')
            ->take(5)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'type' => $c->type,
                'value' => (float) $c->value,
                'usages_count' => $c->usages_count,
                'total_discount' => (float) CouponUsage::where('coupon_id', $c->id)->sum('discount_amount'),
            ]);

        return [
            'total_coupons' => $totalCoupons,
            'active_coupons' => $activeCoupons,
            'valid_now_coupons' => $validNowCoupons,
            'total_discount_given' => $totalDiscountGiven,
            'total_usage_count' => $totalUsageCount,
            'most_used_coupons' => $mostUsed,
        ];
    }
}
