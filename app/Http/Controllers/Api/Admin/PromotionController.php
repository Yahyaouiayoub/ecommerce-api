<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromotionController extends Controller
{
    public function __construct(
        private PromotionService $promotionService
    ) {}

    /**
     * Get all promotions (paginated) with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Promotion::query();

        // Filter by position
        if ($request->filled('position')) {
            $query->where('position', $request->position);
        }

        // Filter by status
        if ($request->filled('status')) {
            $now = now();
            match ($request->status) {
                'active'    => $query->where('is_active', true)
                    ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                    ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now)),
                'scheduled' => $query->where('is_active', true)->where('starts_at', '>', $now),
                'expired'   => $query->where('is_active', true)->where('ends_at', '<', $now),
                'disabled'  => $query->where('is_active', false),
                default     => null,
            };
        }

        // Search by title
        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $promotions = $query->sorted()->paginate($request->input('per_page', 20));

        // Transform to include computed fields
        $promotions->getCollection()->transform(function ($promotion) {
            return $this->formatAdminPromotion($promotion);
        });

        return response()->json($promotions);
    }

    /**
     * Get a single promotion by ID.
     */
    public function show(int $id): JsonResponse
    {
        $promotion = Promotion::findOrFail($id);

        return response()->json([
            'data' => $this->formatAdminPromotion($promotion),
        ]);
    }

    /**
     * Create a new promotion.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'             => 'required|string|max:255',
            'subtitle'          => 'nullable|string|max:255',
            'description'       => 'nullable|string',
            'cta_text'          => 'nullable|string|max:255',
            'cta_url'           => 'nullable|string|max:500',
            'background_image'  => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'mobile_image'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'background_color'  => 'nullable|string|max:50',
            'text_color'        => 'nullable|string|max:50',
            'discount_text'     => 'nullable|string|max:255',
            'badge'             => 'nullable|string|max:100',
            'starts_at'         => 'nullable|date',
            'ends_at'           => 'nullable|date|after_or_equal:starts_at',
            'is_active'         => 'boolean',
            'priority'          => 'integer|min:0|max:999',
            'position'          => ['required', Rule::in(['announcement_bar', 'hero_banner', 'both'])],
        ]);

        $backgroundImage = $request->file('background_image');
        $mobileImage = $request->file('mobile_image');

        $promotion = $this->promotionService->create($validated, $backgroundImage, $mobileImage);

        return response()->json([
            'message'  => 'Promotion created successfully.',
            'data'     => $this->formatAdminPromotion($promotion),
        ], 201);
    }

    /**
     * Update an existing promotion.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $promotion = Promotion::findOrFail($id);

        $validated = $request->validate([
            'title'             => 'sometimes|required|string|max:255',
            'subtitle'          => 'nullable|string|max:255',
            'description'       => 'nullable|string',
            'cta_text'          => 'nullable|string|max:255',
            'cta_url'           => 'nullable|string|max:500',
            'background_image'  => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'mobile_image'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'background_color'  => 'nullable|string|max:50',
            'text_color'        => 'nullable|string|max:50',
            'discount_text'     => 'nullable|string|max:255',
            'badge'             => 'nullable|string|max:100',
            'starts_at'         => 'nullable|date',
            'ends_at'           => 'nullable|date|after_or_equal:starts_at',
            'is_active'         => 'boolean',
            'priority'          => 'integer|min:0|max:999',
            'position'          => [Rule::in(['announcement_bar', 'hero_banner', 'both'])],
        ]);

        $backgroundImage = $request->file('background_image');
        $mobileImage = $request->file('mobile_image');

        $promotion = $this->promotionService->update($promotion, $validated, $backgroundImage, $mobileImage);

        return response()->json([
            'message'  => 'Promotion updated successfully.',
            'data'     => $this->formatAdminPromotion($promotion),
        ]);
    }

    /**
     * Delete a promotion.
     */
    public function destroy(int $id): JsonResponse
    {
        $promotion = Promotion::findOrFail($id);
        $this->promotionService->delete($promotion);

        return response()->json([
            'message' => 'Promotion deleted successfully.',
        ]);
    }

    /**
     * Toggle the active status of a promotion.
     */
    public function toggleActive(int $id): JsonResponse
    {
        $promotion = Promotion::findOrFail($id);
        $promotion = $this->promotionService->toggleActive($promotion);

        return response()->json([
            'message' => $promotion->is_active ? 'Promotion enabled.' : 'Promotion disabled.',
            'data'    => $this->formatAdminPromotion($promotion),
        ]);
    }

    /**
     * Get promotion dashboard stats.
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'data' => $this->promotionService->getDashboardStats(),
        ]);
    }

    /**
     * Format a promotion with computed fields for admin.
     */
    private function formatAdminPromotion($promotion): array
    {
        return [
            'id'                => $promotion->id,
            'title'             => $promotion->title,
            'subtitle'          => $promotion->subtitle,
            'description'       => $promotion->description,
            'cta_text'          => $promotion->cta_text,
            'cta_url'           => $promotion->cta_url,
            'background_image'  => $promotion->background_image,
            'background_image_url' => $promotion->background_image_url,
            'mobile_image'      => $promotion->mobile_image,
            'mobile_image_url'  => $promotion->mobile_image_url,
            'background_color'  => $promotion->background_color,
            'text_color'        => $promotion->text_color,
            'discount_text'     => $promotion->discount_text,
            'badge'             => $promotion->badge,
            'starts_at'         => $promotion->starts_at?->toIso8601String(),
            'ends_at'           => $promotion->ends_at?->toIso8601String(),
            'is_active'         => $promotion->is_active,
            'status_label'      => $promotion->status_label,
            'priority'          => $promotion->priority,
            'position'          => $promotion->position,
            'created_at'        => $promotion->created_at?->toIso8601String(),
            'updated_at'        => $promotion->updated_at?->toIso8601String(),
        ];
    }
}
