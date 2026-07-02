<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Setting;
use App\Services\CouponService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CouponController extends Controller
{
    protected CouponService $couponService;

    public function __construct(CouponService $couponService)
    {
        $this->couponService = $couponService;
    }

    // =========================
    // LIST COUPONS
    // =========================
    public function index(Request $request)
    {
        $query = Coupon::withCount('usages');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            switch ($request->status) {
                case 'active':
                    $query->active();
                    break;
                case 'expired':
                    $query->where('expires_at', '<', now());
                    break;
                case 'scheduled':
                    $query->where('starts_at', '>', now());
                    break;
            }
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $perPage = (int) ($request->per_page ?? 20);
        $coupons = $query->latest()->paginate(min($perPage, 100));

        return response()->json($coupons);
    }

    // =========================
    // SHOW SINGLE COUPON
    // =========================
    public function show($id)
    {
        $coupon = Coupon::with('products:id,name,slug,price,thumbnail')->withCount('usages')->findOrFail($id);

        return response()->json([
            'coupon' => $coupon,
            'stats' => [
                'total_uses' => $coupon->usages_count,
                'total_discount' => (float) $coupon->usages()->sum('discount_amount'),
                'remaining_uses' => $coupon->remaining_uses,
                'is_valid' => $coupon->isValid(),
            ],
        ]);
    }

    // =========================
    // CREATE COUPON
    // =========================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50|unique:coupons,code',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0.01',
            'is_active' => 'sometimes|boolean',
            'is_auto_apply' => 'sometimes|boolean',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'per_customer_limit' => 'sometimes|integer|min:1',
            'applies_to' => 'required|in:all,specific',
            'product_ids' => 'required_if:applies_to,specific|array',
            'product_ids.*' => 'exists:products,id',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $productIds = $data['product_ids'] ?? [];
        unset($data['product_ids']);

        // Validate percentage value
        if ($data['type'] === 'percentage' && $data['value'] > 100) {
            return response()->json([
                'errors' => ['value' => ['Percentage discount cannot exceed 100%.']],
            ], 422);
        }

        $coupon = Coupon::create($data);

        if ($coupon->applies_to === 'specific' && !empty($productIds)) {
            $coupon->products()->attach($productIds);
        }

        $coupon->load('products');
        $coupon->loadCount('usages');

        return response()->json([
            'message' => 'Coupon created successfully.',
            'coupon' => $coupon,
        ], 201);
    }

    // =========================
    // UPDATE COUPON
    // =========================
    public function update(Request $request, $id)
    {
        $coupon = Coupon::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|string|max:50|unique:coupons,code,' . $id,
            'type' => 'sometimes|in:percentage,fixed',
            'value' => 'sometimes|numeric|min:0.01',
            'is_active' => 'sometimes|boolean',
            'is_auto_apply' => 'sometimes|boolean',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'per_customer_limit' => 'sometimes|integer|min:1',
            'applies_to' => 'sometimes|in:all,specific',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $productIds = $data['product_ids'] ?? null;
        unset($data['product_ids']);

        // Validate percentage value
        if (isset($data['type']) && $data['type'] === 'percentage' && isset($data['value']) && $data['value'] > 100) {
            return response()->json([
                'errors' => ['value' => ['Percentage discount cannot exceed 100%.']],
            ], 422);
        }

        $coupon->update($data);

        // Sync products if applies_to changed or product_ids provided
        if ($productIds !== null) {
            if (($data['applies_to'] ?? $coupon->applies_to) === 'specific') {
                $coupon->products()->sync($productIds);
            } else {
                $coupon->products()->detach();
            }
        }

        $coupon->load('products');
        $coupon->loadCount('usages');

        return response()->json([
            'message' => 'Coupon updated successfully.',
            'coupon' => $coupon,
        ]);
    }

    // =========================
    // DELETE COUPON
    // =========================
    public function destroy($id)
    {
        $coupon = Coupon::findOrFail($id);

        // Delete usage records first
        $coupon->usages()->delete();
        $coupon->products()->detach();
        $coupon->delete();

        return response()->json([
            'message' => 'Coupon deleted successfully.',
        ]);
    }

    // =========================
    // TOGGLE ACTIVE STATUS
    // =========================
    public function toggleActive($id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->update(['is_active' => !$coupon->is_active]);

        return response()->json([
            'message' => $coupon->is_active ? 'Coupon activated.' : 'Coupon deactivated.',
            'coupon' => $coupon->fresh()->loadCount('usages'),
        ]);
    }

    // =========================
    // TOGGLE GLOBAL COUPONS ENABLED SETTING
    // =========================
    public function toggleEnabled()
    {
        $current = (bool) Setting::getValue('coupons_enabled', true);
        Setting::setValue('coupons_enabled', $current ? 'false' : 'true');

        // Invalidate settings cache so the public endpoint picks up the change
        app(\App\Services\CacheService::class)->invalidateSettings();

        return response()->json([
            'message' => $current ? 'Coupons disabled.' : 'Coupons enabled.',
            'coupons_enabled' => !$current,
        ]);
    }

    // =========================
    // DASHBOARD STATS
    // =========================
    public function stats()
    {
        return response()->json(
            $this->couponService->getDashboardStats()
        );
    }
}
